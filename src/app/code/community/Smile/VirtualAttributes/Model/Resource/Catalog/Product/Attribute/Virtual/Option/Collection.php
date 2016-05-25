<?php
/**
 * Virtual attributes options collection
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
class Smile_VirtualAttributes_Model_Resource_Catalog_Product_Attribute_Virtual_Option_Collection
    extends Mage_Eav_Model_Resource_Entity_Attribute_Option_Collection
{
    /**
     * Resource initialization
     */
    protected function _construct()
    {
        $this->_init('eav/entity_attribute_option');
        $this->_optionValueTable = Mage::getSingleton('core/resource')
            ->getTableName('smile_virtualattributes/attribute_option_value');
    }

    /**
     * Convert collection items to select options array
     *
     * @return array
     */
    public function toOptionArray($valueKey = 'value')
    {
        $result = $this->_toOptionArray('option_id', 'label', array('rule' => $valueKey));

        return $result;
    }

    /**
     * Add store filter to collection
     *
     * @param int     $storeId         The store Id
     * @param boolean $useDefaultValue The default value
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Option_Collection
     */
    public function setStoreFilter($storeId = null, $useDefaultValue = true)
    {
        if (is_null($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }
        $adapter = $this->getConnection();

        $joinCondition = $adapter->quoteInto('tsv.option_id = main_table.option_id AND tsv.store_id = ?', $storeId);

        if ($useDefaultValue) {
            $this->getSelect()
                ->join(
                    array('tdv' => $this->_optionValueTable),
                    'tdv.option_id = main_table.option_id',
                    array('default_value' => 'value', 'label' => 'label'))
                ->joinLeft(
                    array('tsv' => $this->_optionValueTable),
                    $joinCondition,
                    array(
                        'store_default_value' => 'value',
                        'value'               => $adapter->getCheckSql('tsv.value_id > 0', 'tsv.value', 'tdv.value'),
                    )
                )
                ->where('tdv.store_id = ?', 0);
        } else {
            $this->getSelect()
                ->joinLeft(
                    array('tsv' => $this->_optionValueTable),
                    $joinCondition,
                    array('value', 'label')
                )
                ->where('tsv.store_id = ?', $storeId);
        }

        $this->setOrder('value', self::SORT_ORDER_ASC);

        return $this;
    }
}
