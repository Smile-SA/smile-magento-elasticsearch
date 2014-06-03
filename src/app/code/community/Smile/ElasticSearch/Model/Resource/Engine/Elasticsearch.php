<?php
/**
 * Elastic search engine.
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

// Include the Elasticsearch required libraries used by the adapter
require_once 'vendor/autoload.php';

/**
 * Elastic search engine.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 *
 */
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
{

    const CACHE_INDEX_PROPERTIES_ID = 'elasticsearch_index_properties';

    const UNIQUE_KEY = 'unique';

    /**
     *
     * @var string List of advanced index fields prefix.
     */
    protected $_advancedIndexFieldsPrefix = '#';

    /**
     *
     * @var array List of default query parameters.
     */
    protected $_defaultQueryParams = array(
        'offset' => 0,
        'limit' => 100,
        'sort_by' => array(
            array(
                'relevance' => 'desc'
            )
        ),
        'store_id' => null,
        'locale_code' => null,
        'fields' => array(),
        'params' => array(),
        'ignore_handler' => false,
        'filters' => array()
    );

    /**
     *
     * @var array List of indexable attribute parameters.
     */
    protected $_indexableAttributeParams = array();

    /**
     *
     * @var bool Stores search engine availibility
     */
    protected $_test = null;

    /**
     *
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
     *
     * @var Varien_Object
     */
    protected $_config;

    /**
     *
     * @var Elasticsearch\Client
     */
    protected $_client = null;

    /**
     *
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index
     */
    protected $_currentIndex = null;

    /**
     *
     * @var string
     */
    protected $_currentIndexName = null;

    /**
     *
     * @var bool
     */
    protected $_indexNeedInstall = false;

    /**
     *
     * @var string Date format.
     * @link http://www.elasticsearch.org/guide/reference/mapping/date-format.html
     */
    protected $_dateFormat = 'date';

    /**
     * Initializes search engine config and index name.
     *
     * @param array|bool $params Client init params.
     */
    public function __construct($params = false)
    {
        $config = $this->_getHelper()->getEngineConfigData();

        $this->_config = new Varien_Object($config);

        $this->_client = new \Elasticsearch\Client(array('hosts' => $config['hosts'], 'logging' => false));
        // parent::__construct($config);
        if (! isset($config['alias'])) {
            Mage::throwException('Alias must be defined for search engine client.');
        }

        $this->_currentIndex = Mage::getResourceModel('smile_elasticsearch/engine_elasticsearch_index');
        $this->_currentIndex->setAdapter($this)->setCurrentName($config['alias']);
    }

    /**
     * Get the ElasticSearch client instance
     *
     * @return \Elasticsearch\Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Return the current index instance
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Index
     */
    public function getCurrentIndex()
    {
        return $this->_currentIndex;
    }

    /**
     * Cleans caches.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    public function cleanCache()
    {
        Mage::app()->removeCache(self::CACHE_INDEX_PROPERTIES_ID);

        return $this;
    }

    /**
     * Cleans index.
     *
     * @param int    $storeId Store ind to be cleaned
     * @param int    $id      Document id to be cleaned
     * @param string $type    Document type to be cleaned
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    public function cleanIndex($storeId = null, $id = null, $type = 'product')
    {
        // $this->getClient()->cleanIndex($storeId, $id, $type);
        return $this;
    }

    /**
     * Saves products data in index.
     *
     * @param int    $storeId Store id
     * @param array  $indexes Documents data
     * @param string $type    Documents type
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    public function saveEntityIndexes($storeId, $indexes, $type = 'product')
    {
        $indexes = $this->addAdvancedIndex($indexes, $storeId, array_keys($indexes));

        $helper = $this->_getHelper();
        $store = Mage::app()->getStore($storeId);
        $localeCode = $helper->getLocaleCode($store);
        $searchables = $helper->getSearchableAttributes();
        $sortables = $helper->getSortableAttributes();

        foreach ($indexes as &$data) {
            foreach ($data as $key => &$value) {
                if (is_array($value) && strpos($key, 'suggest') !== 0) {
                    $value = array_values(array_filter(array_unique($value)));
                }
                if (array_key_exists($key, $searchables)) {
                    /**
                     * @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute
                     */
                    $attribute = $searchables[$key];
                    if ($attribute->getBackendType() == 'datetime') {
                        foreach ($value as &$date) {
                            $date = $this->_getDate($store->getId(), $date);
                        }
                        unset($date);
                    } elseif ($attribute->usesSource() && ! empty($value)) {
                        if ($attribute->getFrontendInput() == 'multiselect') {
                            $value = explode(',', is_array($value) ? $value[0] : $value);
                        } elseif ($helper->isAttributeUsingOptions($attribute)) {
                            $val = is_array($value) ? $value[0] : $value;
                            if (! isset($data['_options'])) {
                                $data['_options'] = array();
                            }
                            $option = $attribute->setStoreId($storeId)
                                ->getFrontend()
                                ->getOption($val);
                            $data['_options'][] = $option;
                        }
                    }
                }
                if (array_key_exists($key, $sortables)) {
                    $val = is_array($value) ? current($value) : $value;
                    /**
                     * @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute
                     */
                    $attribute = $sortables[$key];
                    $attribute->setStoreId($store->getId());
                    $key = $helper->getSortableAttributeFieldName($sortables[$key], $localeCode);
                    if ($attribute->usesSource()) {
                        $val = $attribute->getFrontend()->getOption($val);
                    } elseif ($attribute->getBackendType() == 'decimal') {
                        $val = (double) $val;
                    }
                    $data[$key] = $val;
                }
            }
            unset($value);
            $data['store_id'] = $store->getId();
        }
        unset($data);

        $docs = $this->_prepareDocs($indexes, $type, $localeCode);
        $this->getCurrentIndex()->addDocuments($docs);

        return $this;
    }

    /**
     * Checks Elasticsearch availability.
     *
     * @return bool
     */
    public function test()
    {
        if (null !== $this->_test) {
            return $this->_test;
        }

        try {
            $this->_test = $this->getStatus();
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_test = false;
        }

        if ($this->_test === false && $this->_getHelper()->isDebugEnabled()) {
            $this->_getHelper()->showError('Elasticsearch engine is not available');
        }

        return $this->_test;
    }

    /**
     * Run autocomplete for products on the search engigne
     *
     * @param string $text Text to be autocompleted
     *
     * @return array
     */
    public function suggest($text)
    {
        $suggestFieldName = $this->_getHelper()->getSuggestFieldName();
        $params = array(
            'index' => $this->_currentIndex->getCurrentName()
        );
        $params['body']['suggestions'] = array(
            'text' => $text,
            'completion' => array(
                'field' => $suggestFieldName,
                'fuzzy' => array(
                    'fuzziness' => 1,
                    'unicode_aware' => true
                )
            )
        );

        $response = $this->_client->suggest($params);

        $data = array();

        if (! isset($response['error']) && isset($response['suggestions'])) {
            $suggestions = current($response['suggestions']);
            foreach ($suggestions['options'] as $suggestion) {
                $data[] = $suggestion;
            }
        }

        return $data;
    }

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
        return Mage::getResourceSingleton('smile_elasticsearch/engine_index')->addAdvancedIndex($index, $storeId, $productIds);
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
     * Return a new query instance
     *
     * @param string $type Type of document for the query
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query
     */
    public function createQuery($type)
    {
        $query = Mage::getResourceModel('smile_elasticsearch/engine_elasticsearch_query')->setAdapter($this)->setType($type);

        return $query;
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
     * Returns resource name.
     *
     * @return string
     */
    public function getResourceName()
    {
        return 'smile_elasticsearch/advanced';
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
     * Transforms specified date to basic YYYY-MM-dd format.
     *
     * @param int    $storeId Current store id
     * @param string $date    Date to be transformed
     *
     * @return null string
     */
    protected function _getDate($storeId, $date = null)
    {
        if (! isset($this->_dateFormats[$storeId])) {
            $timezone = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $storeId);
            $locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
            $locale = new Zend_Locale($locale);

            $dateObj = new Zend_Date(null, null, $locale);
            $dateObj->setTimezone($timezone);
            $this->_dateFormats[$storeId] = array(
                $dateObj,
                $locale->getTranslation(null, 'date', $locale)
            );
        }

        if (is_empty_date($date)) {
            return null;
        }

        list ($dateObj, $localeDateFormat) = $this->_dateFormats[$storeId];
        $dateObj->setDate($date, $localeDateFormat);

        return $dateObj->toString('YYYY-MM-dd');
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
        if (! is_array($docsData) || empty($docsData)) {
            return array();
        }

        $docs = array();

        foreach ($docsData as $entityId => $index) {
            $index[self::UNIQUE_KEY] = $entityId . '|' . $index['store_id'];
            $index['id'] = $entityId;
            $weight = 1;
            if ($type == 'product') {
                $this->_getSuggestionWeight($index);
            }

            $suggestFieldName = $this->_getHelper()->getSuggestFieldNameByLocaleCode($localeCode);

            if (! isset($index[$suggestFieldName]) && $weight) {

                $input = $index['name'];
                if (isset($index['sku'])) {
                    $input[] = $index['sku'];
                }
                $index[$suggestFieldName] = array(
                    'input' => $input,
                    'payload' => array(
                        'product_id' => $entityId
                    ),
                    'weight' => $weight
                );
            }
            $index = $this->_prepareIndexData($index, $localeCode);
            $docs[] = $this->getCurrentIndex()->createDocument($index[self::UNIQUE_KEY], $index, $type);
        }

        return $docs;
    }

    /**
     * Indicates if product should be suggested or not
     *
     * @param array $data Product data
     *
     * @return boolean
     */
    protected function _getSuggestionWeight($data)
    {
        $visibilityWeight = array(
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE => 0,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG => 1,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH => 1,
            Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH => 2
        );

        $result = isset($data['visibility']) && isset($data['status']) ? $visibilityWeight[current($data['visibility'])] : 0;
        $result = current($data['status']) == Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? $result : 0;

        return $result;
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
        if (! is_array($data) || empty($data)) {
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
     * Indicates if connection to the search engine is up or not
     *
     * @return bool
     */
    public function getStatus()
    {
        return $this->_client->ping();
    }

    /**
     * Read configuration from key
     *
     * @param string $key Name of the config param to retrieve
     *
     * @return mixed
     */
    public function getConfig($key)
    {
        return $this->_config->getData($key);
    }

    /**
     * Returns search helper.
     *
     * @return Smile_ElasticSearch_Helper_Elasticsearch
     */
    protected function _getHelper()
    {
        return Mage::helper('smile_elasticsearch/elasticsearch');
    }
}
