<?php
/**
 * Wrapper block for catalog search term edition
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
class Smile_ElasticSearch_Block_Adminhtml_Catalog_Search_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Block constructor
     *
     */
    public function __construct()
    {
        $this->_objectId   = 'id';
        $this->_controller = 'adminhtml_catalog_search';
        $this->_blockGroup = 'smile_elasticsearch';

        parent::__construct();

        $this->setFormActionUrl($this->getUrl('*/*/save'));

        $this->_updateButton('save', 'label', Mage::helper('catalog')->__('Save Search'));
        $this->_updateButton('delete', 'label', Mage::helper('catalog')->__('Delete Search'));

        $this->_addButton(
            'saveandcontinue',
            array(
                'label'     => Mage::helper('adminhtml')->__('Save and Continue Edit'),
                'onclick'   => 'saveAndContinueEdit()',
                'class'     => 'save',
            ),
            -100
        );

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    /**
     * Retrieve block header
     *
     * @return string
     */
    public function getHeaderText()
    {
        if (Mage::registry('current_catalog_search')->getId()) {
            return Mage::helper('catalog')->__(
                "Edit Search '%s'",
                $this->escapeHtml(Mage::registry('current_catalog_search')->getQueryText())
            );
        } else {
            return Mage::helper('catalog')->__('New Search');
        }
    }
}