<?php
/**
 * Elastic search engine abstract.
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
abstract class Smile_ElasticSearch_Model_Resource_Engine_Abstract
{
    const DEFAULT_ROWS_LIMIT = 9999;

    const UNIQUE_KEY = 'unique';

    /**
     * @var string List of advanced index fields prefix.
     */
    protected $_advancedIndexFieldsPrefix = '#';

    /**
     * @var array List of advanced dynamic index fields.
     */
    protected $_advancedDynamicIndexFields = array(
        '#position_category_',
        '#price_'
    );

    /**
     * @var object Search engine client.
     */
    protected $_client;

    /**
     * @var array List of dates format.
     */
    protected $_dateFormats = array();

    /**
     * @var array List of default query parameters.
     */
    protected $_defaultQueryParams = array(
        'offset' => 0,
        'limit' => 100,
        'sort_by' => array(array('relevance' => 'desc')),
        'store_id' => null,
        'locale_code' => null,
        'fields' => array(),
        'params' => array(),
        'ignore_handler' => false,
        'filters' => array(),
    );

    /**
     * @var array List of indexable attribute parameters.
     */
    protected $_indexableAttributeParams = array();

    /**
     * @var int Last number of results found.
     */
    protected $_lastNumFound;

    /**
     * @var array List of non fulltext fields.
     */
    protected $_notInFulltextField = array(
        self::UNIQUE_KEY,
        'id',
        'store_id',
        'in_stock',
        'categories',
        'show_in_categories',
        'visibility'
    );

    /**
     * @var bool Stores search engine availibility
     */
    protected $_test = null;

    /**
     * @var array List of used fields.
     */
    protected $_usedFields = array(
        self::UNIQUE_KEY,
        'id',
        'sku',
        'price',
        'store_id',
        'categories',
        'show_in_categories',
        'visibility',
        'in_stock',
        'score'
    );

    /**
     * Adds advanced index fields to index data.
     *
     * @param array $index      Product data
     * @param int   $storeId    Store id
     * @param array $productIds Product ids
     *
     * @return array
     */
    public function addAdvancedIndex($index, $storeId, $productIds = null)
    {
        return Mage::getResourceSingleton('smile_elasticsearch/engine_index')
            ->addAdvancedIndex($index, $storeId, $productIds);
    }

    /**
     * Returns advanced search results.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function getAdvancedResultCollection()
    {
        return $this->getResultCollection();
    }

    /**
     * Checks if advanced index is allowed for current search engine.
     *
     * @return bool
     */
    public function allowAdvancedIndex()
    {
        return true;
    }

    /**
     * Cleans cache.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Abstract
     */
    public function cleanCache()
    {
        return $this;
    }

    /**
     * Returns product visibility ids for search.
     *
     * @see Mage_Catalog_Model_Product_Visibility
     *
     * @return mixed
     */
    public function getAllowedVisibility()
    {
        return Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
    }

    /**
     * Returns advanced index fields prefix.
     *
     * @return string
     */
    public function getFieldsPrefix()
    {
        return $this->_advancedIndexFieldsPrefix;
    }

    /**
     * Retrieves product ids for specified query.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (filters, facets, ...)
     * @param string $type   Document type to be searched
     *
     * @return array
     */
    public function getIdsByQuery($query, $params = array(), $type = 'product')
    {
        $ids = array();
        $params['fields'] = array('id');
        $resultTmp = $this->search($query, $params, $type);
        if (!empty($resultTmp['docs'])) {
            foreach ($resultTmp['docs'] as $doc) {
                $ids[] = $doc['id'];
            }
        }
        $result = array(
            'ids' => $ids,
            'total_count'     => (isset($resultTmp['total_count'])) ? $resultTmp['total_count'] : null,
            'faceted_data'    => (isset($resultTmp['facets'])) ? $resultTmp['facets'] : array(),
            'is_spellchecked' => (isset($resultTmp['is_spellchecked'])) ? $resultTmp['is_spellchecked'] : false,
        );

        return $result;
    }

    /**
     * Returns resource name.
     *
     * @return string
     */
    public function getResourceName()
    {
        return 'smile_elasticsearch/advanced';
    }

    /**
     * Returns last number of results found.
     *
     * @return int
     */
    public function getLastNumFound()
    {
        return $this->_lastNumFound;
    }

    /**
     * Returns catalog product collection with current search engine set.
     *
     * @return Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection
     */
    public function getResultCollection()
    {
        return Mage::getResourceModel('smile_elasticsearch/catalog_product_collection')->setEngine($this);
    }

    /**
     * Retrieves stats for specified query.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (filters, facets, ...)
     * @param string $type   Document type to be searched
     *
     * @return array
     */
    public function getStats($query, $params = array(), $type = 'product')
    {
        return $this->_search($query, $params, $type);
    }

    /**
     * Alias of isLayeredNavigationAllowed.
     *
     * @return bool
     */
    public function isLeyeredNavigationAllowed()
    {
        return $this->isLayeredNavigationAllowed();
    }

    /**
     * Checks if layered navigation is available for current search engine.
     *
     * @return bool
     */
    public function isLayeredNavigationAllowed()
    {
        return true;
    }

    /**
     * Prepares index data.
     * Should be overriden in child classes if needed.
     *
     * @param array  $index     Indexed data
     * @param string $separator Field separator into the index
     *
     * @return array
     */
    public function prepareEntityIndex($index, $separator = null)
    {
        return $this->_getHelper()->prepareIndexData($index, $separator);
    }

    /**
     * Performs search query and facetting.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (filters, facets, ...)
     * @param string $type   Document type to be searched
     *
     * @return array
     */
    public function search($query, $params = array(), $type = 'product')
    {
        try {
            Varien_Profiler::start('ELASTICSEARCH');
            $result = $this->_search($query, $params, $type);
            Varien_Profiler::stop('ELASTICSEARCH');
            return $result;
        } catch (Exception $e) {
            Mage::logException($e);
            if ($this->_getHelper()->isDebugEnabled()) {
                $this->_getHelper()->showError($e->getMessage());
            }
        }

        return array();
    }

    /**
     * Checks search engine availability.
     * Should be overriden by child classes.
     *
     * @return bool
     */
    public function test()
    {
        return true;
    }

    /**
     * Transforms specified date to basic YYYY-MM-dd format.
     *
     * @param int    $storeId Current store id
     * @param string $date    Date to be transformed
     *
     * @return null|string
     */
    protected function _getDate($storeId, $date = null)
    {
        if (!isset($this->_dateFormats[$storeId])) {
            $timezone = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $storeId);
            $locale   = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
            $locale   = new Zend_Locale($locale);

            $dateObj  = new Zend_Date(null, null, $locale);
            $dateObj->setTimezone($timezone);
            $this->_dateFormats[$storeId] = array($dateObj, $locale->getTranslation(null, 'date', $locale));
        }

        if (is_empty_date($date)) {
            return null;
        }

        list($dateObj, $localeDateFormat) = $this->_dateFormats[$storeId];
        $dateObj->setDate($date, $localeDateFormat);

        return $dateObj->toString('YYYY-MM-dd');
    }

    /**
     * Returns search helper.
     *
     * @return Smile_ElasticSearch_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('smile_elasticsearch');
    }

    /**
     * Returns indexable attribute parameters.
     *
     * @return array
     */
    protected function _getIndexableAttributeParams()
    {
        if (null === $this->_indexableAttributeParams) {
            $this->_indexableAttributeParams = array();
            $attributes = $this->_getHelper()->getSearchableAttributes();
            foreach ($attributes as $attribute) {
                /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
                $this->_indexableAttributeParams[$attribute->getAttributeCode()] = array(
                    'backend_type'   => $attribute->getBackendType(),
                    'frontend_input' => $attribute->getFrontendInput(),
                    'search_weight'  => $attribute->getSearchWeight(),
                    'is_searchable'  => $attribute->getIsSearchable()
                );
            }
        }

        return $this->_indexableAttributeParams;
    }

    /**
     * Returns store locale code.
     *
     * @param int $storeId Store Id
     *
     * @return string
     */
    protected function _getLocaleCode($storeId = null)
    {
        return $this->_getHelper()->getLocaleCode($storeId);
    }

    /**
     * Transforms specified object to an array.
     *
     * @param object $object Source object
     *
     * @return array
     */
    protected function _objectToArray($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }
        if (is_object($object)) {
            $object = get_object_vars($object);
        }

        return array_map(array($this, '_objectToArray'), $object);
    }

    /**
     * Perpare document to be indexed
     *
     * @param array  $docsData   Source document data to be indexed
     * @param string $type       Document type
     * @param string $localeCode Locale indexed
     *
     * @return array
     */
    protected function _prepareDocs($docsData, $type, $localeCode = null)
    {
        if (!is_array($docsData) || empty($docsData)) {
            return array();
        }

        $docs = array();
        
        foreach ($docsData as $entityId => $index) {
            $index[self::UNIQUE_KEY] = $entityId . '|' . $index['store_id'];
            $index['id'] = $entityId;
            $suggestFieldName = $this->_getHelper()->getSuggestFieldNameByLocaleCode($localeCode);
            $index[$suggestFieldName] = array(
                'input' => array($index['name']),
                'payload' => array('product_id' => $entityId)
            );
            $index = $this->_prepareIndexData($index, $localeCode);
            $docs[] = $this->_createDoc($entityId, $index, $type);
        }

        return $docs;
    }

    /**
     * Prepares index data before indexation.
     *
     * @param array  $data       Document data
     * @param string $localeCode Current locale
     *
     * @return array
     */
    protected function _prepareIndexData($data, $localeCode = null)
    {
        if (!is_array($data) || empty($data)) {
            return array();
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $this->_usedFields)) {
                continue;
            } elseif ($key == 'options') {
                unset($data[$key]);
                continue;
            }
            $field = $this->_getHelper()->getAttributeFieldName($key, $localeCode);
            $field = str_replace($this->_advancedIndexFieldsPrefix, '', $field);
            if ($field != $key) {
                $data[$field] = $value;
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Prepares query before search.
     *
     * @param mixed $query Fulltext query
     *
     * @return string
     */
    protected function _prepareSearchConditions($query)
    {
        return $query;
    }

    /**
     * Cleans index.
     *
     * @param int    $storeId Store id to clean
     * @param int    $id      Document id to clean
     * @param string $type    Document type to clean
     *
     * @return mixed
     *
     * @abstract
     */
    abstract public function cleanIndex($storeId = null, $id = null, $type = 'product');

    /**
     * Deletes index.
     *
     * @abstract
     *
     * @return mixed
     */
    abstract public function deleteIndex();

    /**
     * Saves products data in index.
     *
     * @param int    $storeId Store id of the document
     * @param array  $indexes Document data
     * @param string $type    Document type
     *
     * @return mixed
     *
     * @abstract
     */
    abstract public function saveEntityIndexes($storeId, $indexes, $type = 'product');

    /**
     * Adds documents to index.
     *
     * @param array $docs Docuement to be added
     *
     * @return mixed
     *
     * @abstract
     */
    abstract protected function _addDocs($docs);

    /**
     * Creates and prepares document for indexation.
     *
     * @param int    $entityId Document id
     * @param array  $index    Document data
     * @param string $type     Document type
     *
     * @return mixed
     *
     * @abstract
     */
    abstract protected function _createDoc($entityId, $index, $type = 'product');

    /**
     * Prepares facets query response.
     *
     * @param mixed $response Response to be parsed
     *
     * @return mixed
     *
     * @abstract
     */
    abstract protected function _prepareFacetsQueryResponse($response);

    /**
     * Prepares query response.
     *
     * @param mixed $response Response to be parsed
     *
     * @return mixed
     *
     * @abstract
     */
    abstract protected function _prepareQueryResponse($response);

    /**
     * Performs search and facetting for specified query and parameters.
     *
     * @param string $query  Fulltext query
     * @param array  $params Search params (facets, filters, ...)
     * @param string $type   Document type to be searched
     *
     * @return mixed
     *
     * @abstract
     */
    abstract protected function _search($query, $params = array(), $type = 'product');
}
