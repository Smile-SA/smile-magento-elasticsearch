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
        '=='  => '#{field}:"#{value}"',
        '!='  => '-(#{field}:"#{value}")',
        '>='  => '#{field}:[#{value} TO *]',
        '<='  => '#{field}:[* TO #{value}]',
        '>'   => '#{field}:{#{value} TO *]',
        '<'   => '#{field}:[* TO #{value}}',
        '{}'  => '#{field}:#{value}*',
        '!{}' => '#{field}:#{value}*'
    );

    /**
     * Default operator input by type map getter
     *
     * @return array
     */
    public function getDefaultOperatorInputByType()
    {
        if (null === $this->_defaultOperatorInputByType) {
            parent::getDefaultOperatorInputByType();
            $this->_defaultOperatorInputByType['multiselect'] = array('()', '!()');
        }

        return $this->_defaultOperatorInputByType;
    }

    /**
     * Load attribute options
     *
     * @return Mage_CatalogRule_Model_Rule_Condition_Product
     */
    public function loadAttributeOptions()
    {
        $attributes = array();
        $attributeLoader = Mage::getSingleton('smile_virtualcategories/rule_condition_product_attribute');
        $attributes = $attributeLoader->loadAttributeOptions();
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
        return $this->_getSearchQuery($this->getAttribute(), $this->getEscapedValue(), $this->getOperator(), $excludedCategories);
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
        if ($this->getRule()->getCategory() && $this->getRule()->getCategory()->getId()) {
            $excludedCategories[] = $this->getRule()->getCategory()->getId();
        }
        $query = false;
        if (strpos($value, ',') !== false) {
            $query  = array();
            $values = explode(',', $value);
            foreach ($values as $currentValue) {
                $subQuery = $this->_getCategoriesSearchQuery($currentValue, false, $excludedCategories);
                if (is_array($subQuery)) {
                    $query = array_merge($query, $subQuery);
                } else {
                    $query[] = $subQuery;
                }
            }
            if (!empty($query)) {
                $query = '(' . implode(' OR ', $query) . ')';
                $query = $not == true ? '-' . $query : $query;
            }
        } else {
            $category = Mage::getModel('catalog/category')
                ->setStoreId($this->getStore()->getId())
                ->load($value);
            if ($category->getId() && !in_array($value, $excludedCategories)) {
                $virtualRule = Mage::helper('smile_virtualcategories')->getVirtualRule($category);
                $virtualRule->setStore($this->getStore());
                $query = '(' . $virtualRule->getSearchQuery($excludedCategories) . ')';
                $this->getRule()->addUsedCategoryIds($virtualRule->getUsedCategoryIds());
            }
        }

        return $query;
    }

    /**
     * Create query for has_image filter.
     *
     * @param array $excludedCategories Categories that should not beein used during query building.
     *
     * @return string
     */
    protected function _getHasImageQuery($excludedCategories)
    {
        $value = 'no_selection';
        $attribute = 'image';
        $operator = '!=';
        return $this->_getSearchQuery($attribute, $value, $operator, $excludedCategories);
    }

    /**
     * Create query for is_new filter.
     *
     * @param array $excludedCategories Categories that should not beein used during query building.
     *
     * @return string
     */
    protected function _getIsNewQuery($excludedCategories)
    {
        $today = Mage::getSingleton('core/date')->gmtDate(Varien_Date::DATE_PHP_FORMAT);
        $parts = array(
            "(news_from_date:[* TO *] OR news_to_date:[* TO *])",
            "((-news_from_date:[* TO *]) OR news_from_date:[* TO $today])",
            "((-news_to_date:[* TO *]) OR news_to_date:[$today TO *])"
        );
        return '(' . implode(' AND ', $parts) . ')';
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
        } elseif ($attribute == 'has_image') {
            if ($value == 1 && $operator == "==") {
                $query = $this->_getHasImageQuery($excludedCategories);
            }
        } elseif ($attribute == 'is_new') {
            if ($value == 1 && $operator == "==") {
                $query = $this->_getIsNewQuery($excludedCategories);
            }
        } elseif ($attribute == 'has_discount') {
            if ($value == 1 && $operator == "==") {
                $attribute = $this->getFilterField($attribute);
                $query = $this->_getSearchQuery($attribute, $value, $operator, $excludedCategories);
            }
        } else if ($operator == '()' || $operator == '!()') {
            $attribute = $this->getFilterField($attribute);
            $template = $this->_queryTemplates['=='];
            $query = array();
            $values = is_array($value) ? $value : explode(',', $value);
            foreach ($values as $currentValue) {
                $query[] = $this->_getSearchQuery($attribute, $currentValue, "==", $excludedCategories);
            }
            if (!empty($query)) {
                $query = '(' . implode(' OR ', $query) . ')';

                if ($operator == '!()') {
                    $query = "(-${query})";
                }
            }

        } else {
            $template = $this->_queryTemplates[$operator];
            $attribute = $this->getFilterField($attribute);
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
     * Return the ES field name to build filter.
     *
     * @return string
     */
    public function getFilterField()
    {
        $fieldName = $this->getMapping()->getFieldName($this->getAttribute(), $this->getLocaleCode(), 'filter');

        if ($this->getAttribute() == 'price' || $this->getAttribute() == 'has_discount') {
            $store = $this->getStore();
            $websiteId = $store->getWebsiteId();
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            $fieldName = $this->getAttribute() . '_' . $customerGroupId . '_' . $websiteId;
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
        $specialAttributes = array('in_stock', 'has_image', 'has_discount', 'is_new');
        if (in_array($this->getAttribute(), $specialAttributes)) {
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
        $specialAttributes = array('in_stock', 'has_image', 'has_discount', 'is_new');
        if (in_array($this->getAttribute(), $specialAttributes)) {
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
        $specialAttributes = array('in_stock', 'has_image', 'has_discount', 'is_new');
        if (in_array($this->getAttribute(), $specialAttributes)) {
            $this->setData(
                'value_select_options',
                array(
                    array('value' => '1', 'label' => Mage::helper('adminhtml')->__('Yes'))
                )
            );

            $this->setData(
                'value_option',
                array('1' => Mage::helper('adminhtml')->__('Yes'))
            );
        } else {
            parent::_prepareValueOptions();
        }

        return $this;
    }

    /**
     * Retrieve the product mapping.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Product
     */
    public function getMapping()
    {
        $currentIndex = Mage::helper('catalogsearch')->getEngine()->getCurrentIndex();
        return $currentIndex->getMapping('product');
    }

    /**
     * Return locale code for the current store.
     *
     * @return string
     */
    public function getLocaleCode()
    {
        $store = $this->getStore();
        $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);
        return $languageCode;
    }

    /**
     * Get the current store.
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        $store = $this->getRule()->getStore();
        return $store;
    }
}