<?php
/**
 * ElaticSearch query model for autocomplete
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Autocomplete
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Fulltext
{
    /**
     * Default field used in autocomplete.
     *
     * @return string
     */
    protected function _getDefaultSearchField()
    {
        return 'autocomplete_' . $this->getLanguageCode();
    }

    /**
     * Returns the list of fields used in autocomplete with their respective weights.
     *
     * @return array
     */
    protected function _getWeightedSearchFields()
    {
        return $this->getSearchFields(
            Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_AUTOCOMPLETE
        );
    }

    /**
     * Default field used in autocomplete spellechecking
     *
     * @return string
     */
    protected function _getSpellingBaseField()
    {
        return $this->_getDefaultSearchField();
    }

    /**
     * List of analyzers used by the spellchecker.
     *
     * @return array
     */
    protected function _getSpellingAnalayzers()
    {
        return array('whitespace', 'none', 'edge_ngram_front');
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
        $weightedMultiMatchQuery = array(
            'query' => $textQuery ,
            'type' => 'best_fields',
            'tie_breaker' => 1,
            'fields' => $this->_getWeightedSearchFields(),
            'analyzer' => 'analyzer_' . $this->getLanguageCode()
        );

        if ($spellingType != self::SPELLING_TYPE_MOST_FUZZY) {
            $weightedMultiMatchQuery['minimum_should_match'] = "100%";
        }

        $query = array('multi_match' => $weightedMultiMatchQuery);

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

            $qs = array('query' => $textQuery, 'default_field' => $defaultSearchField . '.whitespace');
            $qsFilter = array('query' => array('query_string' => $qs));
            $optimizationFunction = array('filter' => $qsFilter, 'boost_factor' => $phraseBoostValue);
            $query = array('function_score' => array('query' => $query, 'functions' => array($optimizationFunction)));

        }

        return $query;
    }

    /**
     * Retrieve fuzziness configuration for fulltext queries. False if fuzziness is disabled.
     *
     * @return array|boolean
     */
    protected function _getFuzzinessConfig()
    {
        $fuzzinessConfig = Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_enable_fuzziness');
        if ($fuzzinessConfig) {
            $fuzzySearchFields = $searchFields = $this->getSearchFields(
                Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_AUTOCOMPLETE
            );

            $fuzzinessConfig = array(
                'fields'           => $fuzzySearchFields,
                'fuzziness'        => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_fuzziness_value'),
                'prefix_length'    => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_fuzziness_prefix_length'),
                'max_expansions'   => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_fuzziness_max_expansions'),
                'analyzer'         => 'whitespace'
            );
        }
        return $fuzzinessConfig;
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
            $fuzzyQuery = array('multi_match' => array_merge($matchQuery, $fuzzinessConfig));
        }

        return $fuzzyQuery;
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
        if (in_array($spellingType, array(self::SPELLING_TYPE_PURE_STOPWORDS, self::SPELLING_TYPE_EXACT, self::SPELLING_TYPE_MOST_EXACT))) {
            $defaultSearchField = current($this->_getWeightedSearchFields());
            $minimumShouldMatch = "100%";
            $index              = $this->getAdapter()->getCurrentIndex()->getCurrentName();
            $type               = $this->getType();

            $searchParams = array('index' => $index, 'type' => $type, 'search_type' => 'count');

            $queryType = 'match';

            $searchParams['body']['query'][$queryType][$defaultSearchField]['query'] = $textQuery;
            $searchParams['body']['query'][$queryType][$defaultSearchField]['analyzer'] = 'analyzer_' . $this->getLanguageCode();
            $searchParams['body']['query'][$queryType][$defaultSearchField]['minimum_should_match'] = $minimumShouldMatch;

            $searchResponse = $this->getClient()->search($searchParams);

            if (isset($searchResponse['hits']) && $searchResponse['hits']['total'] == 0) {
                $spellingType = self::SPELLING_TYPE_MOST_FUZZY;
            }
        }

        return $spellingType;
    }
}
