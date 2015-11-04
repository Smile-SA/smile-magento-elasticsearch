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

        $recommenderIndex = Mage::getStoreConfig("elasticsearch_advanced_search_settings/behavioral_optimizers/recommender_index");

        $fields = array(
            "event.eventEntity",
            "event.actionType",
            "event.eventStoreId",
            "popularity"
        );

        if ($recommenderIndex !== null) {
            /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $engine */
            $engine = Mage::helper('catalogsearch')->getEngine();
            if ($engine->getClient()->indices()->exists(array('index' => (string) $recommenderIndex))) {

                // @TODO maybe request all products at once ?
                foreach ($entityIds as $entityId) {

                    $query = array(
                        'index' => (string) $recommenderIndex,
                        'body'  => array(
                            "query" => array(
                                "bool" => array(
                                    "must" => array(
                                        array(
                                            "term" => array(
                                                "event.eventType" => "product"
                                            )
                                        ),
                                        array(
                                            "term" => array(
                                                "event.eventStoreId" => $storeId
                                            )
                                        ),
                                        array(
                                            "term" => array(
                                                "event.eventEntity" => $entityId
                                            )
                                        )
                                    )
                                )
                            ),
                            "fields" => $fields
                        )
                    );

                    $data = $engine->getClient()->search($query);

                    if (isset($data['hits']) && ($data['hits']['total'] > 0)) {
                        foreach ($data['hits']['hits'] as $item) {
                            $updateData = $this->_prepareBehavioralData($item['fields']);
                            if (!empty($updateData)) {
                                $result[$entityId] = $updateData;
                            }
                        }
                    }
                }
            }

            return $result;
        }
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
    public function getMapping()
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