<?php
/**
 * Handles category filtering in layered navigation.
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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Item_Category extends Mage_Catalog_Model_Layer_Filter_Item
{
    /**
     * Allow to keep some filter active when changing category
     *
     * @param string $url URL of the category
     *
     * @return string
     */
    protected function _addCurrentParameters($url)
    {
        $queryParams = Mage::app()->getRequest()->getParams();
        $paramsReset = array(Mage::getBlockSingleton('page/html_pager')->getPageVarName() => null, 'id' => null);
        $queryPart   =  http_build_query(array_merge($queryParams, $paramsReset));
        if ($queryPart) {
            $url .= strpos($url, '?') === false ? '?' . $queryPart : '&' . $queryPart;
        }
        return $url;
    }

    /**
     * Get filter item url
     *
     * @return string
     */
    public function getUrl()
    {
        $categoryUrl = $this->getCategoryUrl();
        if (!$categoryUrl) {
            $categoryUrl = Mage::getModel('catalog/category')->load($this->getValue())->getUrl();
        }
        return $this->_addCurrentParameters($categoryUrl);
    }

    /**
     * Get url for remove item from filter
     *
     * @return string
     */
    public function getRemoveUrl()
    {
        $parentCategory = Mage::getModel('catalog/category')->load($this->getValue())
            ->getParentCategory();

        return $this->_addCurrentParameters($parentCategory->getUrl());
    }

    /**
     * Get url for "clear" link
     *
     * @return false|string
     */
    public function getClearLinkUrl()
    {
        $clearLinkText = $this->getFilter()->getClearLinkText();
        if (!$clearLinkText) {
            return false;
        }

        $urlParams = array(
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => array($this->getFilter()->getRequestVar() => null),
            '_escape' => true,
        );
        return Mage::getUrl('*/*/*', $urlParams);
    }
}
