<?php
/**
 * Search terms products position data provider, this will retrieve custom products positions for each search terms
 * to have this data being added to the current ES search index
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Terms_Position
    extends Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Abstract
{
    /**
     * Retrieve custom position for search term for entities
     *
     * @param int   $storeId   The store id
     * @param array $entityIds The entity ids
     *
     * @return array
     */
    public function getEntitiesData($storeId, $entityIds)
    {
        $result = array();

        $resourceModel    = Mage::getResourceModel("smile_elasticsearch/search_term_product_position");
        $productsPosition = $resourceModel->getByProductIds($entityIds, $storeId);

        // Init the field as empty to manage deletion of a previous custom position for products
        foreach ($entityIds as $entityId) {
            $result[(int) $entityId]["search_terms_position"] = array();
        }

        // Populate matched products
        foreach ($productsPosition as $position) {
            $result[(int) $position["product_id"]]["search_terms_position"][] = array(
                "query_id"              => (int) $position["query_id"],
                "term_product_position" => (int) $position["position"],
            );
        }

        return $result;
    }

    /**
     * Return custom mapping for data added by this provider
     *
     * @return array
     */
    public function getMappingProperties()
    {
        $mapping = array();

        $mapping['properties']['search_terms_position'] = array(
            'type' => 'nested',
            'properties' => array(
                'query_id'              => array('type' => 'long', 'fielddata' => array('format' => 'doc_values')),
                'term_product_position' => array('type' => 'long', 'fielddata' => array('format' => 'doc_values'))
            )
        );

        return $mapping;
    }
}