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
                /* @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
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
}