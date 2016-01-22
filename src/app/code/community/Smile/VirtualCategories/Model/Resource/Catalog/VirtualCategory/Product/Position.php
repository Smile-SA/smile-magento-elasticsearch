<?php
/**
 * Resource model to manage custom products positions into virtual categories
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_VirtualCategories
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Model_Resource_Catalog_VirtualCategory_Product_Position
    extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('smile_virtualcategories/category_product_position', 'category_id');
        $this->_isPkAutoIncrement = false;
    }

    /**
     * Save products positions for a given search category
     *
     * @param array                       $positions Products positions to save
     * @param Mage_Catalog_Model_Category $category  The concerned category
     *
     * @return Smile_VirtualCategories_Model_Resource_Catalog_VirtualCategory_Product_Position self reference
     */
    public function saveProductsPositions($positions, $category = null)
    {
        if ($category->getId()) {
            $categoryId = $category->getId();
            $storeId    = $category->getStoreId();

            $deleteCondition = array(
                "category_id = ?" => $categoryId,
                "store_id = ?"    => $storeId,
            );

            if (count($positions)) {
                $data = array();

                foreach ($positions as $productId => $position) {
                    $data[] = array(
                        "category_id" => $categoryId,
                        "product_id"  => $productId,
                        "store_id"    => $storeId,
                        "position"    => $position
                    );
                }

                $this->_getWriteAdapter()->insertOnDuplicate(
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
     * Retrieve all custom position for categories for a given product Id
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
            $storeIds = array_unique(array_map("intval", array(Mage_Core_Model_App::ADMIN_STORE_ID, $storeId)));
            $select->where('store_id IN (?)', $storeIds);
        }

        return $adapter->fetchAll($select);
    }

    /**
     * Retrieve product Ids associated with a given category
     *
     * @param Mage_Catalog_Model_Category $category The concerned category
     *
     * @return array
     */
    public function getProductIdsByCategory($category)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $adapter->select();

        $storeIds = array_unique(array_map("intval", array(Mage_Core_Model_App::ADMIN_STORE_ID, $category->getStoreId())));

        $select->from(array("main_table" => $this->getMainTable()), "product_id");
        $select->where("{$this->getIdFieldName()} = ?", $category->getId());
        $select->where('store_id IN (?)', $storeIds);

        return $adapter->fetchCol($select);
    }

    /**
     * Verify if a given category has custom positions defined for products
     *
     * @param Mage_Catalog_Model_Category $category The concerned category
     *
     * @return bool
     */
    public function hasCustomPositions($category)
    {
        $result = false;

        if ($category->getId()) {

            $adapter = $this->_getReadAdapter();
            $select  = $adapter->select();

            $storeIds = array_unique(array_map("intval", array(Mage_Core_Model_App::ADMIN_STORE_ID, $category->getStoreId())));

            $select->from(array("main_table" => $this->getMainTable()));
            $select->where('category_id = ?', (int) $category->getId());
            $select->where('store_id IN (?)', $storeIds);

            $result = ($adapter->fetchRow($select) !== false);
        }

        return $result;
    }
}