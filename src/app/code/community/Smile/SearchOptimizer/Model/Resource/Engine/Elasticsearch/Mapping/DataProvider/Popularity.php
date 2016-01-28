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
     * Number of maximum matched per product : 2 because there is "view" and "buy"
     */
    const MAXIMUM_MATCHES_PER_PRODUCT = 2;

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
        $result = array();

        $popularityIndex = $this->_getPopularityIndex();

        if ($popularityIndex !== null) {
            /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine */
            $engine = Mage::helper('catalogsearch')->getEngine();
            if ($engine->getClient()->indices()->exists(array('index' => (string) $popularityIndex))) {

                $query = $this->_getPopularityEventQuery($storeId, $entityIds);
                $data  = $engine->getClient()->search($query);

                if (isset($data['hits']) && ($data['hits']['total'] > 0)) {
                    foreach ($data['hits']['hits'] as $item) {
                        $updateData = $this->_prepareBehavioralData($item['fields']);
                        if (!empty($updateData) && (isset($item['fields']['event.eventEntity']))) {
                            $result[current($item['fields']['event.eventEntity'])] = $updateData;
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
     * @param int   $storeId   The store id
     * @param array $entityIds The entity ids
     *
     * @return array
     */
    protected function _getPopularityEventQuery($storeId, $entityIds)
    {
        $popularityIndex = $this->_getPopularityIndex();

        $fields = array(
            "event.eventEntity",
            "event.actionType",
            "event.eventStoreId",
            "popularity"
        );

        $query = array('index' => (string) $popularityIndex);

        $query['size'] = count($entityIds) * self::MAXIMUM_MATCHES_PER_PRODUCT;

        $query['body']['query']['bool']['must'] = array(
            array('term' => array('event.eventType' => 'product')),
            array('term' => array('event.eventStoreId' => $storeId)),
            array('terms' => array('event.eventEntity' => $entityIds))
        );

        $query['body']['fields'] = $fields;

        return $query;
    }

    /**
     * Prepare behavioral data to insert on product index, based on data coming from popularity index
     *
     * @param array $fields The item fields
     *
     * @return array
     */
    protected function _prepareBehavioralData($fields)
    {
        $data = array();

        if (isset($fields["event.actionType"]) && isset($fields["popularity"])) {
            if (current($fields["event.actionType"]) && current($fields["popularity"])) {

                $popularity = current($fields["popularity"]);
                $actionType = current($fields["event.actionType"]);

                if ($actionType == "view") {
                    $data["_optimizer_view_count"] = $popularity;
                } elseif ($actionType == "buy") {
                    $data["_optimizer_sale_count"] = $popularity;
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
     * Return the horodated part of the index name
     *
     * @return mixed
     */
    public function getIndexDateTime()
    {
        $currentIndexName = $this->getCurrentPopularityIndex();
        $alias            = Mage::helper("smile_searchoptimizer")->getPopularityIndex();
        $indexDateTime    = preg_replace("/[^0-9]/", "", str_replace($alias, "", $currentIndexName));

        return $indexDateTime;
    }

    /**
     * Update only data that has changed on the index
     *
     * @param Zend_Date $date the last changed version date
     *
     * @return void Nothing
     */
    public function updateChangedData($date)
    {
        $entityIds = $this->_getUpdatedEntityIds($date);
        if (count($entityIds)) {
            $this->updateAllData(null, $entityIds);
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

                if (isset($data['hits']) && ($data['hits']['total'] > 0)) {
                    foreach ($data['hits']['hits'] as $item) {
                        $result[] = (int) current($item['fields']['event.eventEntity']);
                    }
                }
            }
        }

        return $result;
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

        $fields = array("event.eventEntity");

        $query = array('index' => (string) $popularityIndex);

        $query['body']['query']['bool']['must'] = array(
            array(
                'range' => array(
                    'event.updated_at' => array(
                        "gte" => $indexDateTime->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                    )
                )
            ),
        );

        $query['body']['fields'] = $fields;

        Mage::log(json_encode($query), null, "system.log", true);

        return $query;
    }
}