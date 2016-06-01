<?php
/**
 * Virtual attributes helper
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
 * @package   Smile_VirtualAttributes
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualAttributes_Helper_Data extends Mage_Core_Helper_Data
{
    /**
     * Local cache for loaded rules.
     *
     * @var array
     */
    protected $_attributesRulesCache = array();

    /**
     * Force the virtual rule to be loaded for an attribute.
     *
     * @param Mage_Eav_Model_Attribute $attribute  The attribute.
     * @param int                      $optionId   The option id.
     * @param array                    $arrayRule  The rule as array.
     *
     * @return Smile_VirtualCategories_Model_Rule
     */
    public function getFilterRule($attribute, $optionId, $arrayRule = null)
    {
        $cacheKey = $attribute->getId() . '_' . $optionId;

        if ($attribute->getStoreId()) {
            $cacheKey = $cacheKey . '_' . $attribute->getStoreId();
        }

        if (!isset($this->_attributesRulesCache[$cacheKey])) {

            if (is_null($arrayRule)) {
                $arrayRule = $attribute->getSource()->getOptionText($optionId);
            }

            if (is_string($arrayRule)) {
                $arrayRule = unserialize($arrayRule);
            }

            $virtualAttributeRule = Mage::getModel('smile_virtualattributes/rule');
            $virtualAttributeRule->getConditions()
                ->setConditions(array())
                ->loadArray($arrayRule);

            $this->_attributesRulesCache[$cacheKey] = $virtualAttributeRule;
        }

        $virtualRule = $this->_attributesRulesCache[$cacheKey];

        return $virtualRule;
    }

    /**
     * Check if a given attribute is one of our virtual types
     *
     * @param Mage_Eav_Model_Attribute $attribute The attribute
     *
     * @return bool
     */
    public function isVirtualAttribute($attribute)
    {
        $isVirtual = false;
        $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
        if (array_key_exists($attribute->getFrontendInput(), $attributes)) {
            $isVirtual = true;
        }

        return $isVirtual;
    }

    /**
     * Retrieve filter block (for Layer use) for a given virtual attribute
     *
     * @param Mage_Eav_Model_Attribute $attribute The attribute
     *
     * @return string
     */
    public function getFilterBlockName($attribute)
    {
        if ($this->isVirtualAttribute($attribute)) {
            $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
            $defaultFilterBlock = Smile_VirtualAttributes_Model_Catalog_Product_Attribute_Virtual::DEFAULT_FILTER_BLOCK;

            if (!isset($attributes[$attribute->getFrontendInput()]['filter_block']) ||
                is_null($attributes[$attribute->getFrontendInput()]['filter_block'])) {
                $attributes[$attribute->getFrontendInput()]['filter_block'] = $defaultFilterBlock;
            }

            $filterBlockName = $attributes[$attribute->getFrontendInput()]['filter_block'];

            return $filterBlockName;
        }

        Mage::throwException("Attribute {$attribute->getAttributeCode()} is not virtual.");
    }

    /**
     * Retrieve contributions instruction for a given attribute
     *
     * @param Mage_Eav_Model_Attribute $attribute The attribute
     *
     * @return string
     */
    public function getAttributeNotice($attribute)
    {
        $notice = null;

        if ($this->isVirtualAttribute($attribute)) {
            $attributes = Mage::getConfig()->getNode('global/virtual_attributes_types')->asArray();
            if (isset($attributes[$attribute->getFrontendInput()]['contribution_notice'])) {
                $notice = $this->__($attributes[$attribute->getFrontendInput()]['contribution_notice']);
            }
        }

        return $notice;
    }
}
