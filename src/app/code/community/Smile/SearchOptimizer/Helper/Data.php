<?php
/**
 * Optimizer helper.
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
 * @copyright 2014 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Alias location in configuration
     */
    const RECOMMENDER_ALIAS_CONFIG_PATH = "elasticsearch_advanced_search_settings/behavioral_optimizers/recommender_index_alias";

    /**
     * Retrieve Recommender Index
     *
     * @return mixed
     */
    public function getRecommenderIndex()
    {
        return Mage::getStoreConfig(self::RECOMMENDER_ALIAS_CONFIG_PATH);
    }
}