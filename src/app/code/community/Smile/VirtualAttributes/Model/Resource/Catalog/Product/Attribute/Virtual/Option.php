<?php
/**
 * Virtual attributes option value
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
class Smile_VirtualAttributes_Model_Resource_Catalog_Product_Attribute_Virtual_Option extends Mage_Eav_Model_Resource_Entity_Attribute_Option
{
    /**
     * Resource initialization
     */
    protected function _construct()
    {
        $this->_init('smile_virtualattributes/attribute_option_value', 'value_id');
    }

    /**
     * Add Join with option value for collection select
     *
     * @TODO build it with ES ?
     *
     * @param Mage_Eav_Model_Entity_Collection_Abstract $collection
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param Zend_Db_Expr $valueExpr
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Option
     */
    public function addOptionValueToCollection($collection, $attribute, $valueExpr)
    {
        return parent::addOptionValueToCollection($collection, $attribute, $valueExpr);
    }
}
