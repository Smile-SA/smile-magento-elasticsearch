<?php
class Smile_ElasticSearch_Model_Indexer_Fulltext extends Mage_CatalogSearch_Model_Indexer_Fulltext
{
    /**
     * Process event
     *
     * @param Mage_Index_Model_Event $event
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

            $this->_getIndexer()->cleanIndex(null, $productId)
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
                    $this->_getIndexer()
                    ->rebuildIndex(null, $productIds)
                    ->resetSearchResults();
                } else {
                    $this->_getIndexer()->cleanIndex(null, $productIds);
                    $this->_getMapping('product')->resetSearchResults();
                }
            }
            if (isset($data['catalogsearch_force_reindex'])) {
                $this->_getMapping('product')->rebuildIndex(null, $productIds);
                $this->_getIndexer()->resetSearchResults();
            }
        } else if (isset($data['catalogsearch_category_update_product_ids'])) {
            $productIds = $data['catalogsearch_category_update_product_ids'];
            $categoryIds = $data['catalogsearch_category_update_category_ids'];

            $this->_getMapping('category')->rebuildIndex(null, $categoryIds);
        }
    }

    public function _getMapping($type)
    {
        $index = $this->getCurrentIndex();
        return $index->getMapping($type);
    }


    public function getCurrentIndex()
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        return $engine->getCurrentIndex();
    }

    /**
     * Rebuild all index data
     *
     */
    public function reindexAll()
    {

        $index = $this->getCurrentIndex();

        $index->prepareNewIndex();
        foreach($index->getAllMappings() as $mapping) {
            $mapping->rebuildIndex();
        }
        $index->installNewIndex();
    }
}
