<?php
/**
 * Handles boolean attribute filtering in layered navigation.
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
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Boolean extends Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
{
    /**
     * Indicates if the filters has more value than what have been currently fetch.
     *
     * @return boolean
     */
    public function hasOthers()
    {
        return false;
    }

    /**
     * Create filter item object and transform the option numeric value to boolean label
     *
     * @param string $label Label of the filter value
     * @param mixed  $value Value of the filter
     * @param int    $count Number of result (default is 0)
     *
     * @return Mage_Catalog_Model_Layer_Filter_Item
     */
    protected function _createItem($label, $value, $count=0)
    {
        $attributeModel = $this->getAttributeModel();
        $source         = $attributeModel->getSource();

        if (is_numeric($label)) {
            $label = $source->getOptionText((int) $value);
        }

        return parent::_createItem($label, $value, $count);
    }

    /**
     * Returns attribute field name.
     * Booleans are not processed on options_ field
     *
     * @return string
     */
    protected function _getFilterField()
    {
        $attribute = $this->getAttributeModel();
        $fieldName = $attribute->getAttributeCode();

        return $fieldName;
    }
}