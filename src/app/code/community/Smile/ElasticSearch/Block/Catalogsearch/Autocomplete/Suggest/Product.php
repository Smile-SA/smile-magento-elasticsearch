<?php
/**
 * Product autocomplete block implementation.
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
class Smile_ElasticSearch_Block_Catalogsearch_Autocomplete_Suggest_Product extends Mage_Catalog_Block_Product_Abstract
{
    /**
     * Configuration path for attributes loaded during autocomplete.
     *
     * @var string
     */
    const AUTOCOMPLETE_ATTRIBUTES_XPATH = 'global/smile_elasticsearch/autocomplete/product/attributes';

    /**
     * Block cache key
     *
     * @return string
     */
    public function getCacheKey()
    {
        return __CLASS__ . md5($this->_getQuery() . $this->getTemplate()) . '_' . Mage::app()->getStore()->getId();
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
        return array(Mage_Catalog_Model_Product::CACHE_TAG);
    }

    /**
     * @var Smile_ElasticSearch_Model_Resource_Catalog_Product_Suggest_Collection
     */
    protected $_collection;

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
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    public function getProductCollection()
    {

        if ($this->_collection === null) {

            $attributes = array_keys(Mage::getConfig()->getNode(self::AUTOCOMPLETE_ATTRIBUTES_XPATH)->asArray());
            $maxSize = $this->getMaxSize();

            $collection = Mage::getResourceModel('smile_elasticsearch/catalog_product_suggest_collection')
                ->setEngine(Mage::helper('catalogsearch')->getEngine())
                ->setStoreId(Mage::app()->getStore()->getId())
                ->setPageSize($maxSize)
                ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
                ->addAttributeToSelect($attributes)
                ->addSearchFilter($this->_getQuery())
                ->addMinimalPrice()
                ->addFinalPrice()
                ->addTaxPercents()
                ->addUrlRewrite();

            $query = $collection->getSearchEngineQuery();

            $allowedVisibilities = Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
            $query->addFilter('terms', array('visibility' => $allowedVisibilities));

            $allowedStatuses = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();
            $query->addFilter('terms', array('status' => $allowedStatuses));

            if (Mage::helper('cataloginventory')->isShowOutOfStock() == false) {
                $query->addFilter('terms', array('in_stock' => 1));
            }

            $query->setQueryType('product_search_layer');

            $this->_collection = $collection;
        }

        return $this->_collection;
    }

    /**
     * Get number of suggestion to display
     *
     * @return int
     */
    public function getMaxSize()
    {
        return Mage::getStoreConfig('elasticsearch_advanced_search_settings/product_autocomplete/max_size');
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
