<?php
/**
 * Optimizer model implementation
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
 * @package   Smile_SearchOptimizer
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2014 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Model_Percolator
{
    /**
     * The current index.
     *
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index
     */
    protected $_index;

    /**
     * ES Client.
     *
     * @var \Elasticsearch\Client
     */
    protected $_client;

    /**
     * Init the percolator.
     *
     * @return void
     */
    public function __construct()
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        $this->_index  = $engine->getCurrentIndex();
        $this->_client = $engine->getClient();
    }

    /**
     * Return a list of the optimizers with for each of them the field optimized_score
     * set to the score of the query. You can access it with ::getOptimizedScore().
     *
     * The field is not set if the optimizer is not applied.
     *
     * @param int $productId The product id.
     * @param int $storeId   The store id.
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer_Collection
     */
    public function analyzeOptmizers($productId, $storeId)
    {
        Varien_Profiler::start('Boost analysis');
        $docId = $productId . '|' . $storeId;
        $indexName = $this->_index->getCurrentName();
        $enabledOptimizers = $this->_getEnabledOptmizerIds($productId, $storeId);

        $optimizers = Mage::getResourceModel('smile_searchoptimizer/optimizer_collection')
            ->addIsActiveFilter()
            ->addStoreFilter($storeId)
            ->setOrder('name', Varien_Data_Collection::SORT_ORDER_ASC);

        foreach ($optimizers as $optimizer) {
            $optimizer->getFilterRule()->setStoreId($storeId);
            if (in_array($optimizer->getId(), $enabledOptimizers)) {
                $optimizedQuery = array(
                    'index' => $indexName,
                    'type' => 'product',
                    'body' => array(
                        'query' => array('ids' => array('values' => array($docId)))
                    )
                );
                $optimizedQuery = $optimizer->applyOptimizer($optimizedQuery);
                try {
                    $scoringResponse = $this->_client->search($optimizedQuery);
                    if ($scoringResponse['hits']['max_score'] !== null && $scoringResponse['hits']['max_score'] != 1) {
                        $optimizer->setOptimizedScore($scoringResponse['hits']['max_score']);
                    }

                } catch(Exception $e) {
                    Mage::logException($e);
                }
            }
        }
        Varien_Profiler::stop('Boost analysis');

        return $optimizers;
    }

    /**
     * Use the percolation to detect the optimizer that applies to the product.
     * Allow to execute fewer scoring queries in the analysis phase.
     *
     * @param int $productId The product id.
     * @param int $storeId   The store id.
     *
     * @return array
     */
    protected function _getEnabledOptmizerIds($productId, $storeId)
    {
        $docId = $productId . '|' . $storeId;
        $optimizers = array();
        $indexName = $this->_index->getCurrentName();
        try {
            $matches = $this->_client->percolate(
                array(
                    'index' => $indexName,
                    'type'  => 'product',
                    'id'    => $docId,
                    'body'  => array(
                        "filter" => array(
                            'and' => array(
                                array('term' => array("store_id" => $storeId)),
                                array('term' => array('percolator_type' => 'search_optimizer'))
                            )
                        ),
                        'percolate_format' => 'ids'
                    )
                )
            );

            foreach ($matches['matches'] as $match) {
                $percolationData = $this->_client->get(
                    array('index' => $indexName, 'type' => '.percolator', 'id' => $match['_id'])
                );
                $optimizers[] = (string) $percolationData['_source']['optimizer_id'];
            }
        } catch(\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            // We should fail silently in this case
            // The product may not be indexed for a legit reason (e.g. its' visibility is set to "Not visible individually")
            ;
        }

        return $optimizers;
    }

}