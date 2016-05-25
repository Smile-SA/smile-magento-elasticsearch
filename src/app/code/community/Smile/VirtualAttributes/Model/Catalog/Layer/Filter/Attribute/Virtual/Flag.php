<?php
/**
 * Default filter model for virtual Flag attributes
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
class Smile_VirtualAttributes_Model_Catalog_Layer_Filter_Attribute_Virtual_Flag
    extends Smile_VirtualAttributes_Model_Catalog_Layer_Filter_Attribute_Virtual
{
    /**
     * Create filter item object and transform the option numeric value to boolean label
     *
     * @param string $label Label of the filter value
     * @param mixed  $value Value of the filter
     * @param int    $count Number of result (default is 0)
     *
     * @return Mage_Catalog_Model_Layer_Filter_Item
     */
    protected function _createItem($label, $value, $count = 0)
    {
        $booleanSource  = Mage::getModel("eav/entity_attribute_source_boolean");

        if (is_numeric($label)) {
            $label = $booleanSource->getOptionText(Mage_Eav_Model_Entity_Attribute_Source_Boolean::VALUE_YES);
        }

        return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute::_createItem($label, $value, $count);
    }
}
