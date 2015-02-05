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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Autocomplete
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Fulltext
{

    /**
     * Build the fulltext query condition for the query.
     *
     * @return array
     */
    protected function _prepareFulltextCondition()
    {
        $query = array('match_all' => array());

        if ($this->_fulltextQuery && is_string($this->_fulltextQuery)) {

            $query = array('bool' => array('disable_coord' => true));
            $queryText = $this->prepareFilterQueryText($this->_fulltextQuery);
            $searchFields = $this->getSearchFields();

            $spellingParts = $this->getSpellingParts($queryText, $searchFields);

            if (isset($spellingParts['matched']) && !empty($spellingParts['matched'])) {
                $queryText = implode(' ', $spellingParts['matched']);
                $query['bool']['must'][] = $this->getExactMatchesQuery($queryText, $searchFields);
            }


            if (isset($spellingParts['unmatched']) && !empty($spellingParts['unmatched'])) {
                foreach ($spellingParts['unmatched'] as $fuzzyQueryText) {
                    $query['bool']['must'][] = $this->getFuzzyMatchesQuery($fuzzyQueryText, $searchFields);
                }
            }

            if (isset($spellingParts['autocomplete']) && !empty($spellingParts['autocomplete'])) {

                $fuzzyAutocompleteQuery = array(
                    'multi_match' => array(
                        'analyzer' => 'analyzer_' . $this->getLanguageCode(),
                        'query'   => $spellingParts['autocomplete'],
                        'fields'  => $this->getAutocompleSearchFields(),
                        'type'    => 'most_fields'
                    )
                );

                if (isset($spellingParts['autocomplete_fuzzy'])) {
                    $fuzzyAutocompleteQuery['multi_match']['fuzziness'] = 0.75;
                }

                $query['bool']['must'][] = $fuzzyAutocompleteQuery;
            }

            $this->_fulltextQuery = $query;

        } else if (is_array($this->_fulltextQuery)) {
            $query = $this->_fulltextQuery;
        }

        return $query;
    }

    /**
     * Retrieve the spelling part of a query through self::_analyzeQuerySpelling
     *
     * @param string $queryText    Text to be searched.
     * @param array  $searchFields Search fields configuration.
     *
     * @return array
     */
    public function getSpellingParts($queryText, $searchFields)
    {
        $spellingParts = array();
        $hasFuzzyFields = false;

        foreach ($searchFields as $fieldName => $currentField) {
            if ($currentField['fuzziness'] !== false) {
                $hasFuzzyFields = true;
            }
        }

        if ($hasFuzzyFields === true) {
            $spellingParts = $this->_analyzeQuerySpelling($queryText);
            $spellingParts = empty($spellingParts) ? $spellingParts = array('matched' => array($queryText)) : $spellingParts;
        } else {
            $queryTerms = explode(' ', $queryText);
            $spellingParts = array(
                'matched'      => array_slice($queryTerms, 0, -1),
                'autocomplete' => array_slice($queryTerms, -1)
            );
        }

        return $spellingParts;
    }

    /**
     * Dispatches query terms in two classes :
     * - matched terms   : Terms present into the indexes. Fuzzy search will not be applied on these terms
     * - unmatched terms : Terms missing into the indexes. Fuzzy search will be applied on these terms
     *
     * The spellchecker will be used to classify terms.
     *
     * @param string $queryText The analyzed query text
     *
     * @return array
     */
    protected function _analyzeQuerySpelling($queryText)
    {
        $result = array();

        $queryTerms = explode(' ', $queryText);

        $query = array(
            'index' => $this->getAdapter()->getCurrentIndex()->getCurrentName(),
            'type'  => $this->getType(),
            'size'  => 0,
        );

        if (count($queryTerms) > 1) {
            $query['body']['suggest']['spelling'] = array(
                'text' => array_slice($queryTerms, 0, -1),
                'term' => array(
                    'field'           => '_all',
                    'min_word_length' => 2,
                    'analyzer'        => 'whitespace'
                )
            );
        }

        $query['body']['aggs']['autocomplete'] = array(
            'filter' => array('prefix' => ['_all' => strtolower(end($queryTerms))])
        );

        Varien_Profiler::start('ES:EXECUTE:SPELLING_QUERY');
        $response = $this->getClient()->search($query);
        Varien_Profiler::stop('ES:EXECUTE:SPELLING_QUERY');

        if (isset($response['suggest'])) {
            foreach ($response['suggest']['spelling'] as $token) {
                if (empty($token['options'])) {
                    $result['matched'][] = Mage::helper('core/string')->substr($queryText, $token['offset'], $token['length']);
                } else {
                    $this->_isSpellChecked = true;
                    $result['unmatched'][] = Mage::helper('core/string')->substr($queryText, $token['offset'], $token['length']);
                }
            }
        } else {
            $result['matched'] = array();
        }

        if (isset($response['aggregations']) && $response['aggregations']['autocomplete']['doc_count'] == 0) {
            $result['autocomplete_fuzzy'] = array_slice($queryTerms, -1, 1);
        }
        $result['autocomplete'] = array_slice($queryTerms, -1, 1);

        return $result;
    }

    /**
     * Return fields used into the query and their configuration
     *
     * @return array
     */
    public function getSearchFields()
    {
        $allSearchFields = parent::getSearchFields();
        $searchFields = array();
        foreach ($allSearchFields as $fieldName => $currrentFieldConfig) {
            if ($currrentFieldConfig['used_in_autocomplete']) {
                $searchFields[$fieldName] = $currrentFieldConfig;
            }
        }
        return $searchFields;
    }

    /**
     * Retturn field used in edge_ngram matching
     *
     * @return array
     */
    public function getAutocompleSearchFields()
    {

        $analyzers = array('edge_ngram_front', 'edge_ngram_back');
        $allFields = parent::getSearchFields();
        $searchFields = array();

        foreach ($allFields as $fieldName => $currentField) {
            $fieldName = current(explode('.', $fieldName));
            if ($currentField['used_in_autocomplete']) {
                foreach ($analyzers as $analyzer) {
                    $searchFields[] = sprintf('%s.%s^%d', $fieldName, $analyzer, $currentField['weight']);
                }
            }

        }

        return $searchFields;
    }

}