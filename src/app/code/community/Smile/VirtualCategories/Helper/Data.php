<?php
/**
 * Virtual helper
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
 * @package   Smile_VirtualCategories
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Helper_Data extends Mage_Core_Helper_Data
{

    /**
     * Local cache for loaded rules.
     *
     * @var array
     */
    protected $_categoryRulesCache = array();

    /**
     * Force the virtual rule to be loaded for a category.
     *
     * @param Mage_Catalog_Model_Category $category The category.
     *
     * @return Smile_VirtualCategories_Model_Rule
     */
    public function getVirtualRule($category)
    {

        $virtualRule = $category->getVirtualRule();

        if (!is_object($virtualRule)) {
            $cacheKey = $category->getId();

            if ($category->getStoreId()) {
                $cacheKey = $cacheKey . '_' . $category->getStoreId();
            }

            if (!isset($this->_categoryRulesCache[$cacheKey])) {
                $backend = Mage::getSingleton('smile_virtualcategories/category_attributes_backend_virtual');
                $backend->afterLoad($category);
                $this->_categoryRulesCache[$cacheKey] = $category->getVirtualRule();
            }

            $virtualRule = $this->_categoryRulesCache[$cacheKey];
        }

        return $virtualRule;
    }
}