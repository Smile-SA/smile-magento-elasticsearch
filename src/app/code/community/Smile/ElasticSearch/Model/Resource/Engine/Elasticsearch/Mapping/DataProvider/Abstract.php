<?php
/**
 * Abstract data provider : this class is meant to be used as an external (or internal) data provider for search index
 * This permits to update search index with external data (such as ratings, page view, etc ...) coming from elsewhere
 *
 * This class currently supports :
 * - differential index
 * - full index
 * - "partial full" index : full index of the fields added by this provider only
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * This work is a fork of Johann Reinke <johann@bubblecode.net> previous module
 * available at https://github.com/jreinke/magento-elasticsearch
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Abstract
{
    /**
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract $mapping The mapping
     */
    private $_mapping;

    /**
     * Set the mapping of the data provider
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract $mapping The mapping
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Abstract self reference
     */
    public function setMapping($mapping)
    {
        $this->_mapping = $mapping;

        return $this;
    }

    /**
     * Run the index update, for dedicated entities and store or for the whole catalog
     *
     * @param int|null   $storeId   The store Id to process index for
     * @param array|null $entityIds The entity ids being reindexed
     *
     * @return $this
     */
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

    /**
     * Retrieve current index
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index
     */
    public function getCurrentIndex()
    {
        return $this->_mapping->getCurrentIndex();
    }

    /**
     * Retrieve Elastic search client
     *
     * @return \Elasticsearch\Client
     */
    public function getClient()
    {
        return $this->getCurrentIndex()->getClient();
    }

    /**
     * Update all entities of a given store Id
     *
     * @param int $storeId The store Id
     *
     * @return void Nothing
     */
    protected function _updateStoreEntities($storeId)
    {
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

    /**
     * Update a given list of entity for a store
     * This method only update the fields concerned by this data provider
     *
     * @param int   $storeId   The store Id
     * @param array $entityIds The entity ids
     *
     * @return $this
     */
    protected function _updateEntities($storeId, $entityIds)
    {
        $updateData = $this->getEntitiesData($storeId, $entityIds);
        $bulk = array();
        foreach ($updateData as $entityId => $data) {
            $documentId = sprintf("%s|%s", $entityId, $storeId);
            $update = $this->getCurrentIndex()->updateDocument($documentId, $data, $this->_mapping->getType());
            $bulk = array_merge($bulk, $update);
        }

        $this->getCurrentIndex()->executeBulk($bulk);

        return $this;
    }

    /**
     * This method should be implemetend by descendents
     * and will return the specific data related to this data provider for the given products/store
     *
     * @param int   $storeId   The store id
     * @param array $entityIds The list of entity Ids
     *
     * @return mixed
     */
    abstract public function getEntitiesData($storeId, $entityIds);

    /**
     * This method should be implemetend by descendents
     * and will return the specific mapping related to this data provider
     *
     * @return array
     */
    abstract public function getMappingProperties();
}