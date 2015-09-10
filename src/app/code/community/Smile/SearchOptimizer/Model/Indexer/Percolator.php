<?php
/**
 * Register optimizers into the search index as percolators
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
class Smile_SearchOptimizer_Model_Indexer_Percolator extends Mage_Index_Model_Indexer_Abstract
{

    /**
     * Index math: product save, category save, store save
     * store group save, config save
     *
     * @var array
     */
    protected $_matchedEntities = array(
        Smile_SearchOptimizer_Model_Optimizer::ENTITY => array(
            Mage_Index_Model_Event::TYPE_SAVE,
            Mage_Index_Model_Event::TYPE_DELETE,
        )
    );

    /**
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    protected $_engine;

    /**
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index
     */
    protected $_index;

    /**
     * Init the search engine and the index.
     *
     * @return void
     */
    public function __construct()
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch) {
            $this->_engine = $engine;
            $this->_index =   $engine->getCurrentIndex();
        }

        parent::__construct();
    }

    /**
     * Return true if the ES engine is active.
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->_index !== null;
    }

    /**
     * Retrieve Indexer name
     *
     * @return string
     */
    public function getName()
    {
        return Mage::helper('smile_searchoptimizer')->__('Search Optimizers Analysis');
    }

    /**
     * Retrieve Indexer name
     *
     * @return string
     */
    public function getDescription()
    {
        return Mage::helper('smile_searchoptimizer')->__('Compute optimizer rules and index them as percolator for analysis.');
    }

    /**
     * Register data required by process in event object
     *
     * @param Mage_Index_Model_Event $event Indexing event.
     *
     * @return void
     */
    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        return;
    }

    /**
     * Process event based on event state data
     *
     * @param Mage_Index_Model_Event $event Indexing event.
     *
     * @return void
     */
    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        $optimizer = $event->getDataObject();
        if ($event->getType() == Mage_Index_Model_Event::TYPE_SAVE) {
            $this->reindex($optimizer);
        } else if ($event->getType() == Mage_Index_Model_Event::TYPE_DELETE) {
            $bulk = array();
            foreach (Mage::app()->getStores() as $store) {
                $docId = sprintf('search_optimizer_%s_%s', $optimizer->getId(), $store->getId());
                $bulk['body'][] = array(
                    'delete' => array('_index' => $this->_index->getCurrentName(), '_type' => '.percolator', '_id' => $docId)
                );
            }
            Mage::log($bulk);
            $this->_engine->getClient()->bulk($bulk);
            $this->_index->refresh();
        }
    }

    /**
     * Reindex everything.
     *
     * @return void
     */
    public function reindexAll()
    {
        if ($this->isEnabled()) {
            $docs = array();
            $optimizers = Mage::getResourceModel('smile_searchoptimizer/optimizer_collection');
            foreach ($optimizers as $optimizer) {
                $docs = array_merge($docs, $this->_getOptimizerPercolator($optimizer));
            }
            $this->_index->addDocuments($docs)
                ->refresh();
        }
    }

    /**
     * Reindex a single optimizer.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer The optimizer.
     *
     * @return void
     */
    public function reindex($optimizer)
    {
        if ($this->isEnabled()) {
            $docs = $this->_getOptimizerPercolator($optimizer);
            $this->_index->addDocuments($docs)
                ->refresh();
        }
    }

    /**
     * Generate a bulk indexing query for a given optimizer.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer The optimizer.
     *
     * @return array
     */
    protected function _getOptimizerPercolator($optimizer)
    {
        $docs = array();
        foreach (Mage::app()->getStores() as $store) {
            $optimizer->getFilterRule()->setStore($store);
            $percolatorQuery = array('match_all' => array());

            $filter = $optimizer->getFilterRuleSearchQuery();
            if ($filter != false) {
                $percolatorQuery = array('query_string' => array('query' => $filter));
            }
            $data = array(
                'query'           => $percolatorQuery,
                'type'            => 'product',
                'percolator_type' => 'search_optimizer',
                'optimizer_id'    => $optimizer->getId(),
                'store_id'        => $store->getId()
            );

            $docId = $data['percolator_type'] . '_' . $data['optimizer_id'] . '_' . $data['store_id'];
            $docs = array_merge($docs, $this->_index->createDocument($docId, $data, '.percolator'));
        }

        return $docs;
    }
}