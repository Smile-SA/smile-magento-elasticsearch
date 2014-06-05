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
     * @var array
     */
    protected $_options = array();

    /**
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query
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
     * @param Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query $query Query the facet belong to.
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
    abstract public function getFacetQuery();

    /**
     * Parse the response to extract facet items.
     *
     * @param array $response Query response data.
     *
     * @return array
     */
    abstract public function getItems($response);
}