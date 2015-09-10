<?php
/**
 * Optimizer resource model implementation
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
 * @package   Smile_SearchOptimizer
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2014 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Model_Resource_Optimizer extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('smile_searchoptimizer/optimizer', 'optimizer_id');
    }


    /**
     * Perform operations after object save
     *
     * @param Mage_Core_Model_Abstract $object Object saved
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer
     */
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $oldStores = $this->lookupStoreIds($object->getId());
        $newStores = (array) $object->getStores();

        $table  = $this->getTable('smile_searchoptimizer/optimizer_store');
        $insert = array_diff($newStores, $oldStores);
        $delete = array_diff($oldStores, $newStores);

        if ($delete) {
            $where = array(
                'optimizer_id = ?' => (int) $object->getId(),
                'store_id IN (?)'  => $delete
            );

            $this->_getWriteAdapter()->delete($table, $where);
        }

        if ($insert) {
            $data = array();

            foreach ($insert as $storeId) {
                $data[] = array(
                    'optimizer_id' => (int) $object->getId(),
                    'store_id'     => (int) $storeId
                );
            }

            $this->_getWriteAdapter()->insertMultiple($table, $data);
        }
        $this->_updateQueryType($object);

        return parent::_afterSave($object);
    }

    /**
     * Update the query type join table on object save.
     *
     * @param Mage_Core_Model_Abstract $object Object saved
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer
     */
    protected function _updateQueryType($object)
    {
        $oldTypes = $this->lookupQueryTypeIds($object->getId());
        $newTypes = (array) $object->getQueryType();

        $table  = $this->getTable('smile_searchoptimizer/optimizer_querytype');
        $insert = array_diff($newTypes, $oldTypes);
        $delete = array_diff($oldTypes, $newTypes);

        if ($delete) {
            $where = array(
                'optimizer_id = ?' => (int) $object->getId(),
                'query_type IN (?)'  => $delete
            );

            $this->_getWriteAdapter()->delete($table, $where);
        }

        if ($insert) {
            $data = array();

            foreach ($insert as $queryType) {
                $data[] = array(
                    'optimizer_id' => (int) $object->getId(),
                    'query_type'     => $queryType
                );
            }

            $this->_getWriteAdapter()->insertMultiple($table, $data);
        }

        return $this;
    }

    /**
     * Perform operations after object load
     *
     * @param Mage_Core_Model_Abstract $object Object loaded
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer
     */
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());
            $object->setData('store_id', $stores);
            $object->setData('stores', $stores);
            $queryType = $this->lookupQueryTypeIds($object->getId());
            $object->setData('query_type', $queryType);
        }

        return parent::_afterLoad($object);
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string                   $field  Field to be filtered
     * @param mixed                    $value  Value to be filtered
     * @param Mage_Core_Model_Abstract $object Object to be loaded
     *
     * @return Zend_Db_Select
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if ($object->getStoreId()) {
            $stores = array(
                (int) $object->getStoreId(),
                Mage_Core_Model_App::ADMIN_STORE_ID,
            );

            $select->join(
                array('os' => $this->getTable('smile_searchoptimizer/optimizer_store')),
                $this->getMainTable().'.optimizer_id = os.optimizer_id',
                array('store_id')
            );

            $select->where('is_active = ?', 1)
                ->where('os.store_id in (?) ', $stores)
                ->order('store_id DESC')
                ->limit(1);
        }

        return $select;
    }

    /**
     * Get store ids to which specified item is assigned
     *
     * @param int $id Id of the optmizer
     *
     * @return array
     */
    public function lookupStoreIds($id)
    {
        $adapter = $this->_getReadAdapter();

        $select  = $adapter->select()
            ->from($this->getTable('smile_searchoptimizer/optimizer_store'), 'store_id')
            ->where('optimizer_id = :optimizer_id');

        $binds = array(
            ':optimizer_id' => (int) $id
        );

        return $adapter->fetchCol($select, $binds);
    }

    /**
     * Get query types to which specified item is assigned
     *
     * @param int $id Id of the optmizer
     *
     * @return array
     */
    public function lookupQueryTypeIds($id)
    {
        $adapter = $this->_getReadAdapter();

        $select  = $adapter->select()
            ->from($this->getTable('smile_searchoptimizer/optimizer_querytype'), 'query_type')
            ->where('optimizer_id = :optimizer_id');

        $binds = array(
            ':optimizer_id' => (int) $id
        );

        return $adapter->fetchCol($select, $binds);
    }
}