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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal extends Mage_Catalog_Model_Layer_Filter_Decimal
{
    const CACHE_TAG = 'MAXVALUE';

    /**
     * Adds facet condition to product collection.
     *
     * @see Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal
     */
    public function addFacetCondition()
    {
        $range = $this->getRange();
        $maxValue = $this->getMaxValue();
        if ($maxValue > 0) {
            $facets = array();
            $facetCount = (int) ceil($maxValue / $range);

            for ($i = 0; $i < $facetCount + 1; $i++) {
                $facets[] = array(
                    'from' => $i * $range,
                    'to' => ($i + 1) * $range,
                    'include_upper' => !($i < $facetCount)
                );
            }

            $fieldName = $this->_getFilterField();
            $this->getLayer()->getProductCollection()->addFacetCondition($fieldName, $facets);
        }

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
        $filter = $request->getParam($this->getRequestVar());
        if (!$filter) {
            return $this;
        }

        $filter = explode(',', $filter);
        if (count($filter) != 2) {
            return $this;
        }

        list($index, $range) = $filter;

        if ((int) $index && (int) $range) {
            $this->setRange((int) $range);

            $this->applyFilterToCollection($this, $range, $index);
            $this->getLayer()->getState()->addFilter(
                $this->_createItem($this->_renderItemLabel($range, $index), $filter)
            );

            $this->_items = array();
        }

        return $this;
    }

    /**
     * Apply decimal filter range to product collection.
     *
     * @param Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal $filter Filter to be applied
     * @param int                                                    $range  Size of the range to be applied
     * @param int                                                    $index  Offset position
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal
     */
    public function applyFilterToCollection($filter, $range, $index)
    {
        $value = array(
            $this->_getFilterField() => array(
                'from' => ($range * ($index - 1)),
                'to'   => $range * $index,
            )
        );
        $filter->getLayer()->getProductCollection()->addFqFilter($value);

        return $this;
    }

    /**
     * Return the max value of the attribute
     *
     * @see Mage_Catalog_Model_Layer_Filter_Decimal::getMaxValue()
     *
     * @return float
     */
    public function getMaxValue()
    {
        $searchParams = $this->getLayer()->getProductCollection()->getExtendedSearchParams();
        $uniquePart = strtoupper(md5(serialize($searchParams)));
        $cacheKey = 'MAXVALUE_' . $this->getLayer()->getStateKey() . '_' . $uniquePart;

        $cachedData = Mage::app()->loadCache($cacheKey);
        if (!$cachedData) {
            $stats = $this->getLayer()->getProductCollection()->getStats($this->_getFilterField());

            $max = $stats[$this->_getFilterField()]['max'];
            if (!is_numeric($max)) {
                $max = parent::getMaxValue();
            }

            $cachedData = (float) $max;
            $tags = $this->getLayer()->getStateTags();
            $tags[] = self::CACHE_TAG;
            Mage::app()->saveCache($cachedData, $cacheKey, $tags);
        }

        return $cachedData;
    }

    /**
     * Returns decimal field name.
     *
     * @return string
     */
    protected function _getFilterField()
    {
        $fieldName = Mage::helper('smile_elasticsearch')->getAttributeFieldName($this->getAttributeModel());

        return $fieldName;
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        $range = $this->getRange();
        $fieldName = $this->_getFilterField();
        $facets = $this->getLayer()->getProductCollection()->getFacetedData($fieldName);

        $data = array();
        if (!empty($facets)) {
            foreach ($facets as $key => $count) {
                if ($count > 0) {
                    preg_match('/TO ([\d\.]+)\]$/', $key, $rangeKey);
                    $rangeKey = round($rangeKey[1] / $range);
                    $data[] = array(
                        'label' => $this->_renderItemLabel($range, $rangeKey),
                        'value' => $rangeKey . ',' . $range,
                        'count' => $count,
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Renders decimal ranges.
     *
     * @param int   $range Range size
     * @param float $value Current value
     *
     * @return string
     */
    protected function _renderItemLabel($range, $value)
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $this->getAttributeModel();

        if ($attribute->getFrontendInput() == 'price') {
            return parent::_renderItemLabel($range, $value);
        }

        $from = ($value - 1) * $range;
        $to = $value * $range;

        if ($from != $to) {
            $to -= 0.01;
        }

        $to = Zend_Locale_Format::toFloat($to, array('locale' => Mage::helper('smile_elasticsearch')->getLocaleCode()));

        return Mage::helper('catalog')->__('%s - %s', $from, $to);
    }
}
