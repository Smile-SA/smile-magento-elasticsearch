<?php
/**
 * Optimizer model abstract
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
abstract class Smile_SearchOptimizer_Model_Optimizer_Abstract
{
    /**
     * Name of the optimizer model.
     *
     * @var string
     */
    protected $_name            = 'Optimizer name';

    /**
     * Set to false if you don't want filter available for your model.
     *
     * @var bool
     */
    protected $_isFilterEnabled = true;

    /**
     * Default configuration values.
     *
     * @var array
     */
    protected $_defaultValues = array();

    /**
     * Return the name of the optimizer.
     *
     * @return string
     */
    public function getName()
    {
        return Mage::helper('smile_searchoptimizer')->__($this->_name);
    }

    /**
     * Append model configuration to the form.
     *
     * @param Varien_Data_Form                      $form      Form the config should be added to.
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer_Abstract Self reference.
     */
    public function prepareForm($form, $optimizer)
    {

        $form->addFieldset(
            'model_config_fieldset',
            array(
                'legend'=> Mage::helper('smile_searchoptimizer')->__('Configuration'),
                'class' => 'fieldset-wide'
            )
        );

        if ($this->_isFilterEnabled) {
            $this->prepareFilterForm($form, $optimizer);
        }

        return $this;
    }

    /**
     * Prepare the filters part of the configuration form.
     *
     * @param Varien_Data_Form                      $form      Form the config should be added to.
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer_Abstract Self reference.
     */
    public function prepareFilterForm($form, $optimizer)
    {
        $url = Mage::helper('adminhtml')->getUrl('*/promo_catalog/newConditionHtml', array('form' => 'optimizer_virtual_fieldset'));

        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('smile/virtualcategories/category/rule_fieldset.phtml')
            ->setNewChildUrl($url);

        $fieldset = $form->addFieldset(
            'virtual_fieldset',
            array('legend' => Mage::helper('smile_searchoptimizer')->__('Apply to products'), 'class' => 'fieldset-wide')
        )->setRenderer($renderer);

        $field = $fieldset->addField(
            'conditions',
            'text',
            array(
                'name' => 'conditions',
                'label' => Mage::helper('catalogrule')->__('Conditions'),
                'title' => Mage::helper('catalogrule')->__('Conditions'),
                'required' => true,
            )
        );

        $rule = $optimizer->getFilterRule();
        $rule->setForm($form);
        $rule->getConditions()->setJsFormObject('optimizer_virtual_fieldset');

        $field->setRule($rule)
            ->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        return $this;
    }

    /**
     * Return default default config values for the currrent model.
     *
     * @return array
     */
    public function getDefaultValues()
    {
        return $this->_defaultValues;
    }

    /**
     * Apply the model to the query.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     * @param array                                 $query     Query to optimize.
     *
     * @return array The modified query.
     */
    abstract public function apply($optimizer, $query);
}