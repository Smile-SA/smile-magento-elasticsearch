<?php
/**
 * ElasticSearch helper
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
class Smile_ElasticSearch_Helper_Elasticsearch extends Smile_ElasticSearch_Helper_Data
{
    /**
     * Returns Elasticsearch engine config data.
     *
     * @param string $prefix Configuration prefix to be loaded (not used but present for compatibility)
     * @param mixed  $store  Store we want the configuration for
     *
     * @return array
     */
    public function getEngineConfigData($prefix = '', $store = null)
    {
        $config = parent::getEngineConfigData('elasticsearch_', $store);
        $servers = array();
        foreach (explode(',', $config['servers']) as $server) {
            $servers[] = $server;
        }
        $config['hosts'] = $servers;

        return $config;
    }

    /**
     * Should Elasticsearch also search on options?
     *
     * @return bool
     */
    public function shouldSearchOnOptions()
    {
        return Mage::getStoreConfigFlag('catalog/search/elasticsearch_enable_options_search');
    }

    /**
     * Produce an horodated name for a given string (eg. magento => magento-20150902-122312).
     *
     * @param string $name    Name to be horodated.
     * @param string $pattern Date format (default to {{YYYYMMdd}}-{{HHmmss}}).
     *
     * @return string
     */
    public function getHorodatedName($name, $pattern = '{{YYYYMMdd}}-{{HHmmss}}')
    {
        // Current date use to compute the index name
        $currentDate = new Zend_Date();

        // Parse pattern to extract datetime tokens
        $matches = array();
        preg_match_all('/{{([\w]*)}}/', $pattern, $matches);

        foreach (array_combine($matches[0], $matches[1]) as $k => $v) {
            // Replace tokens (UTC date used)
            $pattern = str_replace($k, $currentDate->toString($v), $pattern);
        }

        return $name . '-' . $pattern;
    }

}