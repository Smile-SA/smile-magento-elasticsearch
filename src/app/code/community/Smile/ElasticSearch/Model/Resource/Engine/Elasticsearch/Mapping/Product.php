<?php
/**
 * Abstract class that define product attributes mapping
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Product
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Catalog_Eav_Abstract
{

    protected $_attributeCollectionModel = 'catalog/product_attribute_collection';

    protected $_authorizedBackendModels = array(
        'catalog/product_attribute_backend_sku',
        'eav/entity_attribute_backend_array',
        'catalog/product_attribute_backend_price',
        'eav/entity_attribute_backend_time_created',
        'eav/entity_attribute_backend_time_updated',
        'catalog/product_attribute_backend_startdate',
        'catalog/product_attribute_backend_startdate_specialprice',
        'eav/entity_attribute_backend_datetime',
        'catalog/product_status',
        'catalog/visibility'
    );

    protected $_entityType = 'catalog_product';

    public function getMappingProperties($useCache = true)
    {
        parent::getMappingProperties(true);
        $this->_mapping['properties']['categories'] = array('type' => 'long');
        $this->_mapping['properties']['in_stock']   = array('type' => 'integer');
        return $this->_mapping;
    }

    protected function _getSearchableEntities($storeId, $ids = null, $lastId = 0, $limit = 100)
    {
        $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
        $adapter   = $this->getConnection();

        $select = $adapter->select()
        ->useStraightJoin(true)
        ->from(
            array('e' => $this->getTable('catalog/product'))
        )
        ->join(
            array('website' => $this->getTable('catalog/product_website')),
            $adapter->quoteInto(
                'website.product_id=e.entity_id AND website.website_id=?',
                $websiteId
            ),
            array()
        )
        ->joinLeft(
            array('stock_status' => $this->getTable('cataloginventory/stock_status')),
            $adapter->quoteInto(
                'stock_status.product_id=e.entity_id AND stock_status.website_id=?',
                $websiteId
            ),
            array('in_stock' => new Zend_Db_Expr("COALESCE(stock_status, 0)"))
        );

        if (!is_null($ids)) {
            $select->where('e.entity_id IN(?)', $ids);
        }

        $select->where('e.entity_id>?', $lastId)
               ->limit($limit)
               ->order('e.entity_id');

        /**
         * Add additional external limitation
        */
        $eventName = sprintf('prepare_catalog_%s_index_select', $this->_type);
        Mage::dispatchEvent(
            $eventName,
            array(
                'select'        => $select,
                'entity_field'  => new Zend_Db_Expr('e.entity_id'),
                'website_field' => new Zend_Db_Expr('website.website_id'),
                'store_field'   => $storeId
            )
        );

        $result = $adapter->fetchAll($select);

        return $result;
    }

    protected function _saveIndexes($storeId, $entityIndexes)
    {
        $productIds = array_keys($entityIndexes);
        $entityIndexes = Mage::getResourceSingleton('smile_elasticsearch/engine_index')->addAdvancedIndex($entityIndexes, $storeId, $productIds);
        return parent::_saveIndexes($storeId, $entityIndexes);
    }


    public function getSearchFields($localeCode) {

        if ($this->_searchFields == null) {

            $mapping = $this->getMappingProperties();
            $this->_searchFields = array();

            $entityType = Mage::getModel('eav/entity_type')->loadByCode($this->_entityType);

            $attributes = Mage::getResourceModel($this->_attributeCollectionModel)
                ->setEntityTypeFilter($entityType->getEntityTypeId());

            foreach ($attributes as $attribute) {
                if ($attribute->getIsSearchable()) {
                    $field = $this->getFieldName($attribute->getAttributeCode(), $localeCode);
                    if ($field !== false) {
                        if ($attribute->getSearchWeight()) {
                            $field .= '^' . $attribute->getSearchWeight();
                        }
                        $this->_searchFields[] = $field;
                    }
                }
            }
        }

        return $this->_searchFields;
    }

    protected function _getChildrenIds($entityIds, $websiteId = null)
    {
        $children = array();
        $productTypes = array_keys(Mage::getModel('catalog/product_type')->getOptionArray());
        foreach ($productTypes as $productType) {

            $productEmulator = new Varien_Object();
            $productEmulator->setIdFieldName('entity_id');
            $productEmulator->setTypeId($productType);
            $typeInstance = Mage::getSingleton('catalog/product_type')->factory($productEmulator);
            $relation = $typeInstance->isComposite() ? $typeInstance->getRelationInfo() : false;

            if ($relation && $relation->getTable() && $relation->getParentFieldName() && $relation->getChildFieldName()) {

                $select = $this->getConnection()
                    ->select()
                    ->from(
                        array('main' => $this->getTable($relation->getTable())),
                        array($relation->getParentFieldName(), $relation->getChildFieldName()))
                    ->where("main.{$relation->getParentFieldName()} in (?)", $entityIds);

                    if (!is_null($relation->getWhere())) {
                        $select->where($relation->getWhere());
                    }

                Mage::dispatchEvent('prepare_product_children_id_list_select', array(
                    'select'        => $select,
                    'entity_field'  => 'main.product_id',
                    'website_field' => $websiteId
                ));

                $data = $this->getConnection()->fetchAll($select);

                foreach ($data as $link) {
                    $parentId = $link[$relation->getParentFieldName()];
                    $childId  = $link[$relation->getChildFieldName()];
                    if (!isset($children[$parentId])) {
                    	$children[$parentId] = array();
                    }
                    $children[$parentId][] = $childId;
                }
            }
        }

        return $children;
    }
}