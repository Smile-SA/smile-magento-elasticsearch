<?php
/**
 * Optimizer collection implementation
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
class Smile_SearchOptimizer_Model_Resource_Optimizer_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('smile_searchoptimizer/optimizer');
        $this->_map['fields']['store'] = 'store_table.store_id';
        $this->_map['fields']['query_type'] = 'querytype_table.query_type';
    }

    /**
     * Add filter by store
     *
     * @param int|Mage_Core_Model_Store $store     Store you want otimizer list for.
     * @param bool                      $withAdmin Include admin list.
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer_Collection
     */
    public function addStoreFilter($store, $withAdmin = true)
    {
        if ($store instanceof Mage_Core_Model_Store) {
            $store = array($store->getId());
        }

        if (!is_array($store)) {
            $store = array($store);
        }

        if ($withAdmin) {
            $store[] = Mage_Core_Model_App::ADMIN_STORE_ID;
        }

        $this->addFilter('store', array('in' => $store), 'public');

        return $this;
    }

    /**
     * Add query type filer
     *
     * @param string $queryType Query type code
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer_Collection
     */
    public function addQueryTypeFilter($queryType)
    {
        if (!is_array($queryType)) {
            $queryType = array($queryType);
        }
        $this->addFilter('query_type', array('in' => $queryType));
        return $this;
    }

    /**
     * Get SQL for get record count.
     * Extra GROUP BY strip added.
     *
     * @return Varien_Db_Select
     */
    public function getSelectCountSql()
    {
        $countSelect = parent::getSelectCountSql();

        $countSelect->reset(Zend_Db_Select::GROUP);

        return $countSelect;
    }

    /**
     * Join store relation table if there is store filter
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer_Collection Self reference
     */
    protected function _renderFiltersBefore()
    {
        if ($this->getFilter('store')) {
            $this->getSelect()->join(
                array('store_table' => $this->getTable('smile_searchoptimizer/optimizer_store'), 'store_id'),
                'main_table.optimizer_id = store_table.optimizer_id',
                array()
            )->group('main_table.optimizer_id');

            /*
             * Allow analytic functions usage because of one field grouping
            */
            $this->_useAnalyticFunction = true;
        }
        if ($this->getFilter('query_type')) {
            $this->getSelect()
                ->join(
                    array('querytype_table' => $this->getTable('smile_searchoptimizer/optimizer_querytype'), array('query_type')),
                    'main_table.optimizer_id = querytype_table.optimizer_id',
                    array()
                )
                ->group('main_table.optimizer_id');

            $this->_useAnalyticFunction = true;
        }
        return parent::_renderFiltersBefore();
    }

    /**
     * Ensure config is unserialized after load.
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer_Collection Self reference
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        $this->walk('afterLoad');
        return $this;
    }

    /**
     * Returns only active optimizers.
     *
     * @param string|Zend_Date $date Date the filter need to be active (UTC).
     *
     * @return Smile_SearchOptimizer_Model_Resource_Optimizer_Collection Self reference
     */
    public function addIsActiveFilter($date = null)
    {

        $this->addFieldToFilter('is_active', true);

        if (is_null($date)) {
            $date = Mage::getModel('core/date')->date('Y-m-d');
        }

        $this->getSelect()
            ->where('from_date is null or from_date <= ?', $date)
            ->where('to_date is null or to_date >= ?', $date);

        return $this;
    }
}