<?php

abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Catalog_Eav_Abstract
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
{
    protected $_attributeCollectionModel;
    protected $_mapping                  = null;
    protected $_authorizedBackendModels  = array();
    protected $_suggestInputAttributes   = array('name');
    protected $_suggestPayloadAttributes = array('entity_id');

    public function getMappingProperties($useCache = true)
    {
        $cacheKey = 'SEARCH_ENGINE_MAPPING_' . $this->_type;

        if ($this->_mapping == null && $useCache) {
            $mapping = Mage::app()->loadCache($cacheKey);
            if ($mapping) {
                $this->_mapping = unserialize($mapping);
            }
        }

        if ($this->_mapping === null) {

            $this->_mapping = array('properties' => array());

            $entityType = Mage::getModel('eav/entity_type')->loadByCode($this->_entityType);

            $attributes = Mage::getResourceModel($this->_attributeCollectionModel)
                ->setEntityTypeFilter($entityType->getEntityTypeId());

            foreach ($attributes as $attribute) {
                $this->_mapping['properties'] = array_merge($this->_mapping['properties'], $this->_getAttributeMapping($attribute));
            }

            $this->_mapping['properties']['unique']   = array('type' => 'string');
            $this->_mapping['properties']['id']       = array('type' => 'long');
            $this->_mapping['properties']['store_id'] = array('type' => 'integer');

            foreach (Mage::app()->getStores() as $store) {
                $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);
                $this->_mapping['properties'][Mage::helper('smile_elasticsearch')->getSuggestFieldName($store)] = array(
                    'type'     => 'completion',
                    'payloads' => true,
                    'index_analyzer' => 'shingle',
                    'search_analyzer' => 'shingle',
                    'preserve_separators' => false,
                    'preserve_position_increments' => false
                );
            }

            $mapping = serialize($this->_mapping);

            Mage::app()->saveCache(
                $mapping,
                $cacheKey,
                array('CONFIG', 'EAV_ATTRIBUTE'),
                Mage::helper('smile_elasticsearch')->getCacheLifetime()
            );
        }

        return $this->_mapping;
    }


    protected function _getAttributeMapping($attribute)
    {
        $mapping = array();

        if ($this->_canIndexAttribute($attribute)) {
            $attributeCode = $attribute->getAttributeCode();
            $type = $this->_getAttributeType($attribute);

            if ($type === 'string' && !$attribute->getBackendModel() && $attribute->getFrontendInput() != 'media_image') {
                foreach (Mage::app()->getStores() as $store) {
                    $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);
                    $fieldName = $attributeCode . '_' . $languageCode;
                    $mapping[$fieldName] = array('type' => $type, 'analyzer' => 'analyzer_' . $languageCode);

                    if ($attribute->getBackendType() == 'varchar') {
                        $mapping[$fieldName] = array('type' => 'multi_field', 'fields' => array($fieldName => $mapping[$fieldName]));
                        $mapping[$fieldName]['fields']['sortable']  = array('type' => $type, 'analyzer' => 'sortable');
                        $mapping[$fieldName]['fields']['untouched'] = array('type' => $type, 'index' => 'not_analyzed');
                    }
                }
            } else if ($type === 'date') {
                $mapping[$attributeCode] = array('type' => $type, 'format' => Varien_Date::DATETIME_INTERNAL_FORMAT);
            } else {
                $mapping[$attributeCode] = array('type' => $type);
            }

            if ($attribute->usesSource()) {
                foreach (Mage::app()->getStores() as $store) {
                    $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);
                    $fieldName = $attributeCode . '_' . $languageCode;
                    $mapping['options_' . $attributeCode . '_' . $languageCode] = array(
                        'type' => 'string',
                        'analyzer' => 'analyzer_' . $languageCode
                    );
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
        } elseif ($attribute->getBackendType() == 'decimal') {
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

    protected function _canIndexAttribute($attribute)
    {
        $canIndex = true;

        if ($attribute->getBackendModel() && !in_array($attribute->getBackendModel(), $this->_authorizedBackendModels)) {
            $canIndex = false;
        }

        return $canIndex;
    }


    public function rebuildIndex($storeId = null, $ids = null)
    {
       if (is_null($storeId)) {
            $storeIds = array_keys(Mage::app()->getStores());
            foreach ($storeIds as $storeId) {
                $this->_rebuildStoreIndex($storeId, $ids);
            }
        } else {
            $this->_rebuildStoreIndex($storeId, $ids);
        }

        return $this;
    }

    public function getTable($modelEntity)
    {
        return Mage::getSingleton('core/resource')->getTableName($modelEntity);
    }

    public function getConnection()
    {
        return Mage::getSingleton('core/resource')->getConnection('write');;
    }

    protected function _rebuildStoreIndex($storeId, $entityIds = null)
    {
        $store = Mage::app()->getStore($storeId);
        $websiteId = $store->getWebsiteId();

        $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);

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

            $ids = array();

            foreach ($entities as $entityData) {
                $lastObjectId = $entityData['entity_id'];
                $ids[]  = $entityData['entity_id'];
            }

            $entityRelations = $this->_getChildrenIds($ids);
            foreach ($entityRelations as $childrenIds) {
                $ids = array_merge($ids, $childrenIds);
            }

            $entityIndexes    = array();
            $entityAttributes = $this->_getAttributes($storeId, $ids, $dynamicFields);

            foreach ($entities as $entityData) {

                if (!isset($entityAttributes[$entityData['entity_id']])) {
                    continue;
                }

                $entityAttr = array();

                foreach ($entityAttributes[$entityData['entity_id']] as $attributeId => $value) {
                    $attribute = $attributesById[$attributeId];
                    $entityAttr = array_merge(
                        $entityAttr,
                        $this->_getAttributeIndexValues($attribute, $value, $storeId, $languageCode)
                    );

                }

                $entityAttr = array_merge($entityData, $entityAttr);
                $entityAttr['store_id'] = $storeId;
                $entityIndexes[$entityData['entity_id']] = $entityAttr;
            }

            $entityIndexes = $this->_addChildrenData($entityIndexes, $entityAttributes, $entityRelations, $storeId, $languageCode);
            $entityIndexes = $this->_addSuggestField($entityIndexes, $storeId, $languageCode);

            $this->_saveIndexes($storeId, $entityIndexes);
        }

        return $this;
    }

    protected function _getAttributeIndexValues($attribute, $value, $storeId, $languageCode)
    {
        $attrs = array();

        if ($value && $attribute) {
            $field = $this->_getAttributeFieldName($attribute, $languageCode);
            if ($field) {
                $attrs[$field] = $this->_getAttributeValue($attribute, $value, $storeId);

                if ($attribute->usesSource()) {
                    $field = 'options_' . $attribute->getAttributeCode() . '_' . $languageCode;
                    $value = $this->_getOptionsText($attribute, $value, $storeId);
                    if ($value) {
                        $attrs[$field] = $value;
                    }
                }
            }
        }

        return $attrs;
    }

    protected function _getAttributesById()
    {
        $entityType = Mage::getModel('eav/entity_type')->loadByCode($this->_entityType);
        $attributes = Mage::getResourceModel($this->_attributeCollectionModel)
            ->setEntityTypeFilter($entityType->getEntityTypeId());

        $attributesById = array();

        foreach ($attributes as $attribute) {
            if ($this->_canIndexAttribute($attribute) && $attribute->getBackendType() != 'static') {
                $attributesById[$attribute->getAttributeId()] = $attribute;
            }
        }

        return $attributesById;
    }

    protected function _addChildrenData($entityIndexes, $entityAttributes, $entityRelations, $storeId, $languageCode) {

        $attributesById = $this->_getAttributesById();

        foreach ($entityRelations as $parentId => $childrenIds) {

            $values = $entityIndexes[$parentId];

            foreach ($childrenIds as $childrenId) {
                if (isset($entityAttributes[$childrenId])) {
                    foreach ($entityAttributes[$childrenId] as $attributeId => $value) {
                        if (isset($attributesById[$attributeId])) {
                            $attribute = $attributesById[$attributeId];
                            $childrenValues = $this->_getAttributeIndexValues($attribute, $value, $storeId, $languageCode);
                            foreach ($childrenValues as $field => $fieldValue) {
                                $parentValue = array();

                                if (!is_array($fieldValue)) {
                                    $fieldValue = array($fieldValue);
                                }

                                if (isset($values[$field])) {
                                    $parentValue = is_array($values[$field]) ? $values[$field] : array($values[$field]);
                                }
                                $values[$field] = array_unique(array_merge($parentValue, $fieldValue));
                            }
                        }
                    }
                }
            }

            $entityIndexes[$parentId] = $values;
        }

        return $entityIndexes;
    }

    protected function _getChildrenIds($entityIds, $websiteId = null)
    {
        return array();
    }

    protected function _saveIndexes($storeId, $entityIndexes)
    {
        Mage::helper('catalogsearch')->getEngine()->saveEntityIndexes($storeId, $entityIndexes, $this->_type);
        return $this;
    }

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
                ->from(
                    array('t_default' => $tableName),
                    array('entity_id', 'attribute_id'))
                    ->joinLeft(
                        array('t_store' => $tableName),
                        $adapter->quoteInto(
                            't_default.entity_id=t_store.entity_id' .
                            ' AND t_default.attribute_id=t_store.attribute_id' .
                            ' AND t_store.store_id=?',
                            $storeId),
                        array('value' => new Zend_Db_Expr('COALESCE(t_store.value, t_default.value)')))
                        ->where('t_default.store_id=?', 0)
                        ->where('t_default.attribute_id IN (?)', $attributeIds)
                        ->where('t_default.entity_id IN (?)', $entityIds);

                /**
                 * Add additional external limitation
                */
                $eventName = sprintf('prepare_catalog_%s_index_select', $this->_type);
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
                $result[$row['entity_id']][$row['attribute_id']] = $row['value'];
            }
        }

        return $result;
    }

    protected function _getAttributeValue($attribute, $value, $storeId)
    {
        if ($attribute->usesSource()) {
            $inputType = $attribute->getFrontend()->getInputType();
            if ($inputType == 'multiselect') {
                $value = explode(',', $value);
            }
        } else {
            $inputType = $attribute->getFrontend()->getInputType();
            if ($inputType == 'price') {
                $value = Mage::app()->getStore($storeId)->roundPrice($value);
            }
        }

        $value = preg_replace("#\s+#siu", ' ', trim(strip_tags($value)));

        return $value;
    }


    protected function _getAttributeFieldName($attribute, $languageCode)
    {

        $mapping = $this->getMappingProperties()['properties'];
        $fieldName = $attribute->getAttributeCode();

        if (!isset($mapping[$fieldName])) {
            $fieldName =  $fieldName . '_' . $languageCode;
        }

        if (!isset($mapping[$fieldName])) {
            $fieldName = false;
        }

        return $fieldName;
    }

    protected function _addSuggestField($entityIndexes, $storeId, $languageCode) {
        $store = Mage::app()->getStore($storeId);
        $languageCode = Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store);
        $fieldName = Mage::helper('smile_elasticsearch')->getSuggestFieldName($store);
        $inputFields = array();
        foreach ($this->_suggestInputAttributes as $attribute) {
            $field = $this->getFieldName($attribute, $languageCode);
            $inputFields[] = $field;
        }

        $payloadFields = array();
        foreach ($this->_suggestPayloadAttributes as $attribute) {
            $field = $this->getFieldName($attribute, $languageCode, 'filter');
            $payloadFields[] = $field;
        }

        foreach ($entityIndexes as $entityId => $index) {
            $suggest = array('input' => '', 'payload' => array());

            foreach ($inputFields as $field) {
                if (isset($index[$field])) {

                    if (!isset($suggest['output'])) {
                        $suggest['output'] = is_array($index[$field]) ? current($index[$field]) : $index[$field];
                    }

                    if (is_array($index[$field])) {
                        $index[$field] = implode(' ', $index[$field]);
                    }
                    $suggest['input'] = implode(' ', array($suggest['input'], $index[$field]));
                }
            }

            foreach ($payloadFields as $field) {
                if (isset($index[$field])) {
                    if (!isset($suggest['payload'][$field])) {
                        $suggest['payload'][$field] = $index[$field];
                    } else {
                        if (!is_array($index[$field])) {
                            $index[$field] = array($index[$field]);
                        }
                        if (!is_array($suggest['payload'][$field])) {
                            $suggest['payload'][$field] = array($suggest['payload'][$field]);
                        }
                        $suggest['payload'][$field] = array_merge($suggest['payload'][$field], $index[$field]);
                    }
                }
            }

            $suggest['input']  = explode(' ', $suggest['input']);
            $entityIndexes[$index['entity_id']][$fieldName] = $suggest;
        }

        return $entityIndexes;
    }

    protected function _getOptionsText($attribute, $value, $storeId)
    {
        $attribute->setStoreId($storeId);
        $value = $attribute->getSource()->getIndexOptionText($value);
        return $value;
    }


    abstract protected function _getSearchableEntities($storeId, $ids = null, $lastId = 0, $limit = 100);
}