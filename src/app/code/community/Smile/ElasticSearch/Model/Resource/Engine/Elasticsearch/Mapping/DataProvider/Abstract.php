<?php

abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Abstract
{
    /**
     *
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    private $_mapping;

    public function setMapping($mapping)
    {
        $this->_mapping = $mapping;
    }

    public function updateAllData($storeId = null, $entityIds = null)
    {
        if ($storeId == null) {
            $stores = Mage::app()->getStores();
            foreach ($stores as $store) {
                $this->updateAllData($store->getId(), $entityIds);
            }
        } else if ($entityIds == null) {
            $this->_updateStoreEntities($storeId);
        } else {
            $this->_updateEntities($storeId, $entityIds);
        }

        return $this;
    }

    public function getCurrentIndex()
    {
        return $this->_mapping->getCurrentIndex();
    }

    public function getClient()
    {
        return $this->getCurrentIndex()->getClient();
    }

    protected function _updateStoreEntities($storeId) {
        $bulkSize = Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index::COPY_DATA_BULK_SIZE;
        $scrollQuery = array(
            'index' => $this->_mapping->getCurrentIndex()->getCurrentName(),
            'type'  => $this->_mapping->getType(),
            'size'  => $bulkSize,
            'scroll'      => '5m',
            'search_type' => 'scan',
            'body'   => array("query"  => array("term" => array("store_id" => $storeId)), "fields" => array())
        );

        $scroll = $this->getClient()->search($scrollQuery);
        $indexDocumentCount = 0;

        if ($scroll['_scroll_id'] && $scroll['hits']['total'] > 0) {
            $scroller = array('scroll' => '5m', 'scroll_id' => $scroll['_scroll_id']);
            while ($indexDocumentCount < $scroll['hits']['total']) {
                $entityIds = array();
                $data = $this->getClient()->scroll($scroller);
                $indexDocumentCount += $data['hits']['total'];
                foreach ($data['hits']['hits'] as $currentDoc) {
                    $entityIds[] = current(explode('|', $currentDoc['_id']));
                }
                if (!empty($entityIds)) {
                    $this->_updateEntities($storeId, $entityIds);
                }
            }
        }
    }

    protected function _updateEntities($storeId, $entityIds)
    {
        $updateData = $this->getEntitiesData($storeId, $entityIds);
        $bulk = array();
        foreach ($updateData as $entityId => $data) {
            $documentId = sprintf("%s|%s", $entityId, $storeId);
            $update = $this->getCurrentIndex()->updateDocument($documentId, $data ,$this->_mapping->getType());
            $bulk = array_merge($bulk, $update);
        }

        $this->getCurrentIndex()->executeBulk($bulk);

        return $this;
    }

    abstract public function getEntitiesData($storeId, $entityIds);
}