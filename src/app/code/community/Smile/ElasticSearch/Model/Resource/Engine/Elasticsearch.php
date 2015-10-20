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
 */
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
{

    /**
     * @var string
     */
    const CACHE_INDEX_PROPERTIES_ID = 'elasticsearch_index_properties';

    /**
     * @var string
     */
    const UNIQUE_KEY = 'unique';

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
     * @var bool
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
     * Date formats used by the index.
     *
     * @var array()
     */
    protected $_dateFormats = array();

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
        if (is_null($id)) {
            return $this;
        } else if (!is_array($id)) {
            $id = array($id);
        }

        if (is_null($storeId)) {
            $storeId = array_keys(Mage::app()->getStores());
        } else if (!is_array($storeId)) {
            $storeId = array($storeId);
        }

        $bulk = array('body' => array());

        foreach ($id as $currentId) {
            foreach ($storeId as $currentStoreId) {
                $bulk['body'][] = array(
                    'delete' => array(
                        '_index' => $this->getCurrentIndex()->getCurrentName(),
                        '_type'  => $type,
                        '_id'    => $currentId . '|' .$currentStoreId
                    )
                );
            }
        }

        if (!empty($bulk['body'])) {
            $this->getClient()->bulk($bulk);
        }

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
        $object = new Varien_Object();
        $eventDatas = array(
            'type'    => $type,
            'indexes' => $object->setBulk($indexes),
            'engine'  => $this,
        );
        Mage::dispatchEvent('search_engine_save_entity_index_before', $eventDatas);
        Mage::dispatchEvent('search_engine_save_'.(string) $type.'_index_before', $eventDatas);

        $docs = $this->_prepareDocs($object->getBulk(), $type);
        $this->getCurrentIndex()->addDocuments($docs);

        Mage::dispatchEvent('search_engine_save_entity_index_after', $eventDatas);
        Mage::dispatchEvent('search_engine_save_'.(string) $type.'_index_after', $eventDatas);
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

        return $this->_test;
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
     * @param string $type  Type of document for the query.
     * @param string $model Query model name (fulltext, autocomplete, ...).
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function createQuery($type, $model = 'smile_elasticsearch/engine_elasticsearch_query_fulltext')
    {
        $query = Mage::getResourceModel($model)
            ->setAdapter($this)
            ->setType($type);

        return $query;
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
     * @param array  $docsData Source document data to be indexed
     * @param string $type     Document type
     *
     * @return array
     */
    protected function _prepareDocs($docsData, $type)
    {
        if (! is_array($docsData) || empty($docsData)) {
            return array();
        }

        $docs = array();
        foreach ($docsData as $entityId => $index) {
            $document = $this->getCurrentIndex()->createDocument($index[self::UNIQUE_KEY], $index, $type);
            array_push($docs, $document[0]);
            array_push($docs, $document[1]);
        }

        return $docs;
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
