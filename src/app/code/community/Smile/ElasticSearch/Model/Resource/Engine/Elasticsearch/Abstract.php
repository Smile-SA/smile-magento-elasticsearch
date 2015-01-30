<?php
/**
 * ElaticSearch abstract model used to instanciate query and index.
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
class Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Abstract
{
    /**
     * @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    protected $_adapter;


    /**
     * Returns search helper.
     *
     * @return Smile_ElasticSearch_Helper_Elasticsearch
     */
    protected function _getHelper()
    {
        return Mage::helper('smile_elasticsearch/elasticsearch');
    }

    /**
     * Set the adapter.
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch $adapter Adapter.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Abstract
     */
    public function setAdapter($adapter)
    {
        $this->_adapter = $adapter;
        return $this;
    }

    /**
     * Get the adapter.
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

    /**
     * Set the client.
     *
     * @return \Elasticsearch\Client
     */
    public function getClient()
    {
        return $this->getAdapter()->getClient();
    }

    /**
     * Return engine config param.
     *
     * @param string $key Config path.
     *
     * @return mixed
     */
    public function getConfig($key)
    {
        return $this->getAdapter()->getConfig($key);
    }

}