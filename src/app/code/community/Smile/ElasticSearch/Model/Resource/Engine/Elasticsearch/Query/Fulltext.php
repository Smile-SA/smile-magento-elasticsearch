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
      * Build the fulltext query condition for the query.
      *
      * @return array
      */
    protected function _prepareFulltextCondition()
    {
        $query = array('match_all' => array());

        if ($this->_fulltextQuery && is_string($this->_fulltextQuery)) {
            $spellingType = $this->_analyzeSpelling($this->_fulltextQuery);

            if (!isset(self::$_assembledQueries[$this->_fulltextQuery])) {
                self::$_assembledQueries[$this->_fulltextQuery] = $this->_buildFulltextQuery($this->_fulltextQuery, $spellingType);
            }

            $query = self::$_assembledQueries[$this->_fulltextQuery];

            if ($spellingType != self::SPELLING_TYPE_EXACT) {
                $this->_isSpellChecked = true;
            }
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

        if ($spellingType != self::SPELLING_TYPE_EXACT) {
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
            $query['bool']['must'][] = $this->_getExactQueryMatch($textQuery, $spellingType);
        }

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
        $languageCode = $this->getLanguageCode();
        $exactSearchFields = $this->getSearchFields(
            Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL
        );

        $exactMatchQuery = array('multi_match' => array('query' => $textQuery, 'type' => 'cross_fields', 'tie_breaker' => 0.5));
        $exactMatchQuery['multi_match']['fields'] = $exactSearchFields;
        $exactMatchQuery['multi_match']['analyzer']  = 'analyzer_' .$languageCode;
        if ($spellingType != self::SPELLING_TYPE_MOST_FUZZY) {
            $exactMatchQuery['multi_match']['minimum_should_match'] = $this->_getMinimumShouldMatch();
        }

        $phraseBoostValue = $this->_getPhraseMatchBoost();

        if ($phraseBoostValue !== false) {
            $refinedQueryFields = $this->getSearchFields(
                Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL,
                'whitespace'
            );
            $refinedQuery['multi_match'] = array(
                'fields'   => $refinedQueryFields,
                'analyzer' => 'whitespace',
                'boost'    => $phraseBoostValue,
                'query'    => $textQuery,
                'type' => 'phrase'
            );
            $query = array('bool' => array('should' => array($refinedQuery), 'must' => array($exactMatchQuery)));
        }

        return $query;
    }

    /**
     * Retrieve fuzziness configuration for fulltext queries. False if fuzziness is disabled.
     *
     * @param string $languageCode Current language code.
     *
     * @return array|boolean
     */
    protected function _getFuzzinessConfig($languageCode)
    {
        $fuzzinessConfig = (bool) Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'enable_fuzziness');
        if ($fuzzinessConfig) {
            $fuzzySearchFields = $this->getSearchFields(
                Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_FUZZY
            );
            $fuzzinessConfig = array(
                'fields'         => $fuzzySearchFields,
                'fuzziness'      => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_value'),
                'prefix_length'  => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_prefix_length'),
                'max_expansions' => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'fuzziness_max_expansions'),
            );
        }
        return $fuzzinessConfig;
    }

    /**
     * Retrieve phonetic configuration for fulltext queries. False if phonetic search is disabled.
     *
     * @param string $languageCode Current language code.
     *
     * @return array|boolean
     */
    protected function _getPhoneticConfig($languageCode)
    {
        $phoneticConfig = false;
        $configPrexfix = self::RELEVANCY_SETTINGS_BASE_PATH;
        $isSupported = $this->getAdapter()->getCurrentIndex()->isPhoneticSupported($languageCode);
        $isEnabled   = Mage::getStoreConfig($configPrexfix . 'enable_phonetic_search');

        if ($isSupported && $isEnabled) {
            $phoneticAnalyzer = 'phonetic_' . $languageCode;
            $phoneticSearchFields = $this->getSearchFields(
                Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_PHONETIC
            );
            $phoneticConfig = array('analyzer' => $phoneticAnalyzer, 'fields' => $phoneticSearchFields);
            if ((bool) Mage::getStoreConfig($configPrexfix . 'enable_phonetic_search_fuzziness')) {
                $fuzzinessConfig = array(
                    'fuzziness'      => Mage::getStoreConfig($configPrexfix . 'phonetic_search_fuzziness_value'),
                    'prefix_length'  => Mage::getStoreConfig($configPrexfix . 'phonetic_search_fuzziness_prefix_length'),
                    'max_expansions' => Mage::getStoreConfig($configPrexfix . 'phonetic_search_fuzziness_max_expansions'),
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
        $languageCode = $this->getLanguageCode();

        $matchQuery = array('query' => $textQuery, 'type' => 'best_fields', 'minimum_should_match' => "100%");

        $fuzzinessConfig = $this->_getFuzzinessConfig($languageCode);
        if ($fuzzinessConfig !== false) {
            $fuzzyQuery['bool']['should'][] = array('multi_match' => array_merge($matchQuery, $fuzzinessConfig));
        }

        $phoneticConfig = $this->_getPhoneticConfig($languageCode);
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
            $result = self::SPELLING_TYPE_FUZZY;
            $spellingQuery = $this->_buildSpellingQuery($textQuery);
            Varien_Profiler::start('ES:EXECUTE:SPELLING_QUERY');
            $response = $this->getClient()->search($spellingQuery);
            Varien_Profiler::stop('ES:EXECUTE:SPELLING_QUERY');
            $aggregations = $response['aggregations'];
            if (isset($aggregations['exact_match']) && $aggregations['exact_match']['doc_count'] > 0) {
                $result = self::SPELLING_TYPE_EXACT;
            } else if (isset($aggregations['most_exact_match']) && $aggregations['most_exact_match']['doc_count'] > 0) {
                $result = self::SPELLING_TYPE_MOST_EXACT;
            } else if (isset($aggregations['most_fuzzy_match']) && $aggregations['most_fuzzy_match']['doc_count'] > 0) {
                $result = self::SPELLING_TYPE_MOST_FUZZY;
            }

            self::$_analyzedQueries[$textQuery] = $result;
        }

        return self::$_analyzedQueries[$textQuery];
    }

    /**
     * Build the query which detect if some words have been mispelled by the user.
     *
     * @param string $textQuery Query issued by the customer.
     *
     * @return array
     */
    protected function _buildSpellingQuery($textQuery)
    {
        $languageCode = $this->getLanguageCode();
        $query = array(
            'index' => $this->getAdapter()->getCurrentIndex()->getCurrentName(),
            'type'  => $this->getType(),
            'size'  => 0,
            'search_type' => 'count'
        );

        $fuzzinessConfig = $this->_getFuzzinessConfig($languageCode);
        if ($fuzzinessConfig != false) {
            unset($fuzzinessConfig['fields']);
        } else {
            $fuzzinessConfig = array();
        }

        $query['body']['query']['bool']['should'] = array(
            array(
                'match' => array(
                    'spelling_' . $languageCode . '.whitespace' => array_merge(
                        $fuzzinessConfig,
                        array('query' => $textQuery, 'minimum_should_match' => '100%')
                    )
                )
            ),
            array(
                'multi_match' => array(
                    'analyzer' => 'analyzer_' . $languageCode,
                    'fields'   => $this->getSearchFields(
                        Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL
                    ),
                    'minimum_should_match' => $this->_getMinimumShouldMatch(),
                    'type'  => 'cross_fields',
                    'query'                => $textQuery,
                )
            )
        );

        $query['body']['aggs']['exact_match']['filter']['query']['match']['spelling_' . $languageCode . '.whitespace'] = array(
            'analyzer'             => 'whitespace',
            'query'                => $textQuery,
            'minimum_should_match' => '100%'
        );

        $query['body']['aggs']['most_exact_match']['filter']['query']['match']['spelling_' . $languageCode] = array(
            'analyzer'             => 'analyzer_' . $languageCode,
            'query'                => $textQuery,
            'minimum_should_match' => $this->_getMinimumShouldMatch(),
        );

        $query['body']['aggs']['most_fuzzy_match']['filter']['query']['match']['spelling_' . $languageCode] = array(
            'analyzer'             => 'analyzer_' . $languageCode,
            'query'                => $textQuery
        );

        return $query;
    }
}
