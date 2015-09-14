<?php
/**
 * Optimizer edit preview Ajax
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Boost_Preview extends Mage_Core_Block_Template
{

    /**
     * @var int
     */
    const PREVIEW_SIZE = 20;

    /**
     * Init the block collecttions.
     *
     * @return void
     */
    public function _construct()
    {
        $this->_initProductCollection();
        parent::_construct();
    }

    /**
     * Init product collections for the block.
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Boost_Preview
     */
    protected function _initProductCollection()
    {
        $client = Mage::helper('catalogsearch')->getEngine()->getClient();
        $baseQuery      = $this->_getBaseSearchQuery();
        $optimizedQuery = $this->_getOptimizedQuery($baseQuery);
        Mage::log(json_encode($optimizedQuery));
        $baseProductIds = $this->_getProductIdsFromSearchQuery($baseQuery);
        $optimizeProductIds = $this->_getProductIdsFromSearchQuery($optimizedQuery);

        $this->setBaseProductIds($baseProductIds);
        $this->setOptimizedProductIds($optimizeProductIds);

        $allIds = array_merge($baseProductIds, $optimizeProductIds);

        if (empty($allIds)) {
            $allIds = array(0);
        }

        $attributes = Mage::getModel('catalog/config')->getProductCollectionAttributes();
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId($this->getStoreId())
            ->addIdFilter($allIds)
            ->addAttributeToSelect($attributes)
            ->load();

        $this->setProductCollection($collection);

        return $this;
    }

    /**
     * Get the preview max size
     *
     * @return int
     */
    public function getMaxSize()
    {
        return self::PREVIEW_SIZE;
    }

    /**
     * Load product ids for the query
     *
     * @param array $query The query to optimize.
     *
     * @return array
     */
    private function _getProductIdsFromSearchQuery($query)
    {
        $ids = array();
        $client = Mage::helper('catalogsearch')->getEngine()->getClient();
        $response = $client->search($query);
        foreach ($response['hits']['hits'] as $hit) {
            $currentId = $hit['fields']['entity_id'];
            if (is_array($currentId)) {
                $currentId = current($currentId);
            }
            $ids[] = (int) $currentId;
        }
        return $ids;
    }

    /**
     * Get the ES query to be opimized.
     *
     * @return array
     */
    private function _getBaseSearchQuery()
    {
        $store = Mage::app()->getStore($this->getStoreId());
        $collection = Mage::helper('catalogsearch')
            ->getEngine()
            ->getResultCollection()
            ->addSearchFilter($this->getFulltextQuery());

        $allowedVisibilities = Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
        $allowedStatuses = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();

        $query = $collection->getSearchEngineQuery()
            ->addFilter('terms', array('store_id' => $this->getStoreId()))
            ->addFilter('terms', array('visibility' => $allowedVisibilities))
            ->addFilter('terms', array('status' => $allowedStatuses))
            ->setLanguageCode(Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store))
            ->setPageParams(0, self::PREVIEW_SIZE)
            ->getRawQuery();

        return $this->_applyOptimizers($query);
    }

    /**
     * Append current optimizer to the query.
     *
     * @param array $query Query to optimize.
     *
     * @return array
     */
    private function _getOptimizedQuery($query)
    {
        return $this->getCurrentOptimizer()->applyOptimizer($query);
    }

    /**
     * Apply all optimizers but the current one to the query.
     *
     * @param array $query Query to optimize.
     *
     * @return array
     */
    private function _applyOptimizers($query)
    {
        $optimizers = Mage::getResourceModel('smile_searchoptimizer/optimizer_collection')
            ->addIsActiveFilter()
            ->addStoreFilter($this->getStoreId());

        if ($this->getCurrentOptimizer()) {
            $optimizers->addFieldToFilter('main_table.optimizer_id', array('neq' => $this->getCurrentOptimizer()->getId()));
        }

        foreach ($optimizers as $optimizer) {
            $optimizer->getFilterRule()->setStoreId($this->getStoreId());
            $query = $optimizer->applyOptimizer($query);
        }
        return $query;
    }

    /**
     * Get the fulltext query string.
     *
     * @return string
     */
    public function getFulltextQuery()
    {
        return $this->getRequest()->getParam('query');
    }

    /**
     * Get the current store.
     *
     * @return id
     */
    public function getStoreId()
    {
        return $this->getRequest()->getParam('store_id');
    }


    /**
     * Get the current optimizer.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer
     */
    public function getCurrentOptimizer()
    {
        return Mage::registry('current_optimizer');
    }

    /**
     * Get loaded product by id.
     *
     * @param int $productId Product Id to get.
     *
     * @return Mage_Catalog_Model_Produtct
     */
    public function getProductById($productId)
    {
        $collection = $this->getProductCollection();
        return $collection->getItemById($productId);
    }

    /**
     * Indicates if the product position have moved after optimization.
     *
     * @param int $productId The current product.
     *
     * @return int
     */
    public function getEffectOnProduct($productId)
    {
        $baseProductIds      = $this->getBaseProductIds();
        $optimizedProductIds = $this->getOptimizedProductIds();
        $result = 0;
        $baseProductPosition = array_search($productId, $baseProductIds);
        $optimizedProductPosition = array_search($productId, $optimizedProductIds);

        if ($baseProductPosition === false && $optimizedProductPosition !== false) {
            $result = 1;
        } else if ($baseProductPosition !== false && $optimizedProductPosition === false) {
            $result = -1;
        } else if ($baseProductPosition !== false && $optimizedProductPosition !== false) {
            if ($baseProductPosition > $optimizedProductPosition) {
                $result = 1;
            }
            if ($baseProductPosition < $optimizedProductPosition) {
                $result = -1;
            }
        }

        return $result;
    }
}