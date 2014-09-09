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
class Smile_ElasticSearch_Block_Catalogsearch_Autocomplete_Suggest_Product extends Mage_Core_Block_Template
{
    const AUTOCOMPLETE_ATTRIBUTES_XPATH = 'global/smile_elasticsearch/autocomplete/product/attributes';

    /**
     * Check if the block is active or not. Block is disabled if :
     * - ES is not the selected engine into Magento
     *
     * @todo : Implements a configuration per type of suggester
     *
     * @return bool
     */
    public function isActive()
    {
        return Mage::helper('smile_elasticsearch')->isActiveEngine();
    }

    /**
     * Return the list of all suggested products
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    public function getProductCollection()
    {
        $attributes = array_keys(Mage::getConfig()->getNode(self::AUTOCOMPLETE_ATTRIBUTES_XPATH)->asArray());

        $collection = Mage::getResourceModel('smile_elasticsearch/catalog_product_suggest_collection')
            ->setEngine(Mage::helper('catalogsearch')->getEngine())
            ->setStoreId(Mage::app()->getStore()->getId())
            ->setPageSize(10)
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addAttributeToSelect($attributes)
            ->addSuggestFilter($this->_getQuery())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite();

        return $collection;
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
