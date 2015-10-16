<?php
/**
 * Virtual categories config backend storage.
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
class Smile_VirtualCategories_Model_Category_Attributes_Backend_Virtual extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Serializes the virtual category configuration before it will be saved.
     *
     * @param Mage_Catalog_Model_Category $object Category saved.
     *
     * @return Smile_VirtualCategories_Model_Category_Attributes_Backend_Virtual
     */
    public function beforeSave($object)
    {

        $attributeCode = $this->getAttribute()->getName();
        $savedValue = $this->_defaultValue;

        $savedValue['is_virtual'] = (bool) $object->getIsVirtual();

        if ($object->getVirtualCategoryRule()) {
            $savedValue['rule_serialized'] = $object->getVirtualCategoryRule()->getConditions()->asArray();
        }

        $object->setData($attributeCode, serialize($savedValue));

        return $this;
    }

    /**
     * Unserializes the virtual category configuration after it has been loaded.
     *
     * @param Mage_Catalog_Model_Category $object Category saved.
     *
     * @return Smile_VirtualCategories_Model_Category_Attributes_Backend_Virtual
     */
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getName();
        $data = $object->getData($attributeCode);

        if ($data && is_string($data) && strlen($data)) {
            $data = unserialize($data);
        } else {
            $data = $this->_defaultValue;
        }

        $virtualCategoryRule = Mage::getModel('smile_virtualcategories/rule');
        if (isset($data['rule_serialized'])) {
            $virtualCategoryRule->getConditions()
                ->setConditions(array())
                ->loadArray($data['rule_serialized']);
        }

        if ($object->getStoreId()) {
            $virtualCategoryRule->setStoreId($object->getStoreId());
        }

        $virtualCategoryRule->setCategory($object);

        $object->setData('is_virtual', $data['is_virtual']);
        $object->setData('virtual_rule', $virtualCategoryRule);

        return $this;
    }


    /**
     * Get attribute instance
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getAttribute()
    {
        if (is_null($this->_attribute)) {
            $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_category', 'virtual_category');
            $this->setAttribute($attribute);
        }
        return parent::getAttribute();
    }
}