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
     * Retrieve items and transform the indexed value (attribute store label) to boolean Yes if needed
     *
     * @return array
     */
    public function getItems()
    {
        parent::getItems();

        $storeIds       = $this->getStoreId();
        $attributeModel = $this->getAttributeModel();
        $source         = $attributeModel->getSource();

        foreach ($this->_items as &$item) {
            if ($item->getLabel() == $this->getAttributeModel()->getStoreLabel($storeIds)) {
                $label = $source->getOptionText(Mage_Eav_Model_Entity_Attribute_Source_Boolean::VALUE_YES);
                $item->setLabel($label);
            }
        }

        return $this->_items;
    }
}