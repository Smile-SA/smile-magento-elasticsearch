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
    protected function _getSpellingBaseField() {
        return 'autocomplete_' . $this->getLanguageCode();
    }

    protected function _getSpellingAnalayzers() {
        return array('whitespace', 'none', 'edge_ngram_front');
    }

    protected function _getDefaultSearchField()
    {
        return 'autocomplete_' . $this->getLanguageCode();
    }

    public function _getWeightedSearchFields()
    {
        return $this->getSearchFields(Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_AUTOCOMPLETE);
    }

    protected function _getDefaultSubField()
    {
        return 'edge_ngram_front';
    }

    protected function _getDefaultAnalyzer()
    {
        return 'whitespace';
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
            'analyzer' => 'whitespace',
            'minimum_should_match' => "100%",
            'cutoff_frequency' => $this->_getCutOffFrequencyConfig()
        );

        $query = array('multi_match' => $weightedMultiMatchQuery);

        return $query;
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

            if ($spellingType == self::SPELLING_TYPE_FUZZY) {
                $this->_isSpellChecked = true;
            }

            if (!isset(self::$_assembledQueries[$this->_fulltextQuery])) {
                self::$_assembledQueries[$this->_fulltextQuery] = $this->_buildFulltextQuery($this->_fulltextQuery, $spellingType);
            }

            $query = self::$_assembledQueries[$this->_fulltextQuery];
        }

        return $query;
    }

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
     * Build the fulltext query.
     *
     * @param string $textQuery    Text submitted by the user.
     * @param int    $spellingType Type of spelling applied.
     *
     * @return array
     */
    protected function _buildFulltextQuery($textQuery, $spellingType)
    {
        $query = parent::_buildFulltextQuery($textQuery, $spellingType);
        //echo json_encode($query); die;
        return $query;
        /*$query = array();
        $searchFields = array_merge(
            $this->getSearchFields(
                Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_AUTOCOMPLETE
            ),
            $this->getSearchFields(
                Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_NORMAL, 'whitespace'
            )
        );

        $phraseBoostValue = $this->_getPhraseMatchBoost();
        $baseMatchQuery = array(
            'fields'               => $searchFields,
            'analyzer'             => 'whitespace',
            'minimum_should_match' => $this->_getMinimumShouldMatch(),
            'query'                => $textQuery
        );

        if ($spellingType != self::SPELLING_TYPE_FUZZY) {
            $minimumShouldMatch = $this->_getMinimumShouldMatch();
            if ($spellingType == self::SPELLING_TYPE_MOST_FUZZY) {
                $minimumShouldMatch = 1;
            }
            $query['bool']['must'][] = array(
                'multi_match' => array_merge(
                    $baseMatchQuery,
                    array('type' => 'most_fields', 'minimum_should_match' => $minimumShouldMatch)
                )
            );
            if ($phraseBoostValue !== false) {
                $query['bool']['should'][] = array(
                    'multi_match' => array_merge(
                        $baseMatchQuery,
                        array('type' => 'phrase', 'boost' => $phraseBoostValue)
                    )
                );
            }
        }

        $fuzzinessConfig = $this->_getFuzzinessConfig($this->getLanguageCode());
        if ($spellingType != self::SPELLING_TYPE_EXACT && $fuzzinessConfig != false) {
            $query['bool']['must'][] = array(
                'multi_match' => array_merge(
                    $fuzzinessConfig,
                    $baseMatchQuery,
                    array('type' => 'most_fields', 'minimum_should_match' => '100%')
                )
            );
        }
        return $query;*/
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
        $fuzzinessConfig = Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_enable_fuzziness');
        if ($fuzzinessConfig) {
            $fuzzySearchFields = $searchFields = $this->getSearchFields(
                Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::SEARCH_TYPE_AUTOCOMPLETE
            );

            $fuzzinessConfig = array(
                'fields'         => $fuzzySearchFields,
                'fuzziness'      => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_fuzziness_value'),
                'prefix_length'  => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_fuzziness_prefix_length'),
                'max_expansions' => Mage::getStoreConfig(self::RELEVANCY_SETTINGS_BASE_PATH . 'autocomplete_fuzziness_max_expansions'),
            );
        }
        return $fuzzinessConfig;
    }

}
