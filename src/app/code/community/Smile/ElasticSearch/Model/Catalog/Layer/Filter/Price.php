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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Price extends Smile_ElasticSearch_Model_Catalog_Layer_Filter_Decimal
{
    /**
     * Retrieves max price for ranges definition.
     *
     * @return float
     */
    public function getMaxPriceInt()
    {
        return $this->getMaxValue();
    }

    /**
     * Retrieves max price for ranges definition.
     *
     * @return float
     */
    public function getMinPriceInt()
    {
        return $this->getMinValue();
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
     * Prepare text of range label
     *
     * @param float|string $from From clause
     * @param float|string $to   To clause
     *
     * @return string
     */
    protected function _renderRangeLabel($from, $to)
    {
        $store = Mage::app()->getStore();
        $formattedFromPrice  = $store->formatPrice($from);
        if ($to === '') {
            return Mage::helper('catalog')->__('%s and above', $formattedFromPrice);
        } elseif ($from == $to) {
            return $formattedFromPrice;
        } else {
            return Mage::helper('catalog')->__('%s - %s', $formattedFromPrice, $store->formatPrice($to));
        }
    }

}
