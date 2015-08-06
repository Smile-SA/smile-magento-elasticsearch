<?php
/**
 * Search optimizer observer
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
 * @package   Smile_SearchOptimizer
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Model_Observer
{
    /**
     * @var string
     */
    const TRACKING_INDEXER_NODES = 'global/smile_searchoptimizer/elasticsearch/tracking_indexers';

    /**
     * @var string
     */
    const DUPLICATED_INDEX_TYPES = 'global/smile_searchoptimizer/elasticsearch/duplicated_types';

    /**
     * Append stats mapping to index.
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Modyf_Search_Model_Observer
     */
    public function addStatsMappingToIndex(Varien_Event_Observer $observer)
    {
        $indexProperties = $observer->getIndexProperties();
        $indexPropertiesData = $indexProperties->getData();
        $indexPropertiesData['body']['mappings']['stats'] = array(
            '_parent'    => array('type' => 'product'),
            'properties' => array(
                "product_id" => array("type" => "string", "index" => "not_analyzed"),
                "store_id"   => array("type" => "string", "index" => "not_analyzed"),
                "event_type" => array("type" => "string", "index" => "not_analyzed"),
                "count"      => array("type" => "integer"),
                "date"       => array("type" => "date", "date_formats" => array(Varien_Date::DATE_INTERNAL_FORMAT))
            )
        );

        $indexPropertiesData['body']['settings']['tracking'] = $this->_getIndexTrackingProperties();
        $indexProperties->setData($indexPropertiesData);

        return $this;
    }

    /**
     * Get tracking properties from the configuration.
     *
     * @return array
     */
    protected function _getIndexTrackingProperties()
    {
        $properties = array(
            'site_id'    => (string) Mage::helper('smile_searchandisingsuite')->getSiteId(),
            'processors' => Mage::app()->getConfig()->getNode(self::TRACKING_INDEXER_NODES)->asArray()
        );

        return $properties;
    }

    /**
     * Copy old stats data when processing full reindex.
     *
     * @param Varien_Event_Observer $observer Event to observe
     *
     * @return Smile_SearchOptimizer_Model_Observer Self reference.
     */
    public function copyOldData(Varien_Event_Observer $observer)
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        $index  = $engine->getCurrentIndex();
        $types = array_keys(Mage::app()->getConfig()->getNode(self::DUPLICATED_INDEX_TYPES)->asArray());

        foreach ($types as $type) {
            $index->copyDataFromIndex($engine->getConfig('alias'), $type);
        }

        return $this;
    }

    /**
     * Append session template to ES.
     *
     * @param Varien_Event_Observer $observer Event to observe.
     *
     * @return Smile_SearchOptimizer_Model_Observer Self reference.
     */
    public function createSessionMappingTemplate(Varien_Event_Observer $observer)
    {
        $engine = Mage::helper('catalogsearch')->getEngine();
        $client   = $engine->getClient();

        $template = array('name' => 'magento-session', 'body' => array());
        $template['body']['template'] = 'magento-sessio*';
        $template['body']['settings']['number_of_replicas'] = (int) $engine->getConfig('number_of_replicas');
        $template['body']['settings']['number_of_shards']   = (int) $engine->getConfig('number_of_shards');
        $template['body']['settings']['analysis'] = array('analyzer' => array('standard' => array('type' => 'standard')));
        $template['body']['mappings']['session'] = array(
            'date_detection' => false,
            'properties' => array(
                'session_date' => array('type' => 'date'),
                'pages'        => array('type' => 'nested')
            )
        );

        $client->indices()->putTemplate($template);

        return $this;
    }

    /**
     * Append optimize to queries.
     *
     * @param Varien_Event_Observer $observer Event to observe.
     *
     * @return Smile_SearchOptimizer_Model_Observer Self reference.
     */
    public function addOptimizers(Varien_Event_Observer $observer)
    {
        $data = $observer->getQueryData();
        $queryType = $data->getQueryType();

        $optimizers = Mage::getResourceModel('smile_searchoptimizer/optimizer_collection')
            ->addFieldToFilter('is_active', true)
            ->addStoreFilter(Mage::app()->getStore())
            ->addQueryTypeFilter($queryType);

        $query = $data->getQuery();

        foreach ($optimizers as $currentOptimizer) {
            $query = $currentOptimizer->applyOptimizer($query);
        }

        $data->setQuery($query);

        return $this;
    }

}