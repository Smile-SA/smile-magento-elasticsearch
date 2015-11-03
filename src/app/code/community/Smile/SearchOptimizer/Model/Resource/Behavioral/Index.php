<?php
/**
 * Dedicated indexer for behavioral data
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
class Smile_SearchOptimizer_Model_Resource_Behavioral_Index
{
    /**
     * This function update current document index with data coming from the behavioral index
     *
     * @return Smile_SearchOptimizer_Model_Resource_Behavioral_Index self reference
     */
    public function copyBehavioralData()
    {
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

                $scrollQuery = array(
                    'index'       => (string) $recommenderIndex,
                    'type'        => "event",
                    'size'        => Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index::COPY_DATA_BULK_SIZE,
                    'scroll'      => '5m',
                    'search_type' => 'scan',
                    'body'   => array(
                        "query" => array(
                            "term" => array(
                                "eventType" => "product"
                            )
                        ),
                        "fields" => $fields
                    )
                );

                $scroll = $engine->getClient()->search($scrollQuery);
                $indexDocumentCount = 0;

                if ($scroll['_scroll_id'] && $scroll['hits']['total'] > 0) {
                    $scroller = array('scroll' => '5m', 'scroll_id' => $scroll['_scroll_id']);
                    while ($indexDocumentCount <= $scroll['hits']['total']) {
                        $docs = array();
                        $data = $engine->getClient()->scroll($scroller);

                        foreach ($data['hits']['hits'] as $item) {

                            $documentId = $this->_prepareDocumentId($item['fields']);

                            if ($documentId) {
                                $updateData = $this->_prepareBehavioralData($item['fields']);
                                $docs = array_merge(
                                    $docs,
                                    $engine->getCurrentIndex()->updateDocument($documentId, $updateData, 'product')
                                );
                            }
                        }
                        //print_r($docs);
                        $engine->getCurrentIndex()->executeBulk($docs);
                        $indexDocumentCount =
                            $indexDocumentCount +
                            Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index::COPY_DATA_BULK_SIZE;
                    }
                }
            }
        }

        return $this;
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
     * Build the document Id to update based on item fields :
     *     we do not have the document Id but we have entityId and storeId on index to rebuild it
     *
     * @param array $fields The item fields
     *
     * @return string
     */
    protected function _prepareDocumentId($fields)
    {
        $documentId = false;

        if (isset($fields["event.eventStoreId"]) && isset($fields["event.eventEntity"])) {
            // @TODO These fields are array, better testing/grabbing of data needed here
            if (isset($fields["event.eventStoreId"][0]) && isset($fields["event.eventEntity"][0])) {
                $documentId = $fields["event.eventEntity"][0] . "|" . $fields["event.eventStoreId"][0];
            }
        }

        return $documentId;
    }
}