<?php
/**
 * Category autocomplete block implementation.
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
class Smile_ElasticSearch_Block_Catalogsearch_Autocomplete_Suggest_Category extends Mage_Core_Block_Template
{
    /**
     * Block cache key
     *
     * @return string
     */
    public function getCacheKey()
    {
        return __CLASS__ . md5($this->_getQuery()) . '_' . Mage::app()->getStore()->getId();
    }

    /**
     * Block cache lifetime
     *
     * @return int
     */
    public function getCacheLifetime()
    {
        return Mage_Core_Model_Cache::DEFAULT_LIFETIME;
    }

    /**
     * Block cache tags
     *
     * @return array
     */
    public function getCacheTags()
    {
        return array(Mage_Catalog_Model_Category::CACHE_TAG);
    }

    /**
     * Check if the block is active or not. Block is disabled if :
     * - ES is not the selected engine into Magento
     *
     * @return bool
     */
    public function isActive()
    {
        return Mage::helper('smile_elasticsearch')->isActiveEngine() && $this->getMaxSize() > 0;
    }

    /**
     * Return the list of all suggested products
     *
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    public function getCategoryCollection()
    {
        $maxSize = $this->getMaxSize();

        $collection = Mage::getResourceModel('smile_elasticsearch/catalog_category_suggest_collection')
            ->setEngine(Mage::helper('catalogsearch')->getEngine())
            ->setStoreId(Mage::app()->getStore()->getId())
            ->setPageSize($maxSize)
            ->addAttributeToSelect('name')
            ->setOrder('level', Varien_Data_Collection::SORT_ORDER_ASC)
            ->addSearchFilter($this->_getQuery())
            ->addUrlRewriteToResult();

        return $collection;
    }

    /**
     * Get number of suggestion to display
     *
     * @return int
     */
    public function getMaxSize()
    {
        return Mage::getStoreConfig('elasticsearch_advanced_search_settings/category_autocomplete/max_size');
    }

    /**
     * Return the string query we want to retrive suggests for
     *
     * @return string
     */
    protected function _getQuery()
    {
        return Mage::app()->getRequest()->getParam('q', false);
    }
}
