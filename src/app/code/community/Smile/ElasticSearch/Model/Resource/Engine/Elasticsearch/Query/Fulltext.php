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
     * @var string
     */
    const MIN_SHOULD_MATCH_CONFIG_XMLPATH = 'elasticsearch_advanced_search_settings/fulltext_relevancy/search_minimum_should_match';

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
            if ($spellingType != self::SPELLING_TYPE_EXACT) {
                $this->_isSpellChecked = true;
            }
            $this->_fulltextQuery = $this->_buildFulltextQuery($this->_fulltextQuery, $spellingType);
            $query = $this->_fulltextQuery;
        }

        if (is_array($this->_fulltextQuery)) {
            $query = $this->_fulltextQuery;
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
        $query = array('bool' => array());

        if ($spellingType != self::SPELLING_TYPE_FUZZY) {
            $query['bool']['must'][] = $this->_getExactQueryMatch($textQuery, $spellingType);
        }

        if ($spellingType != self::SPELLING_TYPE_EXACT) {
            $fuzzyQuery = $this->_getFuzzyQueryMatch($textQuery, $spellingType);
            if (in_array($spellingType, array(self::SPELLING_TYPE_FUZZY, self::SPELLING_TYPE_MOST_FUZZY))) {
                $query['bool']['must'][] = $fuzzyQuery;
            } else {
                $query['bool']['should'][] = $fuzzyQuery;
            }
        } else {
            $query['bool']['should'][] = $this->_getPhraseQueryMatch($textQuery, $spellingType);
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
        $exactSearchFields = array();
        $exactSearchFields[] = 'spelling_' . $languageCode;

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
        $fuzzySearchFields = array();
        $fuzzySearchFields[] = 'spelling_' . $languageCode . '.shingle';
        foreach ($this->getSearchFields() as $fieldName => $fieldParam) {
            if ($fieldParam['fuzziness'] !== false && $fieldParam['weight'] != 1) {
                $fuzzySearchFields[] = $fieldName . '^' . $fieldParam['weight'];
            }
        }

        $fuzzyQuery['bool']['must'][] = array(
            'multi_match' => array(
                'fields'        => $fuzzySearchFields,
                'query'         => $textQuery,
                'fuzziness'     => 2,
                'prefix_length' => 0,
                'minimum_should_match' => '100%',
                'type'          => 'best_fields'
            )
        );

        $phoneticSearchFields = array();
        $phoneticAnalyzer = 'phonetic_'. $languageCode;
        $phoneticSearchFields[] = 'spelling_' . $languageCode . '.' . $phoneticAnalyzer;
        foreach ($this->getSearchFields() as $fieldName => $fieldParam) {
            if ($fieldParam['fuzziness'] !== false && $fieldParam['weight'] != 1) {
                $phoneticSearchFields[] = $fieldName . '.' . $phoneticAnalyzer . '^' . $fieldParam['weight'];
            }
        }

        if (!empty($phoneticAnalyzer)) {
            $fuzzyQuery['bool']['must'][] = array(
                'multi_match' => array(
                    'fields'        => $phoneticSearchFields,
                    'query'         => $textQuery,
                    'analyzer'      => $phoneticAnalyzer,
                    'minimum_should_match' => 1,
                    'type'          => 'cross_fields'
                )
            );
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

        $query['body']['query']['bool']['should'] = array(
            array(
                'match' => array(
                    'spelling_' . $languageCode => array(
                        'analyzer'             => 'analyzer_' . $languageCode,
                        'query'                => $textQuery,
                        'minimum_should_match' => $this->_getMinimumShouldMatch(),
                        'fuzziness'            => self::MAX_FUZZINESS,
                        'prefix_length'        => 0
                    )
                )
            ),
            array(
                'match' => array(
                    'spelling_' . $languageCode => array(
                        'analyzer'             => 'analyzer_' . $languageCode,
                        'query'                => $textQuery,
                        'minimum_should_match' => $this->_getMinimumShouldMatch(),
                    )
                )
            ),
        );

        $query['body']['aggs']['exact_match']['filter']['query']['match']['spelling_' . $languageCode . '.shingle'] = array(
            'analyzer'             => 'shingle',
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
