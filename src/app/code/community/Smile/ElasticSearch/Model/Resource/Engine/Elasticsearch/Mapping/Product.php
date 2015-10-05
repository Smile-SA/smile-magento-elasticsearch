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

    /**
     * Model used to read attributes configuration.
     *
     * @var string
     */
    protected $_attributeCollectionModel = 'catalog/product_attribute_collection';

    /**
     * List of backends authorized for indexing.
     *
     * @var array
     */
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

    /**
     * Product entity type code.
     *
     * @var string
     */
    protected $_entityType = 'catalog_product';

    /**
     * Get mapping properties as stored into the index
     *
     * @return array
     */
    protected function _getMappingProperties()
    {
        $mapping = parent::_getMappingProperties(true);
        $mapping['properties']['categories'] = array('type' => 'long', 'fielddata' => array('format' => 'doc_values'));
        $mapping['properties']['show_in_categories'] = array('type' => 'long', 'fielddata' => array('format' => 'doc_values'));
        $mapping['properties']['in_stock']   = array('type' => 'integer', 'fielddata' => array('format' => 'doc_values'));

        foreach ($this->_stores as $store) {
            $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);
            $fieldMapping = $this->_getStringMapping('category_name_' . $languageCode, $languageCode, 'string', true, true, true);
            $mapping['properties'] = array_merge($mapping['properties'], $fieldMapping);
        }

        $mapping['properties']['category_position'] = array(
            'type' => 'nested',
            'properties' => array(
                'category_id' => array('type' => 'long', 'fielddata' => array('format' => 'doc_values')),
                'position'    => array('type' => 'long', 'fielddata' => array('format' => 'doc_values'))
            )
        );

        // Append dynamic mapping for product prices and discount fields
        $fieldTemplate = array(
            'match' => 'price_*', 'mapping' => array('type' => 'double', 'fielddata' => array('format' => 'doc_values'))
        );
        $mapping['dynamic_templates'][] = array('prices' => $fieldTemplate);

        $fieldTemplate = array(
            'match' => 'has_discount_*', 'mapping' => array('type' => 'boolean', 'fielddata' => array('format' => 'doc_values'))
        );
        $mapping['dynamic_templates'][] = array('has_discount' => $fieldTemplate);

        $mappingObject = new Varien_Object($mapping);
        Mage::dispatchEvent('search_engine_product_mapping_properties', array('mapping' => $mappingObject));

        return $mappingObject->getData();
    }

    /**
     * Retrive a bucket of indexable entities.
     *
     * @param int         $storeId Store id
     * @param string|null $ids     Ids filter
     * @param int         $lastId  First id
     *
     * @return array
     */
    protected function _getSearchableEntities($storeId, $ids = null, $lastId = 0)
    {
        $limit = $this->_getBatchIndexingSize();

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
                array('in_stock' => new Zend_Db_Expr("COALESCE(stock_status.stock_status, 0)"))
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

    /**
     * Save docs to the index
     *
     * @param int   $storeId       Store id
     * @param array $entityIndexes Doc values.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Catalog_Eav_Abstract
     */
    protected function _saveIndexes($storeId, $entityIndexes)
    {
        $index = Mage::getResourceSingleton('smile_elasticsearch/engine_index');
        $entityIndexes = $index->addAdvancedIndex($entityIndexes, $storeId);
        $store = Mage::app()->getStore($storeId);
        $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);

        foreach ($entityIndexes as &$entityIndex) {
            if (isset($entityIndex['category_name'])) {
                $entityIndex['category_name_' . $languageCode] = $entityIndex['category_name'];
                unset($entityIndex['category_name']);
            }
        }

        return parent::_saveIndexes($storeId, $entityIndexes);
    }

    /**
     * Retrieve entities children ids (simple products for configurable, grouped and bundles).
     *
     * @param array $entityIds Parent entities ids.
     * @param int   $websiteId Current website ids
     *
     * @return array
     */
    protected function _getChildrenIds($entityIds, $websiteId)
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
                        array($relation->getParentFieldName(), $relation->getChildFieldName())
                    )
                    ->where("main.{$relation->getParentFieldName()} in (?)", $entityIds);

                if (!is_null($relation->getWhere())) {
                    $select->where($relation->getWhere());
                }

                Mage::dispatchEvent(
                    'prepare_product_children_id_list_select',
                    array('select' => $select, 'entity_field' => 'main.product_id', 'website_field' => $websiteId)
                );

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

    /**
     * Return a list of all searchable field for the current type (by locale code).
     *
     * @param string $languageCode Language code.
     * @param string $searchType   Type of search currentlty used.
     * @param string $analyzer     Allow to force the analyzer used for the field (shingle, ...).
     *
     * @return array.
     */
    public function getSearchFields($languageCode, $searchType = null, $analyzer = null)
    {
        $searchFields[$searchType] = parent::getSearchFields($languageCode, $searchType, $analyzer);
        $advancedSettingsPathPrefix = 'elasticsearch_advanced_search_settings/fulltext_relevancy/';
        $searchInCategorySettingsPathPrefix = $advancedSettingsPathPrefix . 'search_in_category_name_';
        $isSearchable = (bool) Mage::getStoreConfig($advancedSettingsPathPrefix . 'search_in_category_name');
        if (in_array($searchType, array(self::SEARCH_TYPE_FUZZY, self::SEARCH_TYPE_PHONETIC))) {
            $isSearchable = $isSearchable && (bool) Mage::getStoreConfig($searchInCategorySettingsPathPrefix . 'fuzzy');
        } else if ($searchType == self::SEARCH_TYPE_AUTOCOMPLETE) {
            $isSearchable = $isSearchable && (bool) Mage::getStoreConfig($searchInCategorySettingsPathPrefix . 'use_in_autocomplete');
        }

        if ($isSearchable) {
            $weight = (int) Mage::getStoreConfig($searchInCategorySettingsPathPrefix . 'weight');
            $analyzer = $this->_getDefaultAnalyzerBySearchType($languageCode, $searchType);
            $searchFields[$searchType][] = sprintf(
                "%s^%s", $this->getFieldName('category_name', $languageCode, self::FIELD_TYPE_SEARCH, $analyzer), $weight
            );
        }
        return $searchFields[$searchType];
    }
}
