<?php
/**
 * Optimizer edit form container
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Container construct method.
     */
    public function __construct()
    {
        $this->_objectId = 'optimizer_id';
        $this->_controller = 'adminhtml_optimizer';
        $this->_blockGroup = 'smile_searchoptimizer';

        parent::__construct();

        $this->setData('form_action_url', Mage::getUrl('*/*/save'));

        $this->_updateButton('save', 'label', Mage::helper('smile_searchoptimizer')->__('Save optimizer'));
        $this->_updateButton('delete', 'label', Mage::helper('smile_searchoptimizer')->__('Delete optimizer'));

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
     * Get edit form container header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        $model = Mage::registry('search_optimizer');

        if ($model->getId()) {
            $title = Mage::helper('smile_searchoptimizer')->__("Edit optimizer '%s'", $this->escapeHtml($model->getName()));
        } else {
            $title = Mage::helper('smile_searchoptimizer')->__('New optimizer');
        }

        $title  = $this->__('%s [%s]', $title, $model->getModelInstance()->getName());

        return $title;
    }

}
