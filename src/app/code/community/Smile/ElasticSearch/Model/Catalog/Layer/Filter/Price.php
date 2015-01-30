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
        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();
        $options = array('interval' => 1, 'field' => $this->_getFilterField());
        $query->addFacet($this->_getFilterField(), 'histogram', $options);

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
            $facets = $this->getLayer()->getProductCollection()->getFacetedData($this->_getFilterField());
            $this->_stats['min'] = key($facets);
            $this->_stats['max'] = key(array_reverse($facets, true));
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
            $minPrice = $this->getMinPriceInt();
            if (!$range) {
                $calculation = Mage::app()->getStore()->getConfig(self::XML_PATH_RANGE_CALCULATION);
                if ($calculation == self::RANGE_CALCULATION_AUTO) {
                    $range = pow(10, (strlen(floor($maxPrice - $minPrice)) - 1));
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
        $max = $stats['max'];
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
        $min = $stats['min'];
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
     * Prepare text of range label
     *
     * @param float|string $fromPrice Interval min.
     * @param float|string $toPrice   Interval max.
     *
     * @return string
     */
    protected function _renderRangeLabel($fromPrice, $toPrice)
    {
        $store      = Mage::app()->getStore();
        $formattedFromPrice  = $store->formatPrice($fromPrice);
        if ($toPrice === '') {
            return Mage::helper('catalog')->__('%s and above', $formattedFromPrice);
        } elseif ($fromPrice == $toPrice && Mage::app()->getStore()->getConfig(self::XML_PATH_ONE_PRICE_INTERVAL)) {
            return $formattedFromPrice;
        } else {
            return Mage::helper('catalog')->__('%s - %s', $formattedFromPrice, $store->formatPrice($toPrice));
        }
    }
}
