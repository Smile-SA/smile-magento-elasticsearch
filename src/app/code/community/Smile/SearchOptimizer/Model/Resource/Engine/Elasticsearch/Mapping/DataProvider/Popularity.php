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

        $recommenderIndex = $this->_getRecommenderIndex();

        if ($recommenderIndex !== null) {
            /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine */
            $engine = Mage::helper('catalogsearch')->getEngine();
            if ($engine->getClient()->indices()->exists(array('index' => (string) $recommenderIndex))) {

                $query = $this->_getPopularityEventQuery($storeId, $entityIds);
                $data  = $engine->getClient()->search($query);

                if (isset($data['hits']) && ($data['hits']['total'] > 0)) {
                    foreach ($data['hits']['hits'] as $item) {
                        $updateData = $this->_prepareBehavioralData($item['fields']);
                        if (!empty($updateData) && (isset($item['fields']['event.eventEntity']))) {
                            $result[array_shift($item['fields']['event.eventEntity'])] = $updateData;
                        }
                    }
                }

            }
            return $result;
        }
    }

    /**
     * Retrieve recommendation index name
     *
     * @TODO : better method handling permutation of index
     *
     * @return mixed
     */
    protected function _getRecommenderIndex()
    {
        return Mage::getStoreConfig("elasticsearch_advanced_search_settings/behavioral_optimizers/recommender_index");
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
        $recommenderIndex = $this->_getRecommenderIndex();

        $fields = array(
            "event.eventEntity",
            "event.actionType",
            "event.eventStoreId",
            "popularity"
        );

        $query = array('index' => (string) $recommenderIndex);

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
     * Prepare behavioral data to insert on product index, based on data coming from recommendation index
     *
     * @param array $fields The item fields
     *
     * @return array
     */
    protected function _prepareBehavioralData($fields)
    {
        $data = array();

        if (isset($fields["event.actionType"]) && isset($fields["popularity"])) {
            // @TODO These fields are array, better testing/grabbing of data needed here
            if (isset($fields["event.actionType"][0]) && isset($fields["popularity"][0])) {
                if ($fields["event.actionType"][0] == "view") {
                    $data["_optimizer_view_count"] = $fields["popularity"][0];
                } elseif ($fields["event.actionType"][0] == "buy") {
                    $data["_optimizer_sale_count"] = $fields["popularity"][0];
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
}