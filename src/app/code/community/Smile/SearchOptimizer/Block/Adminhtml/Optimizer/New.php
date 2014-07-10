<?php
/**
 * Optimizer create form container
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
 * @package   Smile_SearchOptimizer
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2014 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_New extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Container construct method.
     */
    public function __construct()
    {
        $this->_objectId = 'optimizer_id';
        $this->_controller = 'adminhtml_optimizer';
        $this->_blockGroup = 'smile_searchoptimizer';
        $this->_mode = 'new';

        parent::__construct();

        $this->setData('form_action_url', $this->getUrl('*/*/new'));

        $this->_updateButton('save', 'label', Mage::helper('smile_searchoptimizer')->__('Create optimizer'));
        $this->_removeButton('reset');
    }

    /**
     * Get edit form container header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('smile_searchoptimizer')->__('Create optimizer');
    }

    /**
     * Hide the form if model is set.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ($this->getRequest()->getParam('model')) {
            return '';
        } else {
            return parent::_toHtml();
        }
    }

}
