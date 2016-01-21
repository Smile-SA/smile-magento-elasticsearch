<?php
/**
 * Model responsible to apply search term optimizations to ES queries
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Search_Term_Optimizer extends Varien_Object
{
    /**
     * Append sort order based on custom products positions for a given search term
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract $query       The ES query
     * @param Mage_CatalogSearch_Model_Query                                         $searchQuery The search query
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function applyCustomProductsPositions($query, $searchQuery)
    {
        if (isset($query['body']['sort'])) {

            if ($this->hasCustomPositions($searchQuery)) {

                $sortDefinition = array(
                    'order'           => 'asc',
                    'missing'         => Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract::SORT_ORDER_LAST - 1,
                    'ignore_unmapped' => true
                );

                $sortDefinition['nested_path']   = 'search_terms_position';
                $sortDefinition['nested_filter'] = array(
                    'term' => array('query_id' => (int) $searchQuery->getId())
                );

                $sort = array("term_product_position" => $sortDefinition);

                array_unshift($query['body']['sort'], $sort);
            }
        }

        return $query;
    }

    /**
     * Verify if a given search query has custom positions defined for products
     *
     * @param Mage_CatalogSearch_Model_Query|int $query The concerned search query
     *
     * @return bool
     */
    public function hasCustomPositions($query)
    {
        return Mage::getResourceModel("smile_elasticsearch/search_term_product_position")->hasCustomPositions($query);
    }
}