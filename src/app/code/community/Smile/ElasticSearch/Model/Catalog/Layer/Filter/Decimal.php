<?php
/**
 * Handles decimal attribute filtering in layered navigation.
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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal extends Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
{
    /**
     * Fields stats available when the filter is loaded.
     * Used to compute max & min values.
     *
     * @var array|null
     */
    protected $_stats = null;

    /**
     * Adds facet condition to product collection.
     *
     * @see Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal
     */
    public function addFacetCondition()
    {
        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
        $options = array('interval' => 1, 'field' => $this->_getFilterField());
        $query->addFacet($this->_getFilterField(), 'histogram', $options);

        return $this;
    }

    /**
     * Retrieves request parameter and applies it to product collection.
     *
     * @param Zend_Controller_Request_Abstract $request     Request containing filter var and value
     * @param Mage_Core_Block_Abstract         $filterBlock Layer block representing the filter
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal
     */
    public function apply(Zend_Controller_Request_Abstract $request, $filterBlock)
    {
        /**
         * Filter must be string: $from-$to
         */
        $filter = $request->getParam($this->getRequestVar());
        if (!$filter) {
            return $this;
        }

        //validate filter
        $filterParams = explode(',', $filter);
        $filter = $this->_validateFilter($filterParams[0]);
        if (!$filter) {
            return $this;
        }

        list($from, $to) = $filter;

        $this->setInterval(array($from, $to));

        $priorFilters = array();
        for ($i = 1; $i < count($filterParams); ++$i) {
            $priorFilter = $this->_validateFilter($filterParams[$i]);
            if ($priorFilter) {
                $priorFilters[] = $priorFilter;
            } else {
                //not valid data
                $priorFilters = array();
                break;
            }
        }
        if ($priorFilters) {
            $this->setPriorIntervals($priorFilters);
        }

        $this->_applyRange();
        $this->getLayer()->getState()->addFilter(
            $this->_createItem($this->_renderRangeLabel(empty($from) ? 0 : $from, $to), $filter)
        );

        return $this;
    }


    /**
     * Return stats (min, max, avg, ...) for the field
     *
     * @return array
     */
    protected function _getFieldStats()
    {
        if (is_null($this->_stats)) {
            $facets = $this->getLayer()->getProductCollection()->getFacetedData($this->_getFilterField());
            $this->_stats['min'] = key($facets);
            $this->_stats['max'] = key(array_reverse($facets, true));
        }
        return $this->_stats;
    }

    /**
     * Is the facet using decimals or not
     *
     * @return boolean
     */
    public function isDecimal()
    {
        $isDecimal = true;
        $attribute = $this->getAttributeModel();
        if ($attribute->getBackendModel() == 'int' || $attribute->getFrontendClass() == 'validate-number') {
            $isDecimal = false;
        }
        return $isDecimal;
    }

    /**
     * Retrieves max value for ranges definition.
     *
     * @return float
     */
    public function getMaxValue()
    {
        $stats = $this->_getFieldStats();
        $max = $stats['max'];
        if ($this->isDecimal()) {
            $max++;
        }
        return $max;
    }

    /**
     * Retrieves max value for ranges definition.
     *
     * @return float
     */
    public function getMinValue()
    {
        $stats = $this->_getFieldStats();
        $min = $stats['min'];
        if (!is_numeric($min)) {
            $min = 0;
        }
        return $min;
    }


    /**
     * Apply range filter to product collection.
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal
     */
    protected function _applyRange()
    {
        $interval = $this->getInterval();

        if (!$interval) {
            return $this;
        }

        list($from, $to) = $interval;
        if ($from === '' && $to === '') {
            return $this;
        }

        $field  = $this->_getFilterField();
        $limits = array();
        if (!empty($from)) {
            $limits['gte'] = $from;
        }
        if (!empty($to)) {
            $limits['lte'] = $to;
        }

        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
        $query->addFilter('range', array($this->_getFilterField() => $limits), $this->_getFilterField());

        return $this;
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        $data = array();

        $facets = $this->getLayer()->getProductCollection()->getFacetedData($this->_getFilterField());

        if (!empty($facets) && count($facets) > 1) {
            foreach ($facets as $key => $count) {
                $data[] = array(
                    'label'  => $key,
                    'value' => $key,
                    'count'  => $count
                );
            }
        }

        return $data;
    }

    /**
     * Validate and parse filter request param
     *
     * @param string $filter Filter applied
     *
     * @return array|bool
     */
    protected function _validateFilter($filter)
    {
        $filter = explode('-', $filter);
        if (count($filter) != 2) {
            return false;
        }
        foreach ($filter as $v) {
            if (($v !== '' && $v !== '0' && (float) $v <= 0) || is_infinite((float) $v)) {
                return false;
            }
        }

        return $filter;
    }

    /**
     * Prepare text of range label
     *
     * @param float|string $from From clause
     * @param float|string $to   To clause
     *
     * @return string
     */
    protected function _renderRangeLabel($from, $to)
    {
        if ($to === '') {
            return Mage::helper('catalog')->__('%s and above', $from);
        } elseif ($from == $to) {
            return $from;
        } else {
            return Mage::helper('catalog')->__('%s - %s', $from, $to);
        }
    }
}
