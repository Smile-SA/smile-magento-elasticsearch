<?php
/**
 * Resource model dedicated to custom positions applied to products for search terms
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Resource_Search_Term_Product_Position extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('smile_elasticsearch/search_term_product_position', 'query_id');
        $this->_isPkAutoIncrement = false;
    }

    /**
     * Save products positions for a given search query
     *
     * @param array                               $positions Products positions to save
     * @param Mage_CatalogSearch_Model_Query|null $query     The concerned search query
     *
     * @return Smile_ElasticSearch_Model_Resource_Search_Term_Product_Position self reference
     */
    public function saveProductsPositions($positions, $query = null)
    {
        if ($query->getId()) {
            $queryId = $query->getId();

            $deleteCondition = array("query_id = ?" => $queryId);

            if (count($positions)) {
                $data = array();

                foreach ($positions as $productId => $position) {
                    $data[] = array(
                        "query_id"   => $queryId,
                        "product_id" => $productId,
                        "position"   => $position
                    );
                }

                $this->_getWriteAdapter()
                    ->insertOnDuplicate(
                        $this->getMainTable(),
                        $data,
                        array_keys(current($data))
                    );

                $deleteCondition["product_id NOT IN (?)"] = array_keys($positions);
            }

            $this->_getWriteAdapter()->delete(
                $this->getMainTable(),
                $deleteCondition
            );
        }
    }

    /**
     * Retrieve all custom position for search terms for a given product Id
     *
     * @param array $productIds The product Ids
     * @param int   $storeId    The store Id
     *
     * @return array
     */
    public function getByProductIds($productIds, $storeId = null)
    {
        $adapter = $this->_getReadAdapter();

        if (!is_array($productIds)) {
            $productIds = array($productIds);
        }

        $select = $this->_getReadAdapter()->select();

        $select->from(array("main_table" => $this->getMainTable()));
        $select->where('product_id IN (?)', array_map("intval", $productIds));

        if (!is_null($storeId)) {
            $select->joinInner(
                array('csq' => $this->getTable('catalogsearch/search_query')),
                'main_table.query_id = csq.query_id',
                array()
            );
            $select->where('csq.store_id = ?', (int) $storeId);
        }

        return $adapter->fetchAll($select);
    }

    /**
     * Retrieve product Ids associated with a given query
     *
     * @param Mage_CatalogSearch_Model_Query $query The Query
     *
     * @return array
     */
    public function getProductIdsByQuery($query)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select();

        $select->from(array("main_table" => $this->getMainTable()), "product_id");
        $select->where("{$this->getIdFieldName()} = ?", $query->getId());

        return $adapter->fetchCol($select);
    }

    /**
     * Verify if a given search query has custom positions defined for products
     *
     * @param Mage_CatalogSearch_Model_Query|int $query The concerned search query
     *
     * @return bool
     */
    public function hasCustomPositions($query)
    {
        $result = false;

        if ($query->getId()) {

            $adapter = $this->_getReadAdapter();
            $select  = $adapter->select();

            $select->from(array("main_table" => $this->getMainTable()));
            $select->where('query_id = ?', (int) $query->getId());

            $result = ($adapter->fetchRow($select) !== false);
        }

        return $result;
    }
}