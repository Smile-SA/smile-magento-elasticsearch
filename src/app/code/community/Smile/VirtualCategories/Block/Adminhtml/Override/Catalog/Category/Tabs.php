<?php
/**
 * Virtual categories override for Magento standard product tabs into categories.
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
class Smile_VirtualCategories_Block_Adminhtml_Override_Catalog_Category_Tabs extends Mage_Adminhtml_Block_Catalog_Category_Tabs
{
    /**
     * Replace the content of the standard table with the new one.
     *
     * @return Smile_VirtualCategories_Block_Adminhtml_Override_Catalog_Category_Tabs
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $tab = $this->_tabs['products'];
        $blockName = 'smile_virtualcategories/adminhtml_catalog_category_tab_product';
        $newTab = $this->getLayout()
            ->createBlock($blockName, 'category.products.tab', array('products_grid' => $this->getTabContent($tab)));

        $tab->setContent($newTab->toHtml());

        return $this;
    }
}