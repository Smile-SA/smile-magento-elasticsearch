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
     * @var string
     */
    const SEARCH_TYPE_NORMAL  = 'normal';

    /**
     * @var string
     */
    const SEARCH_TYPE_FUZZY = 'fuzzy';

    /**
     * @var string
     */
    const SEARCH_TYPE_PHONETIC = 'phonetic';

    /**
     * @var string
     */
    const SEARCH_TYPE_AUTOCOMPLETE = 'autocomplete';

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
    protected $_searchFields = array();

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
     * @param string $languageCode Language code.
     * @param string $searchType   Type of search currentlty used.
     * @param string $analyzer     Allow to force the analyzer used for the field (whitespace, ...).
     *
     * @return array.
     */
    abstract public function getSearchFields($languageCode, $searchType = null, $analyzer = null);

    /**
     * Return the ES field name
     *
     * @param string $field        Document base field (name, size, ...).
     * @param string $languageCode Language code we want the field for.
     * @param string $type         How the field will be used : search, filter, facet, sort
     * @param string $analyzer     Allow to force the analyzer used for the field (whitespace, ...).
     *
     * @return string
     */
    public function getFieldName($field, $languageCode, $type = self::FIELD_TYPE_SEARCH, $analyzer = null)
    {
        $mapping = $this->getMappingProperties();

        $useOptions        = isset($mapping['properties']['options_' . $field . '_' . $languageCode]);
        $typesUsingOptions = array(self::FIELD_TYPE_SEARCH, self::FIELD_TYPE_SORT, self::FIELD_TYPE_FACET);
        $typesUsedInSearch = array('string', 'multi_field');

        if (in_array($type, $typesUsingOptions) && $useOptions) {
            $field = 'options_' . $field . '_' . $languageCode;
        } else if (isset($mapping['properties'][$field . '_' . $languageCode])) {
            $field = $field . '_' . $languageCode;
        }

        if (isset($mapping['properties'][$field]['type'])) {

            $mappingType = $mapping['properties'][$field]['type'];
            if (!in_array($mappingType, $typesUsedInSearch) && $type == self::FIELD_TYPE_SEARCH) {
                $field = false;
            }

            if ($field && $mappingType == 'multi_field') {
                if ($analyzer == null && in_array($type, array(self::FIELD_TYPE_FILTER, self::FIELD_TYPE_FACET))) {
                    $analyzer = 'untouched';
                } else if ($analyzer == null && $type == self::FIELD_TYPE_SORT) {
                    $analyzer = 'sortable';
                }

                if ($analyzer != null) {
                    $field = $field . '.' . $analyzer;
                }
            }
        }

        return $field;
    }

    /**
     * Prepare the spelling fied during mapping generation
     *
     * @return array
     */
    protected function _getSpellingFieldMapping()
    {
        $mapping = array();
        foreach ($this->_stores as $store) {
            $languageCode = $this->_helper->getLanguageCodeByStore($store);
            $defaultAnalyzer = 'analyzer_' . $languageCode;
            $mapping['spelling_' . $languageCode]['type'] = 'multi_field';
            $spellcheckBaseFieldProperties = array('type' => 'string', 'store' => false, 'fielddata' => array('format' => 'disabled'));
            $mapping['spelling_' . $languageCode]['fields'] = array(
                'spelling_' . $languageCode => array_merge(array('analyzer' => $defaultAnalyzer), $spellcheckBaseFieldProperties),
                'whitespace'                => array_merge(array('analyzer' => 'whitespace'), $spellcheckBaseFieldProperties),
            );

            $mapping['autocomplete'] = array(
                'type' => 'string', 'store' => false, 'analyzer' => 'edge_ngram_front', 'fielddata' => array('format' => 'disabled'),
            );

            if ($this->getCurrentIndex()->isPhoneticSupported($languageCode)) {
                $mapping['spelling_' . $languageCode]['fields']['phonetic_' . $languageCode] = array_merge(
                    array('analyzer' => 'phonetic_' . $languageCode), $spellcheckBaseFieldProperties
                );
            }
        }
        return $mapping;
    }

    /**
     * Return mapping for an attribute of type varchar
     *
     * @param string $fieldName    Name of the field
     * @param string $languageCode Language code we want the mapping for
     * @param string $type         ES core type (string default)
     * @param bool   $sortable     Can the attribute be used for sorting
     * @param bool   $fuzzy        Can the attribute be used in fuzzy searches.
     * @param bool   $facet        Can the attribute be used as a facet.
     * @param bool   $autocomplete Can the attribute be used in autocomplete.
     *
     * @return array string
     */
    protected function _getStringMapping(
        $fieldName, $languageCode, $type = 'string', $sortable = false, $fuzzy = true, $facet = true, $autocomplete = true
    ) {
        $mapping = array();

        $analyzers = array('whitespace');

        $mapping[$fieldName] = array('type' => 'multi_field', 'fields' => array());
        $mapping[$fieldName]['fields'][$fieldName] = array(
            'type' => $type, 'analyzer' => 'analyzer_' . $languageCode, 'store' => false, 'fielddata' => array('format' => 'disabled')
        );

        if ($autocomplete == true || $facet == true) {
            $analyzers[] = 'edge_ngram_front';

            if ($facet == true) {
                $mapping[$fieldName]['fields']['untouched'] = array(
                    'type' => $type, 'index' => 'not_analyzed', 'store' => false, 'fielddata' => array('format' => 'doc_values')
                );
            }

            if ($autocomplete == true) {
                $mapping[$fieldName]['fields'][$fieldName]['copy_to'][] = 'autocomplete';
            }
        }

        if ($sortable == true) {
            $analyzers[] = 'sortable';
        }

        if ($fuzzy == true) {
            $mapping[$fieldName]['fields'][$fieldName]['copy_to'][] = 'spelling_' . $languageCode;
        }

        if ($this->getCurrentIndex()->isPhoneticSupported($languageCode)) {
            $analyzers[] = 'phonetic_' . $languageCode;
        }

        foreach ($analyzers as $analyzer) {
            $mapping[$fieldName]['fields'][$analyzer] = array('type' => $type, 'analyzer' => $analyzer, 'store' => false);

            if (isset($analyzersOptions[$analyzer])) {
                $mapping[$fieldName]['fields'][$analyzer] = array_merge(
                    $mapping[$fieldName]['fields'][$analyzer], $analyzersOptions[$analyzer]
                );
            }
        }

        return $mapping;
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
     * Get analyzer for a search type.
     *
     * @param string $languageCode Language code.
     * @param string $searchType   Search type.
     *
     * @return string
     */
    protected function _getDefaultAnalyzerBySearchType($languageCode, $searchType)
    {
        $analyzer = null;

        if ($searchType == self::SEARCH_TYPE_FUZZY) {
            $analyzer = 'whitespace';
        } else if ($searchType == self::SEARCH_TYPE_PHONETIC) {
            $analyzer = 'phonetic_' . $languageCode;
        } else if ($searchType == self::SEARCH_TYPE_AUTOCOMPLETE) {
            $analyzer = 'edge_ngram_front';
        }

        return $analyzer;
    }

    /**
     * As fields are copied into spelling or autocomplete, we can use a default field to reduce the number of fields
     * into multi_match query.
     * Kind of equivalent to _all fields but search type dependant.
     *
     * @param string $languageCode Language code.
     * @param string $searchType   Search type.
     *
     * @return string
     */
    protected function _getDefaultSearchFieldBySearchType($languageCode, $searchType)
    {
        $defaultSearchFields = array();

        if (in_array($searchType, array(self::SEARCH_TYPE_NORMAL, self::SEARCH_TYPE_FUZZY, self::SEARCH_TYPE_PHONETIC))) {
            $defaultSearchFields[] = 'spelling_' . $languageCode;
        } else if ($searchType == self::SEARCH_TYPE_AUTOCOMPLETE) {
            $defaultSearchFields[] = 'autocomplete';
        }
        return $defaultSearchFields;
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