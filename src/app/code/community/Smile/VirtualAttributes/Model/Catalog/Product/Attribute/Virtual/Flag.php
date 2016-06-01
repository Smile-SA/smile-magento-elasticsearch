<?php
/**
 * Virtual attributes abstract Model
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
class Smile_VirtualAttributes_Model_Catalog_Product_Attribute_Virtual_Flag
    extends Smile_VirtualAttributes_Model_Catalog_Product_Attribute_Virtual
{
    /**
     * Initialize resources
     */
    protected function _construct()
    {
        $this->_init('smile_virtualattributes/catalog_product_attribute_virtual_flag');
    }

    /**
     * Process save operation for an attribute
     * This is called by Adminhtml/Observer when saving an attribute
     * This function will format our attribute properly before saving it
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute the attribute to build
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function processAttributeSave($attribute)
    {
        $rule = Mage::app()->getRequest()->getPost('rule', false);

        if ($rule) {
            $ruleInstance = Mage::getModel('smile_virtualcategories/rule')->loadPost($rule);
            $ruleAsArray  = $ruleInstance->getConditions()->asArray();
            $ruleOptionId = Mage::app()->getRequest()->getPost("option_id", null);

            $storeId = 0; // Default to all values;

            $options = array(
                "value" => array(
                    $ruleOptionId => array(
                        $storeId => array(
                            "label" => Mage_Eav_Model_Entity_Attribute_Source_Boolean::VALUE_YES,
                            "rule"  => serialize($ruleAsArray)
                        )
                    )
                )
            );

            $this->getResource()->saveOptions($attribute, $options);
        }
    }
}


