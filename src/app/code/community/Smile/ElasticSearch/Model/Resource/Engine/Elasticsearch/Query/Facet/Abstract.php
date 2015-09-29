<?php
/**
 * ElaticSearch abstract facet model.
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
abstract class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Facet_Abstract
{
    /**
     * Loaded response;
     *
     * @var array
     */
    protected $_response = null;


    /**
     * Default options.
     *
     * @var array
     */
    protected $_options = array();

    /**
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    protected $_query = null;

    /**
     * Init the facet with it's options
     *
     * @param array $options Facet options.
     */
    public function __construct($options = null)
    {
        if (!is_null($options)) {
            $this->_options = array_merge($this->_options, $options);
        }
    }

    /**
     * Associate a query to the facet.
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract $query Query the facet belong to.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Facet_Abstract
     */
    public function setQuery($query)
    {
        $this->_query = $query;
        return $this;
    }

    /**
     * Set the facet as group of facet
     *
     * @return bool
     */
    public function isGroup()
    {
        return false;
    }

    /**
     * Transform the facet into an ES syntax compliant array.
     *
     * @return array
     */
    public function getFacetQuery()
    {
        $filters = false;

        if (isset($this->_options['facet_filter'])) {
            $filters = $this->_options['facet_filter'];
            unset($this->_options['facet_filter']);
        }

        $facets = $this->_getFacetQuery();

        if ($filters !== false) {
            if ($this->isGroup()) {
                foreach ($facets as &$facet) {
                    $facet['facet_filter']['bool']['must'][] = $filters;
                }
            } else {
                $facets['facet_filter']['bool']['must'][] = $filters;
            }
            $this->_options['facet_filter'] = $filters;
        }

        return $facets;
    }

    /**
     * Set the response for this facet after the search phase.
     *
     * @param array $response Search query facets response.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Facet_Abstract
     */
    public function setResponse($response)
    {
        $this->_response = $response;
        return $this;
    }

    /**
     * Transform the facet into an ES syntax compliant array.
     * Real implemntation needed.
     *
     * @return array
     */
    abstract protected function _getFacetQuery();

    /**
     * Parse the response to extract facet items.
     *
     * @param array $response Query response data.
     *
     * @return array
     */
    abstract public function getItems($response = null);

    /**
     * Return facet raw response
     *
     * @return array
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Indicates if the facet has more result than the loaded items list.
     *
     * @return boolean
     */
    public function hasOthers()
    {
        return false;
    }
}