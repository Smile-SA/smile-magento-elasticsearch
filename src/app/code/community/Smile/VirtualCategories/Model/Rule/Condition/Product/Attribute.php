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
class Smile_VirtualCategories_Model_Rule_Condition_Product_Attribute
{
    /**
     * Catalog product entity type
     *
     * @var string
     */
    protected $_entityType = Mage_Catalog_Model_Product::ENTITY;

    /**
     * Attribute data key that indicates whether it should be used for rules
     *
     * @var string
     */
    protected $_isUsedForRuleProperty = array('is_filterable', 'is_filterable_in_search');

    /**
     * List of all attributes.
     *
     * @var array
     */
    protected $_attributes;

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
        $attributes['in_stock'] = Mage::helper('smile_virtualcategories')->__('Only in stock products');
        $attributes['has_image'] = Mage::helper('smile_virtualcategories')->__('Only products with images');
        $attributes['has_discount'] = Mage::helper('smile_virtualcategories')->__('Only discounted products');
        $attributes['is_new'] = Mage::helper('smile_virtualcategories')->__('Only new products');
    }

    /**
     * Load attribute options
     *
     * @return array
     */
    public function loadAttributeOptions()
    {
        if ($this->_attributes === null) {
            $this->_attributes = $this->_loadAttributeOptions();
        }
        return $this->_attributes;
    }

    /**
     * Load attribute options
     *
     * @return array
     */
    protected function _loadAttributeOptions()
    {
        $cacheKey = 'VIRT_CAT_ATTRIBUTES';
        $cacheData = Mage::app()->loadCache($cacheKey);
        $attributes = array();
        if ($cacheData) {
            $attributes = unserialize($cacheData);
        } else {
            $entityType = Mage::getModel('eav/entity_type')->loadByCode($this->_entityType);

            $productAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setEntityTypeFilter($entityType->getEntityTypeId());

            $conditions = array();
            foreach ($this->_isUsedForRuleProperty as $usedField) {
                $conditions[] = sprintf('additional_table.%s = 1', $usedField);
            }

            $productAttributes->getSelect()->where(sprintf('(%s)', implode(' OR ', $conditions)));
            $productAttributes = $productAttributes->getItems();

            $attributes = array();
            foreach ($productAttributes as $attribute) {
                $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
            }

            $this->_addSpecialAttributes($attributes);

            asort($attributes);
            $cacheData = serialize($attributes);
            Mage::app()->saveCache($cacheData, $cacheKey, array(Mage_Eav_Model_Attribute::CACHE_TAG), 7200);
        }

        return $attributes;
    }
}