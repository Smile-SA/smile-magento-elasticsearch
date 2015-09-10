<?php
/**
 * Smile_ElasticSearch custom indexer for search.
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
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Indexer_Fulltext extends Mage_CatalogSearch_Model_Indexer_Fulltext
{
    /**
     * (Dummy) Register indexer event
     *
     * @param Mage_Index_Model_Event $event Indexer event
     *
     * @return Mage_CatalogSearch_Model_Indexer_Fulltext
     */
    public function register(Mage_Index_Model_Event $event)
    {
        $helper = Mage::helper('smile_elasticsearch');
        if ($helper->isEnterpriseSupportEnabled() == true) {
            return $this;
        }
        return parent::register($event);
    }

    /**
     * (Dummy) Process event
     *
     * @param Mage_Index_Model_Event $event Indexer event
     *
     * @return Mage_Index_Model_Indexer_Abstract
     */
    public function processEvent(Mage_Index_Model_Event $event)
    {
        $helper = Mage::helper('smile_elasticsearch');
        if ($helper->isEnterpriseSupportEnabled() == true) {
            return $this;
        }
        return parent::processEvent($event);
    }

    /**
     * (Dummy) Check if event can be matched by process
     *
     * @param Mage_Index_Model_Event $event Indexer event
     *
     * @return bool
     */
    public function matchEvent(Mage_Index_Model_Event $event)
    {
        $helper = Mage::helper('smile_elasticsearch');
        if ($helper->isEnterpriseSupportEnabled() == true) {
            return false;
        }
        return parent::matchEvent($event);
    }


    /**
     * Process event
     *
     * @param Mage_Index_Model_Event $event Event to be indexed.
     *
     * @return void
     */
    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();

        if (!empty($data['catalogsearch_fulltext_reindex_all'])) {
            $this->reindexAll();
        } else if (!empty($data['catalogsearch_delete_product_id'])) {
            $productId = $data['catalogsearch_delete_product_id'];

            if (!$this->_isProductComposite($productId)) {
                $parentIds = $this->_getResource()->getRelationsByChild($productId);
                if (!empty($parentIds)) {
                    $this->_getMapping('product')->rebuildIndex(null, $parentIds);
                }
            }

            $this->_getIndexer()
                ->cleanIndex(null, $productId)
                ->resetSearchResults();

        } else if (!empty($data['catalogsearch_update_product_id'])) {
            $productId = $data['catalogsearch_update_product_id'];
            $productIds = array($productId);

            if (!$this->_isProductComposite($productId)) {
                $parentIds = $this->_getResource()->getRelationsByChild($productId);
                if (!empty($parentIds)) {
                    $productIds = array_merge($productIds, $parentIds);
                }
            }
            $this->_getIndexer()->cleanIndex(null, $productIds);
            $this->_getMapping('product')->rebuildIndex(null, $productIds);

            $this->_getIndexer()->resetSearchResults();

        } else if (!empty($data['catalogsearch_product_ids'])) {
            // mass action
            $productIds = $data['catalogsearch_product_ids'];
            $parentIds = $this->_getResource()->getRelationsByChild($productIds);
            if (!empty($parentIds)) {
                $productIds = array_merge($productIds, $parentIds);
            }

            if (!empty($data['catalogsearch_website_ids'])) {
                $websiteIds = $data['catalogsearch_website_ids'];
                $actionType = $data['catalogsearch_action_type'];

                foreach ($websiteIds as $websiteId) {
                    foreach (Mage::app()->getWebsite($websiteId)->getStoreIds() as $storeId) {
                        if ($actionType == 'remove') {
                            $this->_getIndexer()
                                ->cleanIndex($storeId, $productIds)
                                ->resetSearchResults();
                        } else if ($actionType == 'add') {
                            $this->_getMapping('product')->rebuildIndex($storeId, $productIds);
                            $this->_getIndexer()->resetSearchResults();
                        }
                    }
                }
            }
            if (isset($data['catalogsearch_status'])) {
                $status = $data['catalogsearch_status'];
                if ($status == Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                    $this->_getMapping('product')->rebuildIndex(null, $productIds);
                    $this->_getIndexer()->resetSearchResults();
                } else {
                    $this->_getIndexer()->cleanIndex(null, $productIds);
                    $this->_getMapping('product')->rebuildIndex(null, $productIds);
                    $this->_getIndexer()->resetSearchResults();
                }
            }
            if (isset($data['catalogsearch_force_reindex'])) {
                $this->_getIndexer()->cleanIndex(null, $productIds);
                $this->_getMapping('product')->rebuildIndex(null, $productIds);
                $this->_getIndexer()->resetSearchResults();
            }
        } else if (isset($data['catalogsearch_category_update_product_ids'])) {
            $productIds = $data['catalogsearch_category_update_product_ids'];
            $categoryIds = $data['catalogsearch_category_update_category_ids'];
            $this->_getMapping('category')->rebuildIndex(null, $categoryIds);
        }
    }

    /**
     * Return a mapping used to index entities.
     *
     * @param string $type Retrieve mapping for a type (product, category, ...).
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    protected function _getMapping($type)
    {
        $index = $this->getCurrentIndex();
        return $index->getMapping($type);
    }

    /**
     * Return the current index where to put new documents.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index
     */
    public function getCurrentIndex()
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        return $engine->getCurrentIndex();
    }

    /**
     * Rebuild all index data
     *
     * @return void
     */
    public function reindexAll()
    {
        $index = $this->getCurrentIndex();

        $index->prepareNewIndex();
        foreach ($index->getAllMappings() as $mapping) {
            $mapping->rebuildIndex();
        }
        $index->installNewIndex();
    }
}
