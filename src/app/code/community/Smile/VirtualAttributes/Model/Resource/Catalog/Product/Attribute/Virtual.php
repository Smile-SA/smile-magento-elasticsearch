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
class Smile_VirtualAttributes_Model_Resource_Catalog_Product_Attribute_Virtual
    extends Mage_Eav_Model_Resource_Entity_Attribute
{
    protected $_optionTable;
    protected $_optionValueTable;

    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_optionTable      = $this->getTable('eav/attribute_option');
        $this->_optionValueTable = $this->getTable('smile_virtualattributes/attribute_option_value');
    }

    /**
     * Save options for a virtual attribute
     *
     * @param Mage_Eav_Model_Entity_Attribute $attribute The attribute being saved
     * @param array                           $options   The options
     */
    public function saveOptions($attribute, $options)
    {
        if (is_array($options)) {
            $attribute->setOption($options);
            $this->_saveOption($attribute);
            $attribute->setOption(null);
        }
    }

    /**
     *  Save attribute options
     *
     * @param Mage_Eav_Model_Entity_Attribute $object The attribute being saved
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute
     */
    protected function _saveOption(Mage_Core_Model_Abstract $object)
    {
        $option = $object->getOption();
        if (is_array($option)) {
            if (isset($option['value'])) {
                $this->_saveOptionValue($object, $option);
            }
        }

        return $this;
    }

    /**
     * Process save of an option value
     *
     * @param Mage_Eav_Model_Attribute $object The attribute
     * @param array                    $option The option value
     *
     * @throws \Mage_Core_Exception
     */
    protected function _saveOptionValue($object, $option)
    {
        $adapter     = $this->_getWriteAdapter();
        $stores      = Mage::app()->getStores(true);
        $optionValue = $option['value'];

        foreach ($optionValue as $optionId => $values) {

            $intOptionId = (int) $optionId;
            if (!empty($option['delete'][$optionId])) {
                if ($intOptionId) {
                    $adapter->delete(
                        $this->_optionTable,
                        array('option_id =?' => $intOptionId)
                    );
                }
                continue;
            }

            $sortOrder = $this->_getSortOrder($optionId, $option);
            if (!$intOptionId) {
                $data = array(
                    'attribute_id'  => $object->getId(),
                    'sort_order'    => $sortOrder
                );
                $adapter->insert($this->_optionTable, $data);
                $intOptionId = $adapter->lastInsertId($this->_optionTable);
            } else {
                $data  = array('sort_order' => $sortOrder);
                $where = array('option_id =?' => $intOptionId);
                $adapter->update($this->_optionTable, $data, $where);
            }

            // Default value
            if (!isset($values[0])) {
                Mage::throwException(Mage::helper('eav')->__('Default option value is not defined'));
            }

            if ($intOptionId) {
                $adapter->delete(
                    $this->_optionValueTable,
                    ['option_id =?' => $intOptionId]
                );
            }

            foreach ($stores as $store) {
                $this->_saveStoreOption($store, $intOptionId, $values);
            }
        }
    }

    /**
     * Retrieve sort order for a given option.
     *
     * @param int   $optionId The option Id being saved
     * @param array $option   The option
     *
     * @return int
     */
    protected function _getSortOrder($optionId, $option)
    {
        $sortOrder = !empty($option['order'][$optionId]) ? $option['order'][$optionId] : 0;

        return $sortOrder;
    }

    /**
     * Save an option for a given store.
     *
     * @param Mage_Core_Model_Store $store    The store we are saving values for
     * @param int                   $optionId The option Id being saved
     * @param array                 $values   The values
     */
    protected function _saveStoreOption($store, $optionId, $values)
    {
        $adapter = $this->_getWriteAdapter();

        if (isset($values[$store->getId()])
            && (!empty($values[$store->getId()])
                || $values[$store->getId()] == "0")
        ) {
            $data = array(
                'option_id'    => $optionId,
                'store_id'     => $store->getId(),
                'value'        => $values[$store->getId()]["rule"],
                'label'        => $values[$store->getId()]["label"],
            );

            $adapter->insert($this->_optionValueTable, $data);
        }
    }
}
