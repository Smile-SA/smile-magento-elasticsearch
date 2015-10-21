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
                    'autocomplete' => array_merge($fuzzinessConfig, array('query' => $textQuery, 'minimum_should_match' => '100%'))
                )
            )
        );

        $query['body']['aggs']['exact_match']['filter']['query']['match']['autocomplete'] = array(
            'analyzer'             => 'whitespace',
            'query'                => $textQuery,
            'minimum_should_match' => '100%'
        );

        $query['body']['aggs']['most_exact_match']['filter']['query']['match']['autocomplete'] = array(
            'analyzer'             => 'whitespace',
            'query'                => $textQuery,
            'minimum_should_match' => $this->_getMinimumShouldMatch(),
        );

        $query['body']['aggs']['most_fuzzy_match']['filter']['query']['match']['autocomplete'] = array(
            'analyzer'             => 'whitespace',
            'query'                => $textQuery
        );
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
