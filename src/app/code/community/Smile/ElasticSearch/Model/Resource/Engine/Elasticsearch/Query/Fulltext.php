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
     * @var array Already analyzed queries cache.
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
        } else {
            $query['bool']['should'][] = $this->_getPhraseQueryMatch($textQuery, $spellingType);
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
        $exactSearchFields = array('spelling_' . $languageCode);

        foreach ($this->getSearchFields() as $fieldName => $fieldParam) {
            if ($fieldParam['weight'] != 1) {
                $exactSearchFields[] = $fieldName . '^' . $fieldParam['weight'];
            }
        }

        $exactMatchQuery = array('multi_match' => array('query' => $textQuery, 'type' => 'cross_fields', 'tie_breaker' => 0.5));
        $exactMatchQuery['multi_match']['fields'] = $exactSearchFields;
        $exactMatchQuery['multi_match']['analyzer']  = 'analyzer_' .$languageCode;
        if ($spellingType != self::SPELLING_TYPE_MOST_FUZZY) {
            $exactMatchQuery['multi_match']['minimum_should_match'] = $this->_getMinimumShouldMatch();
        }

        return $exactMatchQuery;
    }

    /**
     * Build the query part for phrase matching.
     *
     * @param string $textQuery    Text submitted by the user.
     * @param int    $spellingType Type of spelling applied.
     *
     * @return string
     */
    protected function _getPhraseQueryMatch($textQuery, $spellingType)
    {
        $languageCode = $this->getLanguageCode();
        $phraseSearchFields = array();
        $phraseSearchFields[] = 'spelling_' . $languageCode . '.shingle';
        foreach ($this->getSearchFields() as $fieldName => $fieldParam) {
            if ($fieldParam['fuzziness'] !== false && $fieldParam['weight'] != 1) {
                $phraseSearchFields[] = $fieldName . '.shingle^' . $fieldParam['weight'];
            }
        }

        $phraseMatchQuery = array(
            'multi_match' => array(
                'fields'        => $phraseSearchFields,
                'query'         => $textQuery,
                'analyzer'      => 'shingle',
                'type'          => 'best_fields'
            )
        );

        return $phraseMatchQuery;
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
        $fuzzinessConfig = Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'enable_fuzziness');
        if ($fuzzinessConfig) {
            $fuzzySearchFields = array('spelling_' . $languageCode . '.whitespace');
            foreach ($this->getSearchFields() as $fieldName => $fieldParam) {
                if ($fieldParam['fuzziness'] !== false && $fieldParam['weight'] != 1) {
                    $fuzzySearchFields[] = $fieldName . '.whitespace' . '^' . $fieldParam['weight'];
                }
            }

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
            $phoneticSearchFields = array('spelling_' . $languageCode . '.' . $phoneticAnalyzer);
            foreach ($this->getSearchFields() as $fieldName => $fieldParam) {
                if ($fieldParam['fuzziness'] !== false && $fieldParam['weight'] != 1) {
                    $phoneticSearchFields[] = $fieldName . '.' . $phoneticAnalyzer . '^' . $fieldParam['weight'];
                }
            }

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
            $spellingQuery = self::_buildSpellingQuery($textQuery);
            Varien_Profiler::start('ES:EXECUTE:SPELLING_QUERY');
            $response = $this->getClient()->search($spellingQuery);
            Varien_Profiler::stop('ES:EXECUTE:SPELLING_QUERY');

            if ($response['aggregations']['exact_match']['doc_count'] > 0) {
                $result = self::SPELLING_TYPE_EXACT;
            } else if ($response['aggregations']['most_exact_match']['doc_count'] > 0) {
                $result = self::SPELLING_TYPE_MOST_EXACT;
            } else if ($response['aggregations']['most_fuzzy_match']['doc_count'] > 0) {
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
        unset($fuzzinessConfig['fields']);
        $query['body']['query']['bool']['should'] = array(
            array(
                'match' => array(
                    'spelling_' . $languageCode . '.whitespace' => array_merge(
                        $fuzzinessConfig,
                        array('query' => $textQuery, 'minimum_should_match' => '100%')
                    )
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
