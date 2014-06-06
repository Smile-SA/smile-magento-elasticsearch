<?php
/**
 * Virtual categories product attributes filtering.
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
class Smile_VirtualCategories_Model_Rule_Condition_Product extends Mage_CatalogRule_Model_Rule_Condition_Product
{
    /**
     * Attribute data key that indicates whether it should be used for rules
     *
     * @var string
     */
    protected $_isUsedForRuleProperty = array('is_filterable', 'is_filterable_in_search');

    /**
     * Query templates for building Lucene queries from rule operators
     *
     * @var array
     */
    protected $_queryTemplates = array(
        '=='  => '#{field}:#{value}',
        '!='  => '-#{field}:#{value}',
        '>='  => '#{field}:[#{value} TO *]',
        '<='  => '#{field}:[* TO #{value}]',
        '>'   => '#{field}:{#{value} TO *]',
        '<'   => '#{field}:[* TO #{value}}',
        '{}'  => '#{field}:#{value}*',
        '!{}' => '#{field}:#{value}*'
    );

    /**
     * Load attribute options
     *
     * @return Mage_CatalogRule_Model_Rule_Condition_Product
     */
    public function loadAttributeOptions()
    {
        $productAttributes = Mage::getResourceSingleton('catalog/product')
            ->loadAllAttributes()
            ->getAttributesByCode();

        $attributes = array();
        foreach ($productAttributes as $attribute) {
            foreach ($this->_isUsedForRuleProperty as $usedField) {
                if (!$attribute->isAllowedForRuleCondition() || !$attribute->getDataUsingMethod($usedField)) {
                    continue;
                }
                $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
            }
        }

        $this->_addSpecialAttributes($attributes);

        asort($attributes);
        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Build search query for the rule.
     *
     * @param string $excludedCategories Categories that should not beein used during query building.
     *
     * @return string
     */
    public function getSearchQuery($excludedCategories)
    {
        return $this->_getSearchQuery($this->getFilterField(), $this->getEscapedValue(), $this->getOperator(), $excludedCategories);
    }

    /**
     * Build search query for the rule (category field implementation)
     *
     * @param string  $value              Filtered value.
     * @param boolean $not                Negative filter : select product not being into categories.
     * @param string  $excludedCategories Categories that should not beein used during query building.
     *
     * @return string
     */
    protected function _getCategoriesSearchQuery($value, $not = false, $excludedCategories = array())
    {
        $excludedCategories[] = $this->getRule()->getCategory()->getId();
        $query = false;
        if (!is_array($value) && strpos($value, ',') !== false) {
            $query  = array();
            $values = explode(',', $value);
            foreach ($values as $currentValue) {
                $subQuery = $this->_getCategoriesSearchQuery($value);
                if (is_array($subQuery)) {
                    $query = array_merge($query, $subQuery);
                }
            }
            if (!empty($query)) {
                $query = '(' . implode(' OR ', $query) . ')';
                $query = $not == true ? '-' . $query : $query;
            }
        } else {
            $category = Mage::getModel('catalog/category')->load($value);
            if ($category->getId() && !in_array($category->getId(), $excludedCategories)) {
                $query = '(' . $category->getVirtualRule()->getSearchQuery($excludedCategories) . ')';
            }
        }

        return $query;
    }

    /**
     * Build search query for the rule (category field implementation)
     *
     * @param string $attribute          Attribute to filter.
     * @param string $value              Filtered value.
     * @param string $operator           Comparaison operator.
     * @param string $excludedCategories Categories that should not beein used during query building.
     *
     * @return string
     */
    protected function _getSearchQuery($attribute, $value, $operator, $excludedCategories)
    {
        $query = false;
        if ($attribute == 'category_ids') {
            $query = $this->_getCategoriesSearchQuery($value, substr($operator, 0, 1) == '!', $excludedCategories);
        } else if ($operator == '()' || $operator == '!()') {
            $template = $this->_queryTemplates['=='];
            $query = array();
            $values = explode(',', $value);
            $values = array_diff($values, $excludedCategories);
            foreach ($values as $currentValue) {
                $query[] = $this->_getSearchQuery($attribute, $currentValue, "==");
            }
            if (!empty($query)) {
                $query = '(' . implode(' OR ', $query) . ')';

                if ($operator == '!()') {
                    $query = "(-${query})";
                }

            }

        } else {
            $template = $this->_queryTemplates[$operator];
            $query    = str_replace(array('#{field}', '#{value}'), array($attribute, $value), $template);
        }

        return $query;
    }

    /**
     * Escape value to be used into queries
     *
     * @return string
     */
    public function getEscapedValue()
    {
        $result = $this->getValue();
        $chars = array('\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '/');
        foreach ($chars as $char) {
            $result = str_replace($char, '\\' . $char, $result);
        }
        return $result;
    }

    /**
     * Add special attributes
     *
     * @param array &$attributes List of existing attributes
     *
     * @return void
     */
    protected function _addSpecialAttributes(array &$attributes)
    {
        $attributes['category_ids'] = Mage::helper('catalogrule')->__('Category');
        $attributes['in_stock'] = Mage::helper('cataloginventory')->__('In stock');
    }

    /**
     * Return the ES field name to build filter.
     *
     * @return string
     */
    public function getFilterField()
    {
        $fieldName = Mage::helper('smile_elasticsearch')->getAttributeFieldName($this->getAttribute(), null, 'facet');

        if ($this->getAttribute() == 'price') {
            $websiteId = Mage::app()->getStore()->getWebsiteId();
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            $fieldName = 'price_' . $customerGroupId . '_' . $websiteId;
        }

        return $fieldName;
    }

    /**
     * Input type for the current attribute.
     *
     * @return string
     */
    public function getInputType()
    {
        if ($this->getAttribute() == 'in_stock') {
            return 'select';
        }
        return parent::getInputType();
    }

    /**
     * Input type for the current attribute.
     *
     * @return string
     */
    public function getValueElementType()
    {
        if ($this->getAttribute() == 'in_stock') {
            return 'select';
        }
        return parent::getValueElementType();
    }

    /**
     * Attributes options
     *
     * @return Smile_VirtualCategories_Model_Rule_Condition_Product
     */
    protected function _prepareValueOptions()
    {
        if ($this->getAttribute() == 'in_stock') {
            $this->setData(
                'value_select_options',
                array(
                    array('value' => '1', 'label' => Mage::helper('adminhtml')->__('Yes')),
                    array('value' => '0', 'label' => Mage::helper('adminhtml')->__('No')),
                )
            );

            $this->setData(
                'value_option',
                array('1' => Mage::helper('adminhtml')->__('Yes'), '0' => Mage::helper('adminhtml')->__('No'))
            );
        } else {
            parent::_prepareValueOptions();
        }

        return $this;
    }
}