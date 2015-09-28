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
     * @var string
     */
    const AUTOCOMPLETE_FUZZINESS_CONFIG_XMLPATH = 'elasticsearch_advanced_search_settings/fulltext_relevancy/search_autocomplete_fuzziness';

    /**
     * Returns autocompelete fuzziness from config.
     *
     * @return float
     */
    protected function _getAutocompleteFuzziness()
    {
        return (float) Mage::getStoreConfig(self::AUTOCOMPLETE_FUZZINESS_CONFIG_XMLPATH);
    }

    /**
     * List of the field used by the query.
     * For autocomplete we use only fields marked as used_in_autocomplete
     *
     * Warning : name field is weighted to 100. Should be configurable.
     *
     * @return array
     */
    public function getSearchFields()
    {
        $allSearchFields = parent::getSearchFields();
        $searchFields = array();
        foreach ($allSearchFields as $fieldName => $currentFieldConfig) {
            if ($currentFieldConfig['used_in_autocomplete']) {
                if ($fieldName == 'name_fr') {
                    $currentFieldConfig['weight'] = 100;
                }
                $searchFields[$fieldName] = $currentFieldConfig;
            }
        }
        return $searchFields;
    }


    /**
     * Build the fulltext query condition for the query.
     *
     * @return array
     */
    protected function _prepareFulltextCondition()
    {
        $query = array();

        if ($this->_fulltextQuery && is_string($this->_fulltextQuery)) {
            $queryArray = explode(' ', $this->_fulltextQuery);
            if (count($queryArray) > 1) {
                $textQuery = implode(' ', array_slice($queryArray, 0, -1));
                $spellingType = $this->_analyzeSpelling($textQuery);
                if ($spellingType != self::SPELLING_TYPE_EXACT) {
                    $this->_isSpellChecked = true;
                }
                $query = $this->_buildFulltextQuery($textQuery, $spellingType);
            }
            $query['bool']['must'][] = $this->_getAutoCompleteQueryPart(end($queryArray));
        }

        return $query;
    }

    /**
     * Build the autocomplete part of the query.
     *
     * @param string $textQuery Last part of the query (last word is considered as not complete)
     *
     * @return array
     */
    protected function _getAutoCompleteQueryPart($textQuery)
    {
        $languageCode = $this->getLanguageCode();

        $fields = array();
        foreach ($this->getSearchFields() as $fieldName => $fieldParams) {
            $fields[] = sprintf('%s.edge_ngram_front^%s', $fieldName, $fieldParams['weight']);
        }

        $autocompleteQuery = array(
            'multi_match' => array(
                'analyzer'  => 'whitespace',
                'query'     => $textQuery,
                'fields'    => $fields,
                'type'      => 'best_fields',
            )
        );

        $fuzzinessConfig = $this->_getFuzzinessConfig($languageCode);

        if ($fuzzinessConfig) {
            $autocompleteFuzzyQuery = $autocompleteQuery;
            $autocompleteFuzzyQuery['multi_match'] = array_merge($fuzzinessConfig, $autocompleteQuery['multi_match']);
            $autocompleteFuzzyQuery['multi_match']['boost'] = 0.001;
            $autocompleteQuery = array('bool' => array('should' => array($autocompleteQuery, $autocompleteFuzzyQuery)));
        }

        return $autocompleteQuery;
    }

}
