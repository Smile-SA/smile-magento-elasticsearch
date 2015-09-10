<?php
/**
 * Abstract class that define a type mapping
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
abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
{
    /**
     * @var string
     */
    const FIELD_TYPE_SEARCH = 'search';

    /**
     * @var string
     */
    const FIELD_TYPE_FILTER = 'filter';

    /**
     * @var string
     */
    const FIELD_TYPE_SORT   = 'sort';

    /**
     * @var string
     */
    const FIELD_TYPE_FACET  = 'facet';

    /**
     * ES Type.
     *
     * @var string
     */
    protected $_type;

    /**
     * Search fields.
     *
     * @var array
     */
    protected $_searchFields = null;

    /**
     * All front stores.
     *
     * @var array
     */
    protected $_stores = array();

    /**
     * Search helper.
     *
     * @var Smile_ElasticSearch_Helper_Data
     */
    protected $_helper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_stores = Mage::app()->getStores();
        $this->_helper = Mage::helper('smile_elasticsearch');
    }

    /**
     * Set index type for the current mapping.
     *
     * @param string $type The new type.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * Return a list of all searchable field for the current type (by locale code).
     *
     * @param string $localeCode Locale code.
     *
     * @return array.
     */
    abstract public function getSearchFields($localeCode);

    /**
     * Return the ES field name
     *
     * @param string $field      Magento field.
     * @param string $localeCode Locale code we want the field for.
     * @param string $type       How the field will be used : search, filter, facet, sort
     *
     * @return string
     */
    public function getFieldName($field, $localeCode, $type = self::FIELD_TYPE_SEARCH)
    {
        $mapping = $this->getMappingProperties();

        $useOptions        = isset($mapping['properties']['options_' . $field . '_' . $localeCode]);
        $typesUsingOptions = array(self::FIELD_TYPE_SEARCH, self::FIELD_TYPE_SORT, self::FIELD_TYPE_FACET);
        $typesUsedInSearch = array('string', 'multi_field');

        if (in_array($type, $typesUsingOptions) && $useOptions) {
            $field = 'options_' . $field . '_' . $localeCode;
        } else if (isset($mapping['properties'][$field . '_' . $localeCode])) {
            $field = $field . '_' . $localeCode;
        }

        if (isset($mapping['properties'][$field]['type'])) {

            $mappingType = $mapping['properties'][$field]['type'];
            if (!in_array($mappingType, $typesUsedInSearch) && $type == self::FIELD_TYPE_SEARCH) {
                $field = false;
            }

            if ($field && $mappingType == 'multi_field') {
                if (in_array($type, array(self::FIELD_TYPE_FILTER, self::FIELD_TYPE_FACET))) {
                    $field .= '.untouched';
                } else if ($type == self::FIELD_TYPE_SORT) {
                    $field .= '.sortable';
                }
            }
        }

        return $field;
    }

    /**
     * Get mapping properties as stored into the index
     *
     * @param string $useCache Indicates if the cache should be used or if the mapping should be rebuilt.
     *
     * @return array
     */
    public function getMappingProperties($useCache = true)
    {
        $indexName = $this->getCurrentIndex()->getCurrentName();

        $cacheKey = 'SEARCH_ENGINE_MAPPING_' . $indexName . $this->_type;

        if ($this->_mapping == null && $useCache) {
            $mapping = Mage::app()->loadCache($cacheKey);
            if ($mapping) {
                $this->_mapping = unserialize($mapping);
            }
        }

        if ($this->_mapping === null) {

            $this->_mapping = $this->_loadMappingFromIndex();

            if ($this->_mapping === null) {
                $this->_mapping = $this->_getMappingProperties();
            }

            $mapping = serialize($this->_mapping);

            Mage::app()->saveCache(
                $mapping, $cacheKey, array('CONFIG', 'EAV_ATTRIBUTE'),
                $this->_helper->getCacheLifetime()
            );
        }

        return $this->_mapping;
    }

    /**
     * Get the size of each bulk of product indexed
     *
     * @return int
     */
    protected function _getBatchIndexingSize()
    {
        return max(1, (int) Mage::getStoreConfig('catalog/search/elasticsearch_batch_indexing_size'));
    }

    /**
     * Retrive the mapping of the current index.
     *
     * @return array|null
     */
    protected function _loadMappingFromIndex()
    {
        return $this->getCurrentIndex()->loadMappingPropertiesFromIndex($this->_type);
    }


    /**
     * Return the current index.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index
     */
    public function getCurrentIndex()
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        return $engine->getCurrentIndex();
    }

    /**
     * Get mapping properties as stored into the index
     *
     * @return array
     */
    abstract protected function _getMappingProperties();

    /**
     * Rebuild the index (full or diff).
     *
     * @param int|null   $storeId Store id the index should be rebuilt for. If null, all store id will be rebuilt.
     * @param array|null $ids     Ids the index should be rebuilt for. If null, processing a fulll reindex
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract
     */
    abstract public function rebuildIndex($storeId = null, $ids = null);
}