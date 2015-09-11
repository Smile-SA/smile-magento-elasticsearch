<?php
/**
 * Optimizer edit form main configuration tab
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
class Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Main
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Prepare content for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Global configuration');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Global configuration');
    }

    /**
     * Returns status flag about this tab can be showed or not
     *
     * @return true
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Returns status flag about this tab hidden or not
     *
     * @return true
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Prepare the form.
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Main
     */
    protected function _prepareForm()
    {
        $model = Mage::registry('search_optimizer');
        if (!$model->getId()) {
            $model->setData('is_active', '1');
        }

        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('optimizer_');

        $this->_addGlobalFieldset($form, $model);
        $this->_addActivationFieldset($form, $model);

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Append global settings to the form
     *
     * @param Varien_Form                           $form  The current form
     * @param Smile_SearchOptimizer_Model_Optimizer $model Optimizer model
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Main
     */
    protected function _addGlobalFieldset($form, $model)
    {
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
    }

    /**
     * Append activation settings to the form
     *
     * @param Varien_Form                           $form  The current form
     * @param Smile_SearchOptimizer_Model_Optimizer $model Optimizer model
     *
     * @return Smile_SearchOptimizer_Block_Adminhtml_Optimizer_Edit_Tab_Main
     */
    protected function _addActivationFieldset($form, $model)
    {
        $fieldset = $form->addFieldset(
            'activation_fieldset',
            array(
                'legend' => Mage::helper('smile_searchoptimizer')->__('Activation'),
                'class'  => 'fieldset-wide'
            )
        );

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

        $dateFormatIso = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
        $fieldset->addField(
            'from_date',
            'date',
            array(
                'name'         => 'from_date',
                'label'        => Mage::helper('smile_searchoptimizer')->__('From Date'),
                'title'        => Mage::helper('smile_searchoptimizer')->__('From Date'),
                'image'        => $this->getSkinUrl('images/grid-cal.gif'),
                'input_format' => Varien_Date::DATE_INTERNAL_FORMAT,
                'format'       => $dateFormatIso
            )
        );
        $fieldset->addField(
            'to_date',
            'date',
            array(
                'name'         => 'to_date',
                'label'        => Mage::helper('smile_searchoptimizer')->__('To Date'),
                'title'        => Mage::helper('smile_searchoptimizer')->__('To Date'),
                'image'        => $this->getSkinUrl('images/grid-cal.gif'),
                'input_format' => Varien_Date::DATE_INTERNAL_FORMAT,
                'format'       => $dateFormatIso
            )
        );

        $fieldset->addField(
            'query_type',
            'multiselect',
            array(
                'name' => 'query_type[]',
                'label' => $this->__('Query type'),
                'title' => $this->__('Query type'),
                'required' => true,
                'values' => Mage::getSingleton('smile_searchoptimizer/adminhtml_system_source_queryType')->toOptionArray(true)
            )
        );

        return $this;
    }
}
