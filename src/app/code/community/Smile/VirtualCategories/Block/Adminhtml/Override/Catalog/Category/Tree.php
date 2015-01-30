<?php
/**
 * Category tree display overriden to fix wrong product count when using virtual categories.
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
class Smile_VirtualCategories_Block_Adminhtml_Override_Catalog_Category_Tree extends Mage_Adminhtml_Block_Catalog_Category_Tree
{
    /**
     * Retrieve product collection
     *
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection
     */
    public function getCategoryCollection()
    {
        $collection = $this->getData('category_collection');
        if (is_null($collection)) {
            $collection = Mage::getResourceModel('smile_virtualcategories/catalog_virtualCategory_collection');

            /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection */
            $collection->addAttributeToSelect('name')
                ->addAttributeToSelect(array('is_active', 'virtual_category'))
                ->setProductStoreId($this->getStoreId())
                ->setLoadProductCount($this->_withProductCount)
                ->setStoreId($this->getStoreId());

            $this->setData('category_collection', $collection);
        }
        return $collection;
    }

    /**
     * Indicates if Magento is running multiple stores.
     *
     * @return boolean
     */
    protected function _isMultipleStore()
    {
        return count(Mage::app()->getStores()) > 1;
    }

    /**
     * Return default store id.
     *
     * @return int
     */
    protected function _getDefaultStoreId()
    {
        $currentStore = current(Mage::app()->getStores());
        return $currentStore->getId();
    }

    /**
     * Return current store id.
     *
     * @return int
     */
    public function getStoreId()
    {
        $storeId = $this->getRequest()->getParam('store');

        if ($storeId == 0 && $this->_isMultipleStore()) {
            // Product count is not relevant on multiple stores with virtual categories
            // instance since attributes may vary from one store to another
            // Store must be be selected at first
            $this->_withProductCount = 0;
        } else if ($storeId == 0) {
            $storeId = $this->_getDefaultStoreId();
        }
        return $storeId;
    }


    /**
     * Get JSON of array of categories, that are breadcrumbs for specified category path
     *
     * @param string $path              Category path.
     * @param string $javascriptVarName Name of the variable the JSON should be placed in.
     *
     * @return string
     */
    public function getBreadcrumbsJavascript($path, $javascriptVarName)
    {
        $result = '';

        if (!empty($path)) {
            $categories = Mage::getResourceModel('smile_virtualcategories/catalog_virtualCategory_collection');
            $categories->addAttributeToSelect('name')
                ->addAttributeToSelect(array('is_active', 'virtual_category'))
                ->addIdFilter(explode('/', $path))
                ->setProductStoreId($this->getStoreId())
                ->setStoreId($this->getStoreId())
                ->setLoadProductCount($this->_withProductCount);

            $categoriesNodes = array();
            foreach ($categories as $category) {
                $categoriesNodes[$category->getId()] = $this->_getNodeJson($category->getData());
            }

            if (!empty($categories)) {
                $result = '<script type="text/javascript">'
                    . $javascriptVarName . ' = ' . Mage::helper('core')->jsonEncode($categoriesNodes) . ';'
                        . ($this->canAddSubCategory()
                            ? '$("add_subcategory_button").show();'
                            : '$("add_subcategory_button").hide();')
                            . '</script>';
            }
        }

        return $result;
    }
}