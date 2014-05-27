<?php

class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query
{
    protected $_q;
    protected $_params;
    protected $_type;
    protected $_adapter;
    protected $_indexName;
    protected $_facetFields;

    public function setFulltextQuery($q)
    {
        $this->_q = $q;
        return $this;
    }

    public function setQueryParams($params)
    {
        $this->_params = $params;
        return $this;
    }

    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    public function setAdapter($adapter)
    {
        $this->_adapter = $adapter;
        return $this;
    }

    public function setIndex($index) {
        $this->_indexName = $index;
        return $this;
    }

    public function getSearchParams()
    {
        $searchParams = array('index' => $this->_indexName, 'type'  => $this->_type);
        $this->_params['filters'] = isset($this->_params['filters']) && !empty($this->_params['filters']) ? $this->_params['filters'] : array();
        $this->_params['range_filters'] = isset($this->_params['range_filters']) && !empty($this->_params['range_filters']) ? $this->_params['range_filters'] : array();

        // Facet management
        $facets = $this->_buildFacets();
        if (!empty($facets)) {
            $searchParams['body']['facets'] = $facets;
        }

        // Filter management
        $filters = $this->_buildQueryFilters($this->_facetFields);
        $searchParams['body']['query']['filtered']['filter'] = $filters;

        $allFilterFields = array_merge(array_keys($this->_params['filters']), array_keys($this->_params['range_filters']));
        $facetFilters = array_diff($allFilterFields, $this->_facetFields);
        $searchParams['body']['query']['filter']['filter'] = $filters = $this->_buildQueryFilters($facetFilters);

        foreach ($searchParams['body']['facets'] as $field => $facet) {
            $excludeField = $facetFilters;
            $excludeField[] = $field;
            $searchParams['body']['facets'][$field]['facet_filter'] = $this->_buildQueryFilters($excludeField);
        }

        if (!empty($q)) {
            // Append fulltext query if relevant
            $textQuery = $this->_buildFullTextQuery();

            $searchParams['body']['query']['filtered']['query']  = $textQuery;

            $searchParams['body']['suggest'] = array(
                "text" => $q,
                "spellcheck" => array(
                    "term" => array(
                        "field"    => "name_fr.whitespace",
                        "size" => 3
                    )
                )
            );
        }

        // Set Pagination
        $searchParams['body']['from'] = $this->_params['offset'];
        $searchParams['body']['size'] = $this->_params['limit'];

        // Set sorting
        if (isset($this->_params['sort']) && !empty($this->_params['sort'])) {
            foreach ($this->_params['sort'] as $sort) {
                $searchParams['body']['sort'][] = $sort;
            }
        }

        return $searchParams;
    }


    /**
     * Build a fulltext query with optionnal fuzzy params read from config
     *
     * @param string $text The text searched
     *
     * @return array
     */
    protected function _buildFullTextQuery()
    {
        $result = array('query_string' => array('query' => $this->_q, 'fields' => $this->getSearchFields(false, $this->_q)));
        if ($this->isFuzzyQueryEnabled()) {
            $result = array('bool' => array('should' => array($result)));
            $fuzzyQuery = array(
                'fields'          => $this->getSearchFields(true, $this->_q),
                'like_text'       => $this->_q,
                'min_similarity'  => $this->getFuzzyMinSimilarity(),
                'prefix_length'   => $this->getFuzzyPrefixLength(),
                'max_query_terms' => $this->getFuzzyMaxQueryTerms(),
                'boost'           => $this->getFuzzyQueryBoost()
            );
            $result['bool']['should'][] = array('fuzzy_like_this' => $fuzzyQuery);
        }
        return $result;
    }

    /**
     * Build the facet part of the query
     *
     * @param array $params Query parameters
     *
     * @return array
     */
    protected function _buildFacets()
    {
        $result = array();

        if (isset($this->_params['facets']['queries']) && !empty($this->_params['facets']['queries'])) {
            foreach ($this->_params['facets']['queries'] as $facetQuery) {
                $facet = array('query' => array('query_string' => array('query' => $facetQuery)));
                $result[$facetQuery] = $facet;
            }
        }

        if (isset($this->_params['stats']['fields']) && !empty($this->_params['stats']['fields'])) {
            foreach ($this->_params['stats']['fields'] as $field) {
                $facet = array('statistical' => array('field' => $field));
                $result[$field] = $facet;
                $this->_facetFields[] = $field;
            }
        } else {
            if (isset($this->_params['facets']['fields']) && !empty($this->_params['facets']['fields'])) {
                $properties = $this->_adapter->getIndexProperties();
                foreach ($this->_params['facets']['fields'] as $field) {
                    if (array_key_exists($field, $properties)) {
                        $realField = $field;
                        if ($properties[$field]['type'] == 'multi_field') {
                            $realField .= '.untouched';
                        }
                        $facet = array('terms' => array('field' => $realField));
                        $facet['terms']['all_terms'] = true;
                        $facet['terms']['size'] = $this->getFacetsMaxSize();
                        $result[$field] = $facet;
                        $this->_facetFields[] = $field;
                    }
                }
            }

            if (isset($this->_params['facets']['ranges']) && !empty($this->_params['facets']['ranges'])) {
                foreach ($this->_params['facets']['ranges'] as $field => $ranges) {
                    $facet = array('range' => array('field' => $field, 'ranges' => $ranges));
                    $result[$field] = $facet;
                    $this->_facetFields[] = $field;
                }
            }

            if (isset($this->_params['facets']['histogram']) && !empty($this->_params['facets']['histogram'])) {
                foreach ($this->_params['facets']['histogram'] as $field => $interval) {
                    $facet = array('histogram' => array('field' => $field, 'interval' => $interval));
                    $result[$field] = $facet;
                    $this->_facetFields[] = $field;
                }
            }
        }

        return $result;
    }

    /**
     * Build the query filter part of the query
     *
     * @param array $params Query parameters
     *
     * @return array
     */
    protected function _buildQueryFilters($excludedFields = array())
    {

        $filters = array('bool' => array('must' => array()));

        $filterString = '*';
        $filterValues = array_diff_key($this->_params['filters'], array_fill_keys($excludedFields, true));

        if (!empty($filterValues)) {
            $filterString = implode(' AND ', $filterValues);
        }

        $filters['bool']['must'][] = array('query' => array('query_string' => array('query' => $filterString)));

        if (isset($this->_params['range_filters']) && !empty($this->_params['range_filters'])) {
            foreach ($this->_params['range_filters'] as $field => $rangeFilter) {
                if (!in_array($field, $excludedFields)) {
                    $filters['bool']['must'][] = array('range' => array($field => $rangeFilter));
                }
            }
        }

        return $filters;
    }

    /**
     * Returns facets max size parameter.
     *
     * @return int
     */
    public function getFacetsMaxSize()
    {
        return (int) $this->_adapter->getConfig('facets_max_size');
    }

    /**
     * Returns fuzzy max query terms parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return int
     */
    public function getFuzzyMaxQueryTerms()
    {
        return (int) $this->_adapter->getConfig('fuzzy_max_query_terms');
    }

    /**
     * Returns fuzzy min similarity parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return float
     */
    public function getFuzzyMinSimilarity()
    {
        // 0 to 1 (1 excluded)
        return min(0.99, max(0, $this->_adapter->getConfig('fuzzy_min_similarity')));
    }

    /**
     * Returns fuzzy prefix length.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return int
     */
    public function getFuzzyPrefixLength()
    {
        return (int) $this->_adapter->getConfig('fuzzy_prefix_length');
    }

    /**
     * Returns fuzzy query boost parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return float
     */
    public function getFuzzyQueryBoost()
    {
        return (float) $this->_adapter->getConfig('fuzzy_query_boost');
    }

    /**
     * Checks if fuzzy query is enabled.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return bool
     */
    public function isFuzzyQueryEnabled()
    {
        return (bool) $this->_adapter->getConfig('enable_fuzzy_query');
    }

}