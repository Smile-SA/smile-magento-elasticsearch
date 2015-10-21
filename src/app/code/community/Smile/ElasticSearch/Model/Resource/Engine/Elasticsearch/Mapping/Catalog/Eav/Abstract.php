<?php
/**
 * Abstract class that define a type mapping of EAV entities.
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
abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Catalog_Eav_Abstract
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
{
    /**
     * Collection of all attributes.
     *
     * @var Mage_Eav_Model_Resource_Attribute_Collection
     */
    protected $_attributeCollectionModel;

    /**
     * Generated or loaded mapping.
     *
     * @var array
     */
    protected $_mapping                  = null;

    /**
     * List of backends authorized for indexing.
     *
     * @var array
     */
    protected $_authorizedBackendModels  = array();

    /**
     * Store all attributes by ids
     *
     * @var
     */
    protected $_attributesById;

    /**
     * Cache of option text indexed by value (added to increase pefrormance into _getOptionText method.
     *
     * @var array
     */
    protected $_indexedOptionText = array();

    /**
     * Get mapping properties as stored into the index
     *
     * @return array
     */
    protected function _getMappingProperties()
    {
        $mapping = array(
            '_all' => array('enabled' => false),
            'properties' => array()
        );

        $mapping['properties'] = $this->_getSpellingFieldMapping();

        $attributes = $this->_getAttributesById();
        foreach ($attributes as $attribute) {
            $mapping['properties'] = array_merge($mapping['properties'], $this->_getAttributeMapping($attribute));
        }

        $mapping['properties']['unique']   = array('type' => 'string', 'store' => false, 'index' => 'not_analyzed');
        $mapping['properties']['id']       = array('type' => 'long', 'store' => false, 'index' => 'not_analyzed');
        $mapping['properties']['store_id'] = array('type' => 'integer', 'store' => false, 'index' => 'not_analyzed');

        return $mapping;
    }

    /**
     * Return mapping for an attribute.
     *
     * @param Mage_Eav_Model_Attribute $attribute Attribute we want the mapping for.
     *
     * @return array
     */
    protected function _getAttributeMapping($attribute)
    {
        $mapping = array();

        if ($this->_canIndexAttribute($attribute)) {
            $attributeCode = $attribute->getAttributeCode();
            $type = $this->_getAttributeType($attribute);

            $isFacet = (bool) ($attribute->getIsFilterable() || $attribute->getIsFilterableInSearch());
            $isFuzzy = (bool) $attribute->getIsFuzzinessEnabled();
            $usedForSortBy = (bool) $attribute->getUsedForSortBy();
            $isAutocomplete = (bool) ($attribute->getIsUsedInAutocomplete() || $attribute->getIsDisplayedInAutocomplete());

            if ($type === 'string' && !$attribute->getBackendModel() && $attribute->getFrontendInput() != 'media_image') {
                foreach ($this->_stores as $store) {
                    $languageCode = $this->_helper->getLanguageCodeByStore($store);
                    $fieldName = $attributeCode . '_' . $languageCode;
                    $mapping[$fieldName] = array('type' => $type, 'analyzer' => 'analyzer_' . $languageCode, 'store' => false);

                    $multiTypeField = $attribute->getBackendType() == 'varchar' || $attribute->getBackendType() == 'text';
                    $multiTypeField = $multiTypeField && !($attribute->usesSource());

                    if ($multiTypeField) {
                        $fieldMapping = $this->_getStringMapping(
                            $fieldName, $languageCode, $type, $usedForSortBy, $isFuzzy, $isFacet, $isAutocomplete
                        );
                        $mapping = array_merge($mapping, $fieldMapping);
                    }
                }
            } else if ($type === 'date') {
                $mapping[$attributeCode] = array(
                    'store' => false,
                    'type' => $type,
                    'format' => implode('||', array(Varien_Date::DATETIME_INTERNAL_FORMAT, Varien_Date::DATE_INTERNAL_FORMAT))
                );
            } else {
                $mapping[$attributeCode] = array(
                    'type' => $type, 'store' => false, 'fielddata' => array('format' => $type == 'string' ? 'disabled' :'doc_values')
                );
            }

            if ($attribute->usesSource()) {
                foreach ($this->_stores as $store) {
                    $languageCode = $this->_helper->getLanguageCodeByStore($store);
                    $fieldName = 'options' . '_' .  $attributeCode . '_' . $languageCode;
                    $fieldMapping = $this->_getStringMapping(
                        $fieldName, $languageCode, 'string', $usedForSortBy, $isFuzzy, $isFacet, $isAutocomplete
                    );
                    $mapping = array_merge($mapping, $fieldMapping);
                }
            }
        }

        return $mapping;
    }

    /**
     * Returns attribute type for indexation.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute Attribute
     *
     * @return string
     */
    protected function _getAttributeType($attribute)
    {
        $type = 'string';
        if ($attribute->getBackendType() == 'int' || $attribute->getFrontendClass() == 'validate-digits') {
            $type = 'integer';
        } elseif ($attribute->getBackendType() == 'decimal' || $attribute->getFrontendClass() == 'validate-number') {
            $type = 'double';
        } elseif ($attribute->getSourceModel() == 'eav/entity_attribute_source_boolean') {
            $type = 'boolean';
        } elseif ($attribute->getBackendType() == 'datetime') {
            $type = 'date';
        } elseif ($attribute->usesSource() && $attribute->getSourceModel() === null) {
            $type = 'integer';
        } else if ($attribute->usesSource()) {
            $type = 'string';
        }

        return $type;
    }

    /**
     * Indicates if an attribute can be indexed or not.
     *
     * @param Mage_Eav_Model_Attribute $attribute Attribute
     *
     * @return boolean
     */
    protected function _canIndexAttribute($attribute)
    {
        $canIndex = true;

        if ($attribute->getBackendModel() && !in_array($attribute->getBackendModel(), $this->_authorizedBackendModels)) {
            $canIndex = false;
        }

        return $canIndex;
    }


    /**
     * Rebuild the index (full or diff).
     *
     * @param int|null   $storeId Store id the index should be rebuilt for. If null, all store id will be rebuilt.
     * @param array|null $ids     Ids the index should be rebuilt for. If null, processing a fulll reindex
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    public function rebuildIndex($storeId = null, $ids = null)
    {
        if (is_null($storeId)) {
            $storeIds = array_keys($this->_stores);
            foreach ($storeIds as $storeId) {
                $this->_rebuildStoreIndex($storeId, $ids);
            }
        } else {
            $this->_rebuildStoreIndex($storeId, $ids);
        }

        $this->getCurrentIndex()->refresh();

        return $this;
    }

    /**
     * Returns the main entity table.
     *
     * @param string $modelEntity Entity name
     *
     * @return string
     */
    public function getTable($modelEntity)
    {
        return Mage::getSingleton('core/resource')->getTableName($modelEntity);
    }

    /**
     * Return DB connection.
     *
     * @return Varien_Db_Adapter_Interface
     */
    public function getConnection()
    {
        return Mage::getSingleton('core/resource')->getConnection('write');
    }

    /**
     * Rebuild the index (full or diff).
     *
     * @param int        $storeId   Store id the index should be rebuilt for.
     * @param array|null $entityIds Ids the index should be rebuilt for. If null, processing a fulll reindex
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    protected function _rebuildStoreIndex($storeId, $entityIds = null)
    {
        $store = Mage::app()->getStore($storeId);
        $websiteId = $store->getWebsiteId();

        $languageCode = $this->_helper->getLanguageCodeByStore($store);

        $dynamicFields = array();
        $attributesById = $this->_getAttributesById();

        foreach ($attributesById as $attribute) {
            if ($this->_canIndexAttribute($attribute) && $attribute->getBackendType() != 'static') {
                $dynamicFields[$attribute->getBackendTable()][] = $attribute->getAttributeId();
            }
        }

        $websiteId = Mage::app()->getStore($storeId)->getWebsite()->getId();
        $lastObjectId = 0;

        while (true) {

            $entities = $this->_getSearchableEntities($storeId, $entityIds, $lastObjectId);

            if (!$entities) {
                break;
            }

            $ids = array_keys($entities);
            $lastObjectId = end($ids);

            $entities = $this->_addAdvancedIndex($entities, $storeId);

            if (!empty($entities)) {

                $ids = array_keys($entities);

                $entityRelations = $this->_getChildrenIds($ids, $websiteId);
                if (!empty($entityRelations)) {
                    $allChildrenIds = call_user_func_array('array_merge', $entityRelations);
                    $ids = array_merge($ids, $allChildrenIds);
                }

                $entityIndexes    = array();
                $entityAttributes = $this->_getAttributes($storeId, $ids, $dynamicFields);

                foreach ($entities as &$entityData) {

                    if (!isset($entityAttributes[$entityData['entity_id']])) {
                        continue;
                    }
                    $entityTypeId = isset($entityData['type_id']) ? $entityData['type_id'] : null;
                    $this->_addChildrenData($entityData['entity_id'], $entityAttributes, $entityRelations, $storeId, $entityTypeId);

                    foreach ($entityAttributes[$entityData['entity_id']] as $attributeId => $value) {
                        $attribute = $attributesById[$attributeId];
                        $entityData += $this->_getAttributeIndexValues($attribute, $value, $storeId, $languageCode);
                    }

                    $entityData['store_id'] = $storeId;
                    $entityData[Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch::UNIQUE_KEY] = $entityData['entity_id'] . '|' . $storeId;
                    $entityIndexes[$entityData['entity_id']] = $entityData;
                }

                $this->_saveIndexes($storeId, $entityIndexes);
            }
        }

        return $this;
    }

    /**
     * Return the indexed attribute value.
     *
     * @param Mage_Eav_Model_Attribute $attribute    Attribute we want the value for.
     * @param mixed                    $value        Raw value
     * @param int                      $storeId      Store id
     * @param string                   $languageCode Locale code
     *
     * @return mixed.
     */
    protected function _getAttributeIndexValues($attribute, $value, $storeId, $languageCode)
    {
        $attrs = array();

        if ($value && $attribute) {
            $field = $this->_getAttributeFieldName($attribute, $languageCode);
            if ($field) {
                $storedValue = $this->_getAttributeValue($attribute, $value, $storeId);

                if ($storedValue != null && $storedValue != false && $storedValue != '0000-00-00 00:00:00' && !empty($storedValue)) {
                    $attrs[$field] = $storedValue;

                    if ($attribute->usesSource()) {
                        $field = 'options_' . $attribute->getAttributeCode() . '_' . $languageCode;
                        $optionValue = $this->_getOptionsText($attribute, $storedValue, $storeId);
                        if ($optionValue) {
                            $attrs[$field] = $optionValue;
                        }
                    }
                }
            }
        }

        return $attrs;
    }

    /**
     * Load all entity attributes by ids.
     *
     * @return array.
     */
    protected function _getAttributesById()
    {
        if ($this->_attributesById === null) {
            $entityType = Mage::getModel('eav/entity_type')->loadByCode($this->_entityType);
            $attributes = Mage::getResourceModel($this->_attributeCollectionModel)
                ->setEntityTypeFilter($entityType->getEntityTypeId());

            if (method_exists($attributes, 'addToIndexFilter')) {
                $attributes->addToIndexFilter(true);
            }

            $this->_attributesById = array();

            foreach ($attributes as $attribute) {
                if ($this->_canIndexAttribute($attribute)) {
                    $this->_attributesById[$attribute->getAttributeId()] = $attribute;
                }
            }
        }

        return $this->_attributesById;
    }

    /**
     * Append children attributes to parents doc.
     *
     * @param int    $parentId          Entity id
     * @param array  &$entityAttributes Attributes values by entity id
     * @param array  $entityRelations   Array of the entities relations
     * @param int    $storeId           Store id
     * @param string $entityTypeId      Type of the parent entity
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Catalog_Eav_Abstract
     */
    protected function _addChildrenData($parentId, &$entityAttributes, $entityRelations, $storeId, $entityTypeId)
    {
        return $this;
    }

    /**
     * Append additional data to the index
     *
     * @param array $entityIndexes Indexed data
     * @param int   $storeId       Store id
     *
     * @return array
     */
    protected function _addAdvancedIndex($entityIndexes, $storeId)
    {
        return $entityIndexes;
    }

    /**
     * Retrieve entities children ids
     *
     * @param array $entityIds Parent entities ids.
     * @param int   $websiteId Current website ids
     *
     * @return array
     */
    protected function _getChildrenIds($entityIds, $websiteId)
    {
        return array();
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
        Mage::helper('catalogsearch')->getEngine()->saveEntityIndexes($storeId, $entityIndexes, $this->_type);
        return $this;
    }

    /**
     * Retrieve values for attributes.
     *
     * @param int   $storeId        Store id.
     * @param array $entityIds      Entities ids.
     * @param array $attributeTypes Attributes to be indexed.
     *
     * @return array
     */
    protected function _getAttributes($storeId, array $entityIds, array $attributeTypes)
    {
        $result  = array();
        $selects = array();
        $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
        $adapter = $this->getConnection();
        $ifStoreValue = $adapter->getCheckSql('t_store.value_id > 0', 't_store.value', 't_default.value');

        foreach ($attributeTypes as $tableName => $attributeIds) {
            if ($attributeIds) {
                $select = $adapter->select()
                    ->from(array('t_default' => $tableName), array('entity_id', 'attribute_id'))
                    ->joinLeft(
                        array('t_store' => $tableName),
                        $adapter->quoteInto(
                            't_default.entity_id=t_store.entity_id' .
                            ' AND t_default.attribute_id=t_store.attribute_id' .
                            ' AND t_store.store_id=?',
                            $storeId
                        ),
                        array('value' => new Zend_Db_Expr('COALESCE(t_store.value, t_default.value)'))
                    )
                    ->where('t_default.store_id=?', 0)
                    ->where('t_default.attribute_id IN (?)', $attributeIds)
                    ->where('t_default.entity_id IN (?)', $entityIds);

                /**
                 * Add additional external limitation
                */
                $eventName = sprintf('prepare_catalog_atttibutes_%s_index_select', $this->_type);
                Mage::dispatchEvent(
                    $eventName,
                    array(
                        'select'        => $select,
                        'entity_field'  => new Zend_Db_Expr('t_default.entity_id'),
                        'website_field' => $websiteId,
                        'store_field'   => new Zend_Db_Expr('t_store.store_id')
                    )
                );

                $selects[] = $select;
            }
        }

        if ($selects) {
            $select = $adapter->select()->union($selects, Zend_Db_Select::SQL_UNION_ALL);
            $query = $adapter->query($select);
            while ($row = $query->fetch()) {
                if ($row['value'] !== null) {
                    $result[$row['entity_id']][$row['attribute_id']] = $row['value'];
                }
            }
        }

        return $result;
    }

    /**
     * Return the indexed attribute value.
     *
     * @param Mage_Eav_Model_Attribute $attribute Attribute we want the value for.
     * @param mixed                    $value     Raw value
     * @param int                      $storeId   Store id
     *
     * @return mixed.
     */
    protected function _getAttributeValue($attribute, $value, $storeId)
    {
        if ($attribute->usesSource()) {
            if (!is_array($value)) {
                $value = explode(',', $value);
            }
            $value = array_filter($value);
            $value = array_values(array_unique($value));
            if ($attribute->getBackendType() == 'int') {
                $value = array_map('intval', $value);
            }
            if (count($value) == 1) {
                $value = current($value);
            }
        } else if ($attribute->getBackendType() == 'decimal') {
            $value = floatval($value);
        } else if ($attribute->getBackendType() == 'int') {
            $value = intval($value);
        }

        return $value;
    }

    /**
     * Retrieve the field name for an attributes.
     *
     * @param Mage_Eav_Model_Attribute $attribute    Attribute we want the value for.
     * @param string                   $languageCode Language code
     *
     * @return string
     */
    protected function _getAttributeFieldName($attribute, $languageCode)
    {
        $mapping = $this->getMappingProperties();
        $mapping = $mapping['properties'];

        $fieldName = $attribute->getAttributeCode();

        if (!isset($mapping[$fieldName])) {
            $fieldName =  $fieldName . '_' . $languageCode;
        }

        if (!isset($mapping[$fieldName])) {
            $fieldName = false;
        }

        return $fieldName;
    }

    /**
     * Return the text value for an atribute using source model.
     *
     * @param Mage_Eav_Model_Attribute $attribute Attribute we want the value for.
     * @param mixed                    $value     Raw value
     * @param int                      $storeId   Store id
     *
     * @return mixed.
     */
    protected function _getOptionsText($attribute, $value, $storeId)
    {
        $attributeId = $attribute->getAttributeId();
        if (!isset($this->_indexedOptionText[$attributeId]) || !isset($this->_indexedOptionText[$attributeId][$storeId])) {
            $this->_getAllOptionsText($attribute, $storeId);
        }

        if (is_array($value)) {
            $value = array_values(array_intersect_key($this->_indexedOptionText[$attributeId][$storeId], array_flip($value)));
            if (empty($value)) {
                $value = false;
            } else if (count($value) == 1) {
                $value = current($value);
            }
        } else {
            $value = (string) trim($value, ',');
            if (isset($this->_indexedOptionText[$attributeId][$storeId][$value])) {
                $value = $this->_indexedOptionText[$attributeId][$storeId][$value];
            } else {
                $value == false;
            }
        }

        return $value;
    }

    /**
     * Load all options for an attribute using source.
     *
     * @param Mage_Eav_Model_Attribute $attribute Attribute we want the value for.
     * @param int                      $storeId   Store id.
     *
     * @return array
     */
    protected function _getAllOptionsText($attribute, $storeId)
    {
        $attributeId = $attribute->getAttributeId();
        if (!isset($this->_indexedOptionText[$attributeId]) || !isset($this->_indexedOptionText[$attributeId][$storeId])) {
            $options = array();
            if ($attribute->getSource()) {
                $storeIds = array(0, $storeId);
                foreach ($storeIds as $storeId) {
                    $attribute->setStoreId($storeId);
                    $allOptions = $attribute->getSource()->getAllOptions(false);
                    foreach ($allOptions as $key => $value) {
                        if (is_array($value) && isset($value['value'])) {
                            $options[$value['value']] = $value['label'];
                        } else {
                            $options[$key] = $value;
                        }
                    }
                }
            }
            $this->_indexedOptionText[$attributeId][$storeId] = $options;
        }

        return $this->_indexedOptionText[$attributeId][$storeId];
    }

    /**
     * Return a list of all searchable field for the current type (by locale code).
     *
     * @param string $languageCode Language code.
     * @param string $searchType   Type of search currentlty used.
     * @param string $analyzer     Allow to force the analyzer used for the field (whitespace, ...).
     *
     * @return array.
     */
    public function getSearchFields($languageCode, $searchType = null, $analyzer = null)
    {
        if ($searchType == null) {
            $searchType = self::SEARCH_TYPE_NORMAL;
        }

        if ($analyzer == null) {
            $analyzer = $this->_getDefaultAnalyzerBySearchType($languageCode, $searchType);
        }

        if (!isset($this->_searchFields[$searchType . $analyzer])) {

            $mapping = $this->getMappingProperties();
            $this->_searchFields[$searchType . $analyzer] = $this->_getDefaultSearchFieldBySearchType($languageCode, $searchType);
            $hasDefaultField = !empty($this->_searchFields[$searchType]);

            $entityType = Mage::getModel('eav/entity_type')->loadByCode($this->_entityType);

            $attributes = Mage::getResourceModel($this->_attributeCollectionModel)
                ->setEntityTypeFilter($entityType->getEntityTypeId())
                ->addFieldToFilter('is_searchable', 1);

            foreach ($attributes as $attribute) {
                $isAttributeSearchable = $this->_isAttributeUsedForSearchType($attribute, $searchType);
                if ($isAttributeSearchable) {
                    $field = $this->getFieldName($attribute->getAttributeCode(), $languageCode, self::FIELD_TYPE_SEARCH, $analyzer);
                    $weight = (int) $attribute->getSearchWeight();
                    if ($field !== false && $weight > 0 && !($hasDefaultField && $weight == 1)) {
                        $this->_searchFields[$searchType . $analyzer][] = $field . '^' . $weight;
                    }
                }
            }
        }

        return $this->_searchFields[$searchType . $analyzer];
    }

    /**
     * Indicates if an attribute is used into a search type.
     *
     * @param Mage_Eav_Model_Attribute $attribute  Attribute we want the value for.
     * @param string                   $searchType Search type
     *
     * @return boolean
     */
    protected function _isAttributeUsedForSearchType($attribute, $searchType)
    {
        $isSearchable = $attribute->getIsSearchable() || $attribute->getAttributeCode() == 'name';

        if (in_array($searchType, array(self::SEARCH_TYPE_FUZZY, self::SEARCH_TYPE_PHONETIC))) {
            $isSearchable = $isSearchable && (bool) $attribute->getIsFuzzinessEnabled();
        } else if ($searchType == self::SEARCH_TYPE_AUTOCOMPLETE) {
            $isSearchable = $isSearchable && (bool) $attribute->getIsUsedInAutocomplete();
        }

        return $isSearchable;
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
    abstract protected function _getSearchableEntities($storeId, $ids = null, $lastId = 0);
}
