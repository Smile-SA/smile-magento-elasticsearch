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

    protected static $_spellcheck = array();

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
     * @return array
     */
    protected function _getCutOffFrequencyConfig()
    {
        return 0.07;
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

    protected function _getDefaultSearchField()
    {
        return 'search_' . $this->getLanguageCode();
    }

    public function _getWeightedSearchFields()
    {
        return $this->getSearchFields(Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL);
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
                $cutoffFrequencyConfig = $this->_getCutOffFrequencyConfig();
                $weightedMultiMatchQuery['cutoff_frequency'] = $cutoffFrequencyConfig;
                $defaultSearchField = $this->_getDefaultSearchField();
                $filterQuery = array('query' => $textQuery, 'cutoff_frequency' => $cutoffFrequencyConfig);
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
                $qs = array('query' => $textQuery, 'cutoff_frequency' => $this->_getCutOffFrequencyConfig());
                $qsFilter = array('query' => array('common' => array($defaultSearchField . '.whitespace' => $qs)));
                $optimizationFunctions[] = array('filter' => $qsFilter, 'boost_factor' => $phraseBoostValue);
            }

            if (isset(self::$_spellcheck[$textQuery])) {
                foreach (self::$_spellcheck[$textQuery] as $currentTerm) {
                    $qs = array('query' => $currentTerm, 'default_field' => $defaultSearchField);
                    $qsFilter = array('query' => array('query_string' => $qs));
                    $optimizationFunctions[] = array('filter' => $qsFilter, 'boost_factor' => $phraseBoostValue);
                }
            }

            if (!empty($optimizationFunctions)) {
                $query = array('function_score' => array('query' => $query, 'functions' => $optimizationFunctions));
            }
        }

        return $query;
    }

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
     * @param string $languageCode Current language code.
     *
     * @return array|boolean
     */
    protected function _getFuzzinessConfig()
    {
        $fuzzinessConfig = (bool) Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'enable_fuzziness');
        if ($fuzzinessConfig) {
            $fuzzySearchFields = $this->_getFuzzySearchFields();
            $fuzzinessConfig = array(
                'analyzer'         => 'whitespace',
                'fields'           => $fuzzySearchFields,
                'fuzziness'        => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_value'),
                'prefix_length'    => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_prefix_length'),
                'max_expansions'   => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_max_expansions'),
                'cutoff_frequency' => $this->_getCutOffFrequencyConfig(),
            );
        }

        return $fuzzinessConfig;
    }

    protected function _getPhoneticSearchFields()
    {
        return $this->getSearchFields(
            Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL, 'phonetic'
        );
    }

    /**
     * Retrieve phonetic configuration for fulltext queries. False if phonetic search is disabled.
     *
     * @param string $languageCode Current language code.
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
                    'cutoff_frequency' => $this->_getCutOffFrequencyConfig(),
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

            $result = self::SPELLING_TYPE_FUZZY;
            if ($queryTermStats['total'] == $queryTermStats['stop']) {
                $result = self::SPELLING_TYPE_PURE_STOPWORDS;
            } else if ($queryTermStats['total'] == $queryTermStats['stop'] + $queryTermStats['exact']) {
                $result = self::SPELLING_TYPE_EXACT;
            } else if ($queryTermStats['missing'] == 0) {
                $result = self::SPELLING_TYPE_MOST_EXACT;
            } else if ($queryTermStats['total'] - $queryTermStats['missing'] > 0) {
                $result = self::SPELLING_TYPE_MOST_FUZZY;
            }
            self::$_analyzedQueries[$textQuery] = $result;

        }

        return self::$_analyzedQueries[$textQuery];
    }

    protected function _getSpellingBaseField() {
        return 'spelling_' . $this->getLanguageCode();
    }

    protected function _getSpellingAnalayzers() {
        return array('whitespace', 'none');
    }

    /**
     *
     *
     * @param string $textQuery Query issued by the customer.
     *
     * @return array
     */
    protected function _getQueryTermStats($textQuery)
    {
        $baseField = $this->_getSpellingBaseField();
        $analyzers = $this->_getSpellingAnalayzers();

        $currentIndex = $this->getAdapter()->getCurrentIndex()->getCurrentName();

        $termVectorQuery = array('index' => $currentIndex, 'type' => $this->getType(), 'id' => '', 'term_statistics'  => true);
        $termVectorQuery['body']['doc'] = array($baseField => $textQuery);
        Varien_Profiler::start('ES:EXECUTE:SPELLING_QUERY');
        $indexStatResponse = $this->getAdapter()->getCurrentIndex()->getStatus();
        $termVectResponse  = $this->getClient()->termvector($termVectorQuery);
        Varien_Profiler::stop('ES:EXECUTE:SPELLING_QUERY');
        $terms = array();

        $indexTotalDocs = (int) $indexStatResponse['total']['docs']['count'];

        foreach ($analyzers as $currentAnalyzer) {
            $currentField = $currentAnalyzer == 'none' ? $baseField : sprintf('%s.%s', $baseField, $currentAnalyzer);
            if (isset($termVectResponse['term_vectors'][$currentField])) {
                $currentTermVector = $termVectResponse['term_vectors'][$currentField];
                foreach ($currentTermVector['terms'] as $currentTerm => $termVector) {
                    foreach ($termVector['tokens'] as $token) {
                        $positionKey = sprintf("%s_%s", $token['start_offset'], $token['end_offset']);
                        $frequency = 0;
                        if (isset($termVector['doc_freq'])) {
                            $frequency = $termVector['doc_freq'] / $indexTotalDocs;
                            $terms[$positionKey]['analyzers'][] = $currentTerm;
                            $terms[$positionKey]['analyzers'][] = $currentAnalyzer;
                        }
                        if (isset($term[$positionKey]) && isset($term[$positionKey]['frequency'])) {
                            $frequency = max($terms[$positionKey]['frequency'], $frequency);
                        }
                        $terms[$positionKey]['frequency'] = $frequency;
                    }
                }
            }
        }
        $queryTermStats = array('stop' => 0, 'exact' => 0, 'standard' => 0, 'missing' => 0, 'total' => count($terms));

        foreach ($terms as $term) {
            if ($term['frequency'] == 0) {
                $queryTermStats['missing']++;
            } else if ($term['frequency'] > $this->_getCutOffFrequencyConfig()) {
                $queryTermStats['stop']++;
            } else if (in_array('whitespace', $term['analyzers'])) {
                $queryTermStats['exact']++;
            } else {
                $queryTermStats['standard']++;
            }
        }

        return $queryTermStats;
    }
}
