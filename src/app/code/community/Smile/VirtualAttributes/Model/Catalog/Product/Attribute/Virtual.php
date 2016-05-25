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
abstract class Smile_VirtualAttributes_Model_Catalog_Product_Attribute_Virtual extends Mage_Core_Model_Abstract
{
    /**
     * Default source model for virtual attributes
     */
    const DEFAULT_SOURCE = "smile_virtualattributes/catalog_product_attribute_source_virtual";

    /**
     * Default filter block for virtual attributes
     */
    const DEFAULT_FILTER_BLOCK = "smile_virtualattributes/catalog_layer_filter_attribute_virtual";

    /**
     * Default frontend model for virtual attributes : does nothing, as they should not be editable on product level.
     */
    const DEFAULT_FRONTEND_MODEL = "Varien_Data_Form_Element_Hidden";

    /**
     * Process save operation for an attribute
     * This is called by Adminhtml/Observer when saving an attribute
     * This function will format our attribute properly before saving it
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute the attribute to build
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    abstract public function processAttributeSave($attribute);

    /**
     * Called when deleting an attribute
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute the attribute that has been deleted
     *
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function processDeletion($attribute)
    {
        return $attribute;
    }
}


