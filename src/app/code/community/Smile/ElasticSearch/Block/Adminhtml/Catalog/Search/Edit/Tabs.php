<?php
/**
 * Tabs for Search Term edition
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Block_Adminhtml_Catalog_Search_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    /**
     * Init tabs
     *
     * @return Smile_ElasticSearch_Block_Adminhtml_Catalog_Search_Edit_Tabs
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('smile_catalog_search_edit_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('Edit search term'));
    }
}
