<?php
/**
 * Popularity data provider, this will retrieve popularity data from a dedicated ES index
 * to have this data being added to the current ES search index
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_SearchOptimizer
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Popularity
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Abstract
{
    /**
     * The chunk size to build aggregation on
     */
    const CHUNK_SIZE = 10;

    /**
     * Retrieve popularity data for entities
     *
     * @param int   $storeId   The store id
     * @param array $entityIds The entity ids
     *
     * @return array
     */
    public function getEntitiesData($storeId, $entityIds)
    {
        $result          = array();
        $popularityIndex = $this->_getPopularityIndex();
        $engine          = Mage::helper('catalogsearch')->getEngine();

        if (($popularityIndex !== null) && ($engine->getClient()->indices()->exists(array('index' => (string) $popularityIndex)))) {

            $offset = 0;
            while ($offset < count($entityIds)) {

                $productIds = array_slice($entityIds, $offset, self::CHUNK_SIZE, true);
                $offset     = $offset + self::CHUNK_SIZE;
                $productIds = $this->_skuFromEntityId($productIds);

                $query = $this->_getPopularityEventQuery(array_values($productIds));
                $data  = $engine->getClient()->search($query);

                if (isset($data['aggregations']) && (isset($data['aggregations']['by_event_type']))) {
                    foreach ($data['aggregations']['by_event_type']['buckets'] as $item) {
                        $updateData = $this->_prepareBehavioralData($item);
                        foreach ($updateData as $sku => $productData) {
                            $entityId = array_search($sku, $productIds);
                            if (!isset($result[$entityId])) {
                                $result[$entityId] = array();
                            }
                            $result[$entityId] = array_merge((array) $result[$entityId], $productData);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Retrieve Popularity index alias
     *
     * @return mixed
     */
    protected function _getPopularityIndex()
    {
        return Mage::helper("smile_searchoptimizer")->getPopularityIndex();
    }

    /**
     * Build the query to retrieve event popularity for given entity Ids
     *
     * @param array $entityIds The entity ids
     *
     * @return array
     */
    protected function _getPopularityEventQuery($entityIds)
    {
        $popularityIndex = $this->_getPopularityIndex();

        $query = array('index' => (string) $popularityIndex);

        $query['body']['query']['bool']['must'] = array(
            array('term' => array('entity_type' => 'product')),
            array('terms' => array('entity_id'  => $entityIds))
        );

        $query['body']['aggregations']['by_event_type'] = array(
            'terms' => array(
                'field' => 'event_type',
                'size'  => 0
            ),
            'aggregations' => array(
                "by_sku" => array(
                    "terms" => array("field" => "entity_id")
                )
            )
        );

        return $query;
    }

    /**
     * Prepare behavioral data to insert on product index, based on data coming from popularity index
     *
     * @param array $item The item fields from aggregation
     *
     * @return array
     */
    protected function _prepareBehavioralData($item)
    {
        $data = array();

        if (isset($item["key"])) {
            $actionType = $item["key"];
            if (isset($item["by_sku"])) {
                foreach ($item["by_sku"]["buckets"] as $bucket) {

                    $popularity = $bucket["doc_count"];
                    $sku        = $bucket["key"];

                    if ($actionType == "view") {
                        $data[$sku]["_optimizer_view_count"] = $popularity;
                    } elseif ($actionType == "buy") {
                        $data[$sku]["_optimizer_sale_count"] = $popularity;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Return custom mapping for data added by this provider
     *
     * @return array
     */
    public function getMappingProperties()
    {
        $mapping = array(
            "properties" => array(
                "_optimizer_sale_count" => array('type' => 'long', 'doc_values' => true),
                "_optimizer_view_count" => array('type' => 'long', 'doc_values' => true)
            )
        );

        return $mapping;
    }

    /**
     * Return the current real name of the popularity index
     *
     * @return string
     */
    public function getCurrentPopularityIndex()
    {
        /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine */
        $engine  = Mage::helper('catalogsearch')->getEngine();
        $indices = $engine->getClient()->indices();
        $alias   = Mage::helper("smile_searchoptimizer")->getPopularityIndex();

        $currentIndexes = array();

        $allIndices = $indices->getMapping(array('index'=> $alias));

        foreach (array_keys($allIndices) as $index) {
            $currentIndexes[] = $index;
        }

        return end($currentIndexes);
    }

    /**
     * Update only data that has changed on the index
     *
     * @param Zend_Date $date the last changed version date
     *
     * @return void Nothing
     */
    public function updateChangeLog($date)
    {
        $entityIds = $this->_getUpdatedEntityIds($date);
        if (count($entityIds)) {
            $this->updateAllData(null, array_values($entityIds));
        }
    }

    /**
     * Retrieve all products ids that has changed on the popularity index
     *
     * @param Zend_Date $date the last changed version date
     *
     * @return array
     */
    protected function _getUpdatedEntityIds($date)
    {
        $result = array();

        $popularityIndex = $this->_getPopularityIndex();

        if ($popularityIndex !== null) {
            /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine */
            $engine = Mage::helper('catalogsearch')->getEngine();
            if ($engine->getClient()->indices()->exists(array('index' => (string) $popularityIndex))) {

                $query = $this->_getHasChangedQuery($date);
                $data  = $engine->getClient()->search($query);

                if (isset($data['aggregations']) && (isset($data['aggregations']['product_skus']))) {
                    foreach ($data['aggregations']['product_skus']['buckets'] as $item) {
                        $result[] = $item['key'];
                    }
                }
            }
        }

        $result = $this->_entityIdFromSku($result);

        return $result;
    }

    /**
     * Fetch product entity ids from their Skus
     *
     * @param array $skus the SKUs
     *
     * @return array The entity Ids
     */
    protected function _entityIdFromSku($skus)
    {
        $entityIds = array();

        if (count($skus)) {
            // Since entity_id is SKU on finedata index, we have to map products entity_id (The magento one)
            $readAdapter = Mage::getSingleton('core/resource')->getConnection("read");
            $select      = $readAdapter->select()
                ->from(Mage::getSingleton('core/resource')->getTableName("catalog/product"), array('entity_id', 'sku'))
                ->where("sku IN (?)", $skus);
            $data = $readAdapter->fetchAll($select);
            foreach ($data as $productData) {
                $entityIds[$productData["sku"]] = $productData["entity_id"];
            }
        }

        return $entityIds;
    }

    /**
     * Fetch product entity ids from their Skus
     *
     * @param array $entityIds the entity Ids
     *
     * @return array The entity Ids
     */
    protected function _skuFromEntityId($entityIds)
    {
        $skus = array();

        if (count($entityIds)) {
            $readAdapter = Mage::getSingleton('core/resource')->getConnection("read");
            $select      = $readAdapter->select()
                ->from(Mage::getSingleton('core/resource')->getTableName("catalog/product"), array('entity_id', 'sku'))
                ->where('entity_id IN (?)', array_map("intval", $entityIds));
            $data = $readAdapter->fetchAll($select);

            foreach ($data as $productData) {
                $skus[$productData["entity_id"]] = $productData["sku"];
            }
        }

        return $skus;
    }

    /**
     * Build the query to retrieve event popularity for given entity Ids
     *
     * @param Zend_Date $indexDateTime the last known index datetime
     *
     * @return array
     */
    protected function _getHasChangedQuery($indexDateTime)
    {
        $popularityIndex = $this->_getPopularityIndex();

        $fields = array("entity_id");

        $query = array('index' => (string) $popularityIndex);

        $query['body']['query']['bool']['must'] = array(
            array(
                'range' => array(
                    'event_date' => array(
                        "gte"    => $indexDateTime->toString(Varien_Date::DATETIME_INTERNAL_FORMAT),
                        "format" => Varien_Date::DATETIME_INTERNAL_FORMAT
                    )
                )
            ),
            array('term' => array('event.entity_type' => 'product'))
        );

        $query['body']['aggregations']['product_skus']['terms'] = array(
            'field' => 'entity_id',
            'size'  => 0
        );

        $query['body']['fields'] = $fields;

        return $query;
    }
}