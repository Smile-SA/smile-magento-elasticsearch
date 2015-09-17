<?php
/**
 * Price filter block
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
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Price extends Smile_ElasticSearch_Block_Catalog_Layer_Filter_Decimal
{
    /**
     * Defines specific filter model name.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Price
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'smile_elasticsearch/catalog_layer_filter_price';
        $this->setTemplate('smile/elasticsearch/catalog/layer/filter/price.phtml');
    }

    /**
     * Prepares filter model.
     *
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Price
     */
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());

        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Price::addFacetCondition()
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Price
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();
        return $this;
    }

    /**
     * Return the lowest price avaiblable for filtering.
     *
     * @param bool $rounding Enable rounding feature according price range
     *
     * @return int
     */
    public function getMinPriceInt($rounding = false)
    {
        return parent::getMinValueInt($rounding);
    }

    /**
     * Return the highest price avaiblable for filtering.
     *
     * @param bool $rounding Enable rounding feature according price range
     *
     * @return int
     */
    public function getMaxPriceInt($rounding = false)
    {
        return parent::getMaxValueInt($rounding);
    }


    /**
     * Return the price format used by JS to display prices.
     *
     * @return array
     */
    public function getJsPriceFormat()
    {
        return Mage::helper('core/data')->jsonEncode(Mage::app()->getLocale()->getJsPriceFormat());
    }
}
