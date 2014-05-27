<?php
/**
 * Handles price attribute filtering in layered navigation.
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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Price extends Mage_Catalog_Model_Layer_Filter_Price
{
    const CACHE_TAG = 'MAXPRICE';

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
        $range = $this->getPriceRange();
        $maxPrice = $this->getMaxPriceInt();
        if ($maxPrice > 0) {
            $priceFacets = array();
            $facetCount = (int) ceil($maxPrice / $range);

            for ($i = 0; $i < $facetCount + 1; $i++) {
                $from = ($i === 0) ? '' : ($i * $range);
                $to = ($i === $facetCount) ? '' : (($i + 1) * $range);
                $priceFacets[] = array(
                    'from' => $from,
                    'to' => $to,
                    'include_upper' => !($i < $facetCount)
                );
            }

            $this->getLayer()->getProductCollection()->addFacetCondition($this->_getFilterField(), $priceFacets);
        }

        return $this;
    }

    /**
     * Returns cache tag.
     *
     * @return string
     */
    public function getCacheTag()
    {
        return self::CACHE_TAG;
    }


    /**
     * Return stats (min, max, avg, ...) for the field
     *
     * @return array
     */
    protected function _getFieldStats()
    {

        if (is_null($this->_stats)) {
            $this->_stats = $this->getLayer()->getProductCollection()->getStats($this->_getFilterField());
        }

        return $this->_stats;
    }

    /**
     * Get price range for building filter steps
     *
     * @return int
     */
    public function getPriceRange()
    {
        $range = $this->getData('price_range');
        if (!$range) {
            $currentCategory = Mage::registry('current_category_filter');
            if ($currentCategory) {
                $range = $currentCategory->getFilterPriceRange();
            } else {
                $range = $this->getLayer()->getCurrentCategory()->getFilterPriceRange();
            }

            $maxPrice = $this->getMaxPriceInt();
            if (!$range) {
                $calculation = Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_CALCULATION);
                if ($calculation == self::RANGE_CALCULATION_AUTO) {
                    $range = pow(10, (strlen(floor($maxPrice)) - 1));
                } else {
                    $range = (float)Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_STEP);
                }
            }

            $this->setData('price_range', $range);
        }

        return $range;
    }

    /**
     * Retrieves max price for ranges definition.
     *
     * @return float
     */
    public function getMaxPriceInt()
    {
        $stats = $this->_getFieldStats();
        $max = $stats[$this->_getFilterField()]['max'];
        if (!is_numeric($max)) {
            $max = parent::getMaxPriceInt();
        }
        return $max;
    }

    /**
     * Retrieves max price for ranges definition.
     *
     * @return float
     */
    public function getMinPriceInt()
    {
        $stats = $this->_getFieldStats();
        $min = $stats[$this->_getFilterField()]['min'];
        if (!is_numeric($min)) {
            $min = 0;
        }
        return $min;
    }

    /**
     * Apply price range filter to product collection.
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Price
     */
    protected function _applyPriceRange()
    {
        $interval = $this->getInterval();
        if (!$interval) {
            return $this;
        }

        list($from, $to) = $interval;
        if ($from === '' && $to === '') {
            return $this;
        }

        if ($to !== '') {
            $to = (float) $to;
            if ($from == $to) {
                $to += .01;
            }
        }

        $field = $this->_getFilterField();
        $value = array(
            $field => array(
                'include_upper' => !($to < $this->getMaxPriceInt())
            )
        );

        if (!empty($from)) {
            $value[$field]['from'] = $from;
        }
        if (!empty($to)) {
            $value[$field]['to'] = $to;
        }

        $this->getLayer()->getProductCollection()->addFqRangeFilter($value);

        return $this;
    }

    /**
     * Returns price field according to current customer group and website.
     *
     * @return string
     */
    protected function _getFilterField()
    {
        $websiteId = Mage::app()->getStore()->getWebsiteId();
        $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
        $priceField = 'price_' . $customerGroupId . '_' . $websiteId;

        return $priceField;
    }

    /**
     * Retrieves current items data.
     *
     * @return array
     */
    protected function _getItemsData()
    {
        if (Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_CALCULATION) == self::RANGE_CALCULATION_IMPROVED) {
            return $this->_getCalculatedItemsData();
        }

        $data = array();
        $facets = $this->getLayer()->getProductCollection()->getFacetedData($this->_getFilterField());

        if (!empty($facets)) {
            foreach ($facets as $key => $count) {
                if (!$count) {
                    unset($facets[$key]);
                }
            }
            $i = 0;
            foreach ($facets as $key => $count) {
                $i++;
                preg_match('/^\[(\d*) TO (\d*)\]$/', $key, $rangeKey);
                $fromPrice = $rangeKey[1];
                $toPrice = ($i < count($facets)) ? $rangeKey[2] : '';
                $data[] = array(
                    'label' => $this->_renderRangeLabel($fromPrice, $toPrice),
                    'value' => $fromPrice . '-' . $toPrice,
                    'count' => $count
                );
            }
        }

        return $data;
    }
}
