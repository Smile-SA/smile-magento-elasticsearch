<?php
/**
 * Decimal attributes filter block
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
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Decimal extends Smile_ElasticSearch_Block_Catalog_Layer_Filter_Abstract
{
    /**
     * Defines specific filter model name.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal
     */
    public function __construct()
    {
        parent::__construct();
        $this->_filterModelName = 'smile_elasticsearch/catalog_layer_filter_decimal';
        $this->setTemplate('smile/elasticsearch/catalog/layer/filter/range.phtml');
    }

    /**
     * Prepares filter model.
     *
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Decimal
     */
    protected function _prepareFilter()
    {
        $this->_filter->setAttributeModel($this->getAttributeModel());

        return $this;
    }

    /**
     * Adds facet condition to filter.
     *
     * @see Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal::addFacetCondition()
     * @return Smile_ElasticSearch_Block_Catalog_Layer_Filter_Decimal
     */
    public function addFacetCondition()
    {
        $this->_filter->addFacetCondition();

        return $this;
    }

    /**
     * Return the lowest avaiblable for filtering.
     *
     * @param bool $rounding Enable rounding feature according range
     *
     * @return int
     */
    public function getMinValueInt($rounding = false)
    {
        $minValue = $this->_filter->getMinValue();
        if ($rounding === true) {
            $range = $this->getRange();
            $minValue = max(0, floor($minValue / $range) * $range);
        }
        return $minValue;
    }

    /**
     * Return the highest avaiblable for filtering.
     *
     * @param bool $rounding Enable rounding feature according range
     *
     * @return int
     */
    public function getMaxValueInt($rounding = false)
    {
        $maxValue = $this->_filter->getMaxValue();
        if ($rounding === true) {
            $pow = pow(10, strlen(ceil($maxValue)) - 1);
            $maxValue = ceil($maxValue / $pow) * $pow;
        }
        return $maxValue;
    }

    /**
     * Return the size of the filtering interval
     *
     * @return int
     */
    public function getRange()
    {
        return max(1, $this->_filter->getMaxValue() - $this->_filter->getMinValue());
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
            $interval = array($this->getMinValueInt(true), $this->getMaxValueInt(true));
        }
        return $interval;
    }

     /**
     * Array of the interval containing products (used to build sliders)
     *
     * @return array
     */
    public function getAllowedIntervals()
    {
        $minValueInt = $this->getMinValueInt(true);
        $maxValueInt = $this->getMaxValueInt(true);
        $allowedIntervals = array();

        foreach ($this->getItems() as $currentItem) {
            $allowedIntervals[] = array('value' => $currentItem->getValue(), 'count' => $currentItem->getCount());
        }
        return $allowedIntervals;
    }
}
