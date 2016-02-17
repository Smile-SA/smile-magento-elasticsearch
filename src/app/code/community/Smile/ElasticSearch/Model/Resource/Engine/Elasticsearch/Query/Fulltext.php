<?php
/**
 * ElaticSearch query model
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Fulltext
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
{

    /**
     * Already analyzed queries cache.
     *
     * @var array
     */
    protected static $_analyzedQueries = array();

    /**
     * Already assembed queries cache.
     *
     * @var array
     */
    protected static $_assembledQueries = array();

    /**
     * @var string
     */
    const MIN_SHOULD_MATCH_CONFIG_XMLPATH = 'elasticsearch_advanced_search_settings/fulltext_relevancy/search_minimum_should_match';

    /**
     * @var string
     */
    const RELEVANCY_SETTINGS_BASE_PATH    = 'elasticsearch_advanced_search_settings/fulltext_relevancy/';
    /**
     * @var string
     */
    const MAX_FUZZINESS = 2;

    /**
     * @var int
     */
    const SPELLING_TYPE_EXACT      = 0;

    /**
     * @var int
     */
    const SPELLING_TYPE_MOST_EXACT = 1;

    /**
     * @var int
     */
    const SPELLING_TYPE_MOST_FUZZY = 2;

    /**
     * @var int
     */
    const SPELLING_TYPE_FUZZY      = 3;

    /**
     * @var int
     */
    const SPELLING_TYPE_PURE_STOPWORDS = 4;

    /**
     * Returns the minimum should match clause from config.
     *
     * @return string
     */
    protected function _getMinimumShouldMatch()
    {
        return (string) Mage::getStoreConfig(self::MIN_SHOULD_MATCH_CONFIG_XMLPATH);
    }

    /**
     * Return the value of the boost applied on phrase match. False if disabled.
     *
     * @return bool|int
     */
    protected function _getPhraseMatchBoost()
    {
        $boostValue = (bool) Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'enable_phrase_match');
        if ($boostValue !== false) {
            $boostValue = (int) Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'phrase_match_boost_value');
        }
        return $boostValue;
    }

    /**
     * Return the configuration of the cutoff frequency
     *
     * @return float
     */
    protected function _getCutOffFrequency()
    {
        return (float) Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'cutoff_frequency');
    }

    /**
     * Default field used in standard search.
     *
     * @return string
     */
    protected function _getDefaultSearchField()
    {
        return 'search_' . $this->getLanguageCode();
    }

    /**
     * Returns the list of fields used in standard search with their respective weights.
     *
     * @return array
     */
    protected function _getWeightedSearchFields()
    {
        return $this->getSearchFields(Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL);
    }

    /**
     * Default field used in standard search spellechecking
     *
     * @return string
     */
    protected function _getSpellingBaseField()
    {
        return 'spelling_' . $this->getLanguageCode();
    }

    /**
     * List of analyzers used by the spellchecker.
     *
     * @return array
     */
    protected function _getSpellingAnalayzers()
    {
        return array('whitespace', 'none');
    }

    /**
      * Build the fulltext query condition for the query.
      *
      * @return array
      */
    protected function _prepareFulltextCondition()
    {
        $query = array('match_all' => array());

        if ($this->_fulltextQuery && is_string($this->_fulltextQuery)) {
            $spellingType = $this->_analyzeSpelling($this->_fulltextQuery);

            if (!in_array($spellingType, array(self::SPELLING_TYPE_EXACT, self::SPELLING_TYPE_PURE_STOPWORDS))) {
                $this->_isSpellChecked = true;
            }

            if (!isset(self::$_assembledQueries[$this->_fulltextQuery])) {
                self::$_assembledQueries[$this->_fulltextQuery] = $this->_buildFulltextQuery($this->_fulltextQuery, $spellingType);
            }

            $query = self::$_assembledQueries[$this->_fulltextQuery];
        }

        return $query;
    }

    /**
     * Build the fulltext query.
     *
     * @param string $textQuery    Text submitted by the user.
     * @param int    $spellingType Type of spelling applied.
     *
     * @return array
     */
    protected function _buildFulltextQuery($textQuery, $spellingType)
    {
        $query = array();

        if ($this->isSpellchecked()) {
            $fuzzyQuery = $this->_getFuzzyQueryMatch($textQuery, $spellingType);
            if ($fuzzyQuery !== false) {
                if (in_array($spellingType, array(self::SPELLING_TYPE_FUZZY, self::SPELLING_TYPE_MOST_FUZZY))) {
                    $query['bool']['must'][] = $fuzzyQuery;
                } else {
                    $query['bool']['should'][] = $fuzzyQuery;
                }
            }
        }

        if ($spellingType != self::SPELLING_TYPE_FUZZY || empty($query)) {
            $exactMacthQuery = $this->_getExactQueryMatch($textQuery, $spellingType);
            if ($exactMacthQuery !== false) {
                $clause = 'must';
                if ($spellingType == self::SPELLING_TYPE_MOST_FUZZY) {
                    $clause = 'should';
                }
                $query['bool'][$clause][] = $exactMacthQuery;
            }
        }
        $query = $this->_addPhraseOptimizations($query, $textQuery, $spellingType);

        return $query;
    }

    /**
     * Build the query part for correctly spelled query part.
     *
     * @param string $textQuery    Text submitted by the user.
     * @param int    $spellingType Type of spelling applied.
     *
     * @return string
     */
    protected function _getExactQueryMatch($textQuery, $spellingType)
    {
        $query = false;
        $weightedMultiMatchQuery = array(
            'query' => $textQuery , 'type' => 'best_fields', 'tie_breaker' => 1, 'fields' => $this->_getWeightedSearchFields()
        );

        if ($spellingType == self::SPELLING_TYPE_PURE_STOPWORDS) {
            $weightedMultiMatchQuery['minimum_should_match'] = "100%";
            $query = array('multi_match' => $weightedMultiMatchQuery);
        } else {
            if ($spellingType == self::SPELLING_TYPE_MOST_FUZZY) {
                $query = array('multi_match' => $weightedMultiMatchQuery);
            } else {
                $cutoffFrequency = $this->_getCutOffFrequency();
                $weightedMultiMatchQuery['cutoff_frequency'] = $cutoffFrequency;
                $defaultSearchField = $this->_getDefaultSearchField();
                $filterQuery = array('query' => $textQuery, 'cutoff_frequency' => $cutoffFrequency);
                $filterQuery['minimum_should_match'] = array('low_freq' => $this->_getMinimumShouldMatch());
                $query = array(
                    'filtered' => array(
                        'query' => array('multi_match' => $weightedMultiMatchQuery),
                        'filter' => array('query' => array('common' => array($defaultSearchField => $filterQuery))),
                    ),
                );
            }
        }

        return $query;
    }

    /**
     * Append phrase optimization to the user query.
     *
     * @param array  $query        Query to be optimized.
     * @param strint $textQuery    Text submitted by the user.
     * @param int    $spellingType Type of spelling applied.
     *
     * @return array
     */
    protected function _addPhraseOptimizations($query, $textQuery, $spellingType)
    {
        $phraseBoostValue = $this->_getPhraseMatchBoost();

        if ($phraseBoostValue !== false) {

            $defaultSearchField = $this->_getDefaultSearchField();
            $optimizationFunctions = array();

            if (str_word_count($textQuery) > 1) {
                $qs = array('query' => $textQuery, 'default_field' => $defaultSearchField . '.shingle');
                $qsFilter = array('query' => array('query_string' => $qs));
                $optimizationFunctions[] = array('filter' => $qsFilter, 'boost_factor' => $phraseBoostValue);
            }

            if (!in_array($spellingType, array(self::SPELLING_TYPE_PURE_STOPWORDS, self::SPELLING_TYPE_FUZZY))) {
                $qs = array('query' => $textQuery, 'cutoff_frequency' => $this->_getCutOffFrequency());
                $qsFilter = array('query' => array('common' => array($defaultSearchField . '.whitespace' => $qs)));
                $optimizationFunctions[] = array('filter' => $qsFilter, 'boost_factor' => $phraseBoostValue);
            }

            if (!empty($optimizationFunctions)) {
                $query = array('function_score' => array('query' => $query, 'functions' => $optimizationFunctions));
            }
        }

        return $query;
    }

    /**
     * Retrieve the list of fields used in fuzzy search (weighted).
     *
     * @return array
     */
    protected function _getFuzzySearchFields()
    {
        $fuzzySearchFields =  $this->getSearchFields(
            Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL, 'whitespace'
        );
        $fuzzySearchFields[] = $this->_getDefaultSearchField() . '.shingle';
        return $fuzzySearchFields;
    }
    /**
     * Retrieve fuzziness configuration for fulltext queries. False if fuzziness is disabled.
     *
     * @return array|boolean
     */
    protected function _getFuzzinessConfig()
    {
        $fuzzinessConfig = (bool) Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'enable_fuzziness');
        if ($fuzzinessConfig) {
            $fuzzySearchFields = $this->_getFuzzySearchFields();
            $fuzzinessConfig = array(
                'fields'           => $fuzzySearchFields,
                'fuzziness'        => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_value'),
                'prefix_length'    => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_prefix_length'),
                'max_expansions'   => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_max_expansions'),
                'cutoff_frequency' => $this->_getCutOffFrequency(),
            );
        }

        return $fuzzinessConfig;
    }

    /**
     * Retrieve the list of fields used in phonetic search (weighted).
     *
     * @return array
     */
    protected function _getPhoneticSearchFields()
    {
        return $this->getSearchFields(
            Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL, 'phonetic'
        );
    }

    /**
     * Retrieve phonetic configuration for fulltext queries. False if phonetic search is disabled.
     *
     * @return array|boolean
     */
    protected function _getPhoneticConfig()
    {
        $phoneticConfig = false;
        $languageCode   = $this->getLanguageCode();
        $configPrexfix  = self::RELEVANCY_SETTINGS_BASE_PATH;
        $isSupported    = $this->getAdapter()->getCurrentIndex()->isPhoneticSupported($languageCode);
        $isEnabled      = Mage::getStoreConfig($configPrexfix . 'enable_phonetic_search');

        if ($isSupported && $isEnabled) {
            $phoneticAnalyzer = 'phonetic_' . $languageCode;
            $phoneticSearchFields = $this->_getPhoneticSearchFields();
            $phoneticConfig = array('analyzer' => $phoneticAnalyzer, 'fields' => $phoneticSearchFields);
            if ((bool) Mage::getStoreConfig($configPrexfix . 'enable_phonetic_search_fuzziness')) {
                $fuzzinessConfig = array(
                    'fuzziness'        => Mage::getStoreConfig($configPrexfix . 'phonetic_search_fuzziness_value'),
                    'prefix_length'    => Mage::getStoreConfig($configPrexfix . 'phonetic_search_fuzziness_prefix_length'),
                    'max_expansions'   => Mage::getStoreConfig($configPrexfix . 'phonetic_search_fuzziness_max_expansions'),
                    'cutoff_frequency' => $this->_getCutOffFrequency(),
                );
                $phoneticConfig = array_merge($phoneticConfig, $fuzzinessConfig);
            }
        }

        return $phoneticConfig;
    }

    /**
     * Build the query part for incorrect spelling matching.
     *
     * @param string $textQuery    Text submitted by the user.
     * @param int    $spellingType Type of spelling applied.
     *
     * @return string
     */
    protected function _getFuzzyQueryMatch($textQuery, $spellingType)
    {
        $fuzzyQuery = array();

        $matchQuery = array('query' => $textQuery, 'type' => 'best_fields', 'minimum_should_match' => "100%");

        $fuzzinessConfig = $this->_getFuzzinessConfig();
        if ($fuzzinessConfig !== false) {
            $fuzzyQuery['bool']['should'][] = array('multi_match' => array_merge($matchQuery, $fuzzinessConfig));
        }

        $phoneticConfig = $this->_getPhoneticConfig();
        if ($phoneticConfig !== false) {
            $fuzzyQuery['bool']['should'][] = array('multi_match' => array_merge($matchQuery, $phoneticConfig));
        }

        if (!isset($fuzzyQuery['bool'])) {
            $fuzzyQuery = false;
        } else if (count($fuzzyQuery['bool']['should']) == 1) {
            $fuzzyQuery = current($fuzzyQuery['bool']['should']);
        }

        return $fuzzyQuery;
    }

    /**
     * Try to detect if user mispelled some words.
     *
     * @param string $textQuery Query issued by the customer.
     *
     * @return int
     */
    protected function _analyzeSpelling($textQuery)
    {
        if (!isset(self::$_analyzedQueries[$textQuery])) {
            $queryTermStats = $this->_getQueryTermStats($textQuery);

            $spellingType = self::SPELLING_TYPE_FUZZY;
            if ($queryTermStats['total'] == $queryTermStats['stop']) {
                $spellingType = self::SPELLING_TYPE_PURE_STOPWORDS;
            } else if ($queryTermStats['total'] == $queryTermStats['stop'] + $queryTermStats['exact']) {
                $spellingType = self::SPELLING_TYPE_EXACT;
            } else if ($queryTermStats['missing'] == 0) {
                $spellingType = self::SPELLING_TYPE_MOST_EXACT;
            } else if ($queryTermStats['total'] - $queryTermStats['missing'] > 0) {
                $spellingType = self::SPELLING_TYPE_MOST_FUZZY;
            }

            $spellingType = $this->_fixSpellingType($textQuery, $spellingType);

            self::$_analyzedQueries[$textQuery] = $spellingType;
        }

        return self::$_analyzedQueries[$textQuery];
    }

    /**
     * Retrieve statistics on spelling of the user isssued query.
     *
     * @param string $textQuery Query issued by the customer.
     *
     * @return array
     */
    protected function _getQueryTermStats($textQuery)
    {
        $baseField = $this->_getSpellingBaseField();
        $analyzers = $this->_getSpellingAnalayzers();

        $termVectResponse = $this->_getTermVectors($textQuery, $baseField, $analyzers);

        $queryTermStats = array('stop' => 0, 'exact' => 0, 'standard' => 0, 'missing' => 0, 'total' => count($termVectResponse));

        foreach ($termVectResponse as $term) {
            if ($term['frequency'] == 0) {
                $queryTermStats['missing']++;
            } else if ($term['frequency'] > $this->_getCutOffFrequency()) {
                $queryTermStats['stop']++;
            } else if (in_array('whitespace', $term['analyzers'])) {
                $queryTermStats['exact']++;
            } else {
                $queryTermStats['standard']++;
            }
        }

        return $queryTermStats;
    }

    /**
     * Read terms stats usign term vectors API
     *
     * @param string $textQuery Text query to be analyzed
     * @param string $field     Field to be analyzed
     * @param array  $analyzers Used analyzers
     *
     * @return array
     */
    protected function _getTermVectors($textQuery, $field, $analyzers)
    {
        // Build term vector query
        $currentIndex = $this->getAdapter()->getCurrentIndex()->getCurrentName();
        $termVectorQuery = array('index' => $currentIndex, 'type' => $this->getType(), 'id' => '', 'term_statistics'  => true);
        $termVectorQuery['body']['doc'] = array($field => $textQuery);

        // Run the term vector query and get index status
        Varien_Profiler::start('ES:EXECUTE:SPELLING_QUERY');
        $indexStatResponse = $this->getAdapter()->getCurrentIndex()->getStatus();
        $termVectorResponse  = $this->getClient()->termvector($termVectorQuery);
        Varien_Profiler::stop('ES:EXECUTE:SPELLING_QUERY');

        // Get total number of doc in the index : used to recompute doc_freq as a ratio
        // Warning : cutoff_frequency does not use the number of doc by type
        $indexTotalDocs = (int) $indexStatResponse['total']['docs']['count'];

        // Parse the response

        $response = array();

        foreach ($termVectorResponse['term_vectors'] as $fieldName => $fieldData) {

            // Get the fieldname and the analyzer from the real fieldname (formatted as fieldname.analyzer)
            list ($fieldName, $analyzer) = (strstr($fieldName, '.') ? explode('.', $fieldName) : array($fieldName, ''));

            if ($analyzer == null) {
                $analyzer = "none";
            }

            if (in_array($analyzer, $analyzers)) {
                // Keep only required analyzers and parse them
                foreach ($fieldData['terms'] as $term => $termStats) {
                    $termMinFrequency = false;
                    foreach ($termStats['tokens'] as $token) {
                        // Read the current token data
                        $positionKey  = sprintf("%s_%s", $token['start_offset'], $token['end_offset']);
                        $frequency    = isset($termStats['doc_freq']) ? $termStats['doc_freq'] / $indexTotalDocs : 0;
                        $addAnalyzer  = (bool) ($frequency > 0);

                        if ($termMinFrequency !== false) {
                            $frequency = min($termMinFrequency, $frequency);
                        }
                        $termMinFrequency = $frequency;

                        // Keep data set by a previous analyzer if the frequency is higher or the text is longer
                        if (isset($response[$positionKey])) {
                            $currentValue = $response[$positionKey];
                            $frequency    = max($currentValue['frequency'], $frequency);
                            $term         = $term;
                        }

                        // Put everything into the response
                        $response[$positionKey]['term'] = $term;
                        $response[$positionKey]['frequency'] = $frequency;
                        if ($addAnalyzer == true) {
                            $response[$positionKey]['analyzers'][] = $analyzer;
                        }
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Ensure the spelling type as result. Else reduce the constraint by applying SPELLING_TYPE_MOST_EXACT spelling type.
     *
     * @param string $textQuery    Text query to be analyzed.
     * @param int    $spellingType Spelling type before fix.
     *
     * @return string
     */
    protected function _fixSpellingType($textQuery, $spellingType)
    {
        $spellingTypesUsed = array(self::SPELLING_TYPE_PURE_STOPWORDS, self::SPELLING_TYPE_EXACT, self::SPELLING_TYPE_MOST_EXACT);
        if (in_array($spellingType, $spellingTypesUsed)) {
            $defaultSearchField = current($this->_getWeightedSearchFields());
            $cutoffFrequency    = $this->_getCutOffFrequency();
            $minimumShouldMatch = $this->_getMinimumShouldMatch();
            $index              = $this->getAdapter()->getCurrentIndex()->getCurrentName();
            $type               = $this->getType();

            $searchParams = array('index' => $index, 'type' => $type, 'search_type' => 'count');

            $queryType = 'match';

            if ($spellingType != self::SPELLING_TYPE_PURE_STOPWORDS) {
                $queryType = 'common';
                $searchParams['body']['query'][$queryType][$defaultSearchField]['cutoff_frequency'] = $cutoffFrequency;
            }

            $searchParams['body']['query'][$queryType][$defaultSearchField]['query'] = $textQuery;
            $searchParams['body']['query'][$queryType][$defaultSearchField]['minimum_should_match'] = $minimumShouldMatch;

            $searchResponse = $this->getClient()->search($searchParams);

            if (isset($searchResponse['hits']) && $searchResponse['hits']['total'] == 0) {
                $spellingType = self::SPELLING_TYPE_MOST_FUZZY;
            }
        }

        return $spellingType;
    }
}
