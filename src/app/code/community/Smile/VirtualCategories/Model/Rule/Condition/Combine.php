<?php
/**
 * Virtual categories rule combine.
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
class Smile_VirtualCategories_Model_Rule_Condition_Combine extends Mage_CatalogRule_Model_Rule_Condition_Combine
{
    /**
     * List all available rules under a combine.
     *
     * @return array
     */
    public function getNewChildSelectOptions()
    {
        $productCondition = Mage::getModel('smile_virtualcategories/rule_condition_product');
        $productAttributes = $productCondition->loadAttributeOptions()->getAttributeOption();
        $attributes = array();

        foreach ($productAttributes as $code=>$label) {
            $attributes[] = array('value' => 'smile_virtualcategories/rule_condition_product|'.$code, 'label' => $label);
        }

        $conditions = array(
            array(
                'value' => '',
                'label' => Mage::helper('rule')->__('Please choose a condition to add...')
            ),
            array(
                'value' => 'smile_virtualcategories/rule_condition_combine',
                'label' => Mage::helper('catalogrule')->__('Conditions Combination')
            ),
            array(
                'value' => $attributes,
                'label' => Mage::helper('catalogrule')->__('Product Attribute')
            )
        );

        return $conditions;
    }

    /**
     * Build search query for the rule.
     *
     * @param string $excludedCategories Categories that should not beein used during query building.
     *
     * @return string
     */
    public function getSearchQuery($excludedCategories = array())
    {
        $operator = 'must';

        $ruleOperator = $this->getAggregator();
        $ruleValue    = $this->getValue();

        $conditions   = array();
        foreach ($this->getConditions() as $condition) {
            $condition->setRule($this->getRule());
            $conditions[] = $condition->getSearchQuery($excludedCategories);
        }

        $conditions = array_filter($conditions);
        $query = false;

        if (!empty($conditions)) {
            if ($ruleOperator == 'any' && $ruleValue == '1') {
                $query = implode(' OR ', $conditions);
            } elseif ($ruleOperator == 'any' && $ruleValue = '0') {
                $query = '-(' . implode(' AND ', $conditions) . ')';
            } elseif ($ruleOperator == 'all' && $ruleValue = '1') {
                $query = implode(' AND ', $conditions);
            } elseif ($ruleOperator == 'all' && $ruleValue = '0') {
                $query = '-(' . implode(' OR ', $conditions) . ')';
            }
        }

        return $query;
    }
}