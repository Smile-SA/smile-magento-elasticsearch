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
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Price extends Smile_ElasticSearch_Block_Catalog_Layer_Filter_Abstract
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
        $minPrice = $this->_filter->getMinPriceInt();
        if ($rounding === true) {
            $range = $this->getPriceRange();
            $minPrice = max(0, floor($minPrice / $range) * $range);
        }
        return $minPrice;
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
        $maxPrice = $this->_filter->getMaxPriceInt();
        if ($rounding === true) {
            $range = $this->getPriceRange();
            $maxPrice = ceil($maxPrice / $range) * $range;
        }
        return $maxPrice;
    }

    /**
     * Return the size of the filtering interval
     *
     * @return int
     */
    public function getPriceRange()
    {
        return $this->_filter->getPriceRange();
    }

    /**
     * JS template of the get var filter
     *
     * @return string
     */
    public function getFilterJsTemplate()
    {
        $requestVar = $this->getRequestVar();
        return "$requestVar=#{min}-#{max}";
    }

    /**
     * Return the currently selected interval.
     *
     * @return array
     */
    public function getInterval()
    {
        $interval = $this->_filter->getInterval();
        if (is_null($interval)) {
            $interval = array($this->getMinPriceInt(true), $this->getMaxPriceInt(true));
        }
        return $interval;
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

    /**
     * Array of the interval containing products (used to build sliders)
     *
     * @return array
     */
    public function getAllowedIntervals()
    {
        $minPriceInt = $this->getMinPriceInt(true);
        $maxPriceInt = $this->getMaxPriceInt(true);
        $allowedIntervals = array();

        foreach ($this->getItems() as $currentItem) {
            $allowedIntervals[] = array('value' => $currentItem->getValue(), 'count' => $currentItem->getCount());
        }

        return $allowedIntervals;
    }
}
