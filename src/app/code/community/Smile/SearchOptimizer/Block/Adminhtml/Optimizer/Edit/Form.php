<?php
/**
 * Optimizer edit form.
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * Init form
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('optimizer_form');
        $this->setTitle(Mage::helper('smile_searchoptimizer')->__('Optimizer Information'));
    }

    /**
     * Create the form and append field to it.
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Form
     */
    protected function _prepareForm()
    {
        $model = Mage::registry('search_optimizer');

        $form = new Varien_Data_Form(
            array('id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post')
        );

        $form->setHtmlIdPrefix('optimizer_');

        $fieldset = $form->addFieldset(
            'base_fieldset',
            array(
                'legend' => Mage::helper('smile_searchoptimizer')->__('General Information'),
                'class'  => 'fieldset-wide'
            )
        );

        if ($model->getOptimizerId()) {
            $fieldset->addField('optimizer_id', 'hidden', array('name' => 'optimizer_id'));
        }

        $fieldset->addField(
            'name',
            'text',
            array(
                'name'      => 'name',
                'label'     => Mage::helper('smile_searchoptimizer')->__('Optimizer Name'),
                'title'     => Mage::helper('smile_searchoptimizer')->__('Optimizer Name'),
                'required'  => true,
            )
        );

        $fieldset->addField('model', 'hidden', array('name' => 'model'));

        /**
         * Check is single store mode
         */
        if (!Mage::app()->isSingleStoreMode()) {
            $field = $fieldset->addField(
                'store_id',
                'multiselect',
                array(
                    'name'      => 'stores[]',
                    'label'     => Mage::helper('smile_searchoptimizer')->__('Store View'),
                    'title'     => Mage::helper('smile_searchoptimizer')->__('Store View'),
                    'required'  => true,
                    'values'    => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true),
                )
            );
            $renderer = $this->getLayout()->createBlock('adminhtml/store_switcher_form_renderer_fieldset_element');
            $field->setRenderer($renderer);

        } else {
            $fieldset->addField(
                'store_id',
                'hidden',
                array(
                'name'      => 'stores[]',
                'value'     => Mage::app()->getStore(true)->getId()
                )
            );
            $model->setStoreId(Mage::app()->getStore(true)->getId());

        }

        $fieldset->addField(
            'is_active',
            'select',
            array(
                'label'     => Mage::helper('smile_searchoptimizer')->__('Status'),
                'title'     => Mage::helper('smile_searchoptimizer')->__('Status'),
                'name'      => 'is_active',
                'required'  => true,
                'options'   => array(
                    '1' => Mage::helper('smile_searchoptimizer')->__('Enabled'),
                    '0' => Mage::helper('smile_searchoptimizer')->__('Disabled'),
                ),
            )
        );

        $model->prepareForm($form);

        if (!$model->getId()) {
            $model->setData('is_active', '1');
        }

        $form->setValues($model->getData());
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

}
