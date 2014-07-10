<?php
/**
 * Optimizer create form.
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_New_Form extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * Init form
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('optimizer_form');
        $this->setTitle(Mage::helper('smile_searchoptimizer')->__('Create optimizer'));
    }

    /**
     * Create the form and append field to it.
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Form
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(
            array('id' => 'edit_form', 'action' => $this->getData('action'))
        );

        $form->setHtmlIdPrefix('optimizer_');

        $fieldset = $form->addFieldset(
            'base_fieldset',
            array('legend'=>Mage::helper('smile_searchoptimizer')->__('Type'), 'class' => 'fieldset-wide')
        );

        $field = $fieldset->addField(
            'model',
            'select',
            array(
                'name'      => 'model',
                'label'     => Mage::helper('smile_searchoptimizer')->__('Optimizer type'),
                'title'     => Mage::helper('smile_searchoptimizer')->__('Optimizer type'),
                'required'  => true,
                'values'    => Mage::getSingleton('smile_searchoptimizer/optimizer')->getAvailableModels(),
            )
        );

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

}
