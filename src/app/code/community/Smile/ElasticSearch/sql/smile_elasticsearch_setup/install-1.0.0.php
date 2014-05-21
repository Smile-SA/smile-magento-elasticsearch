<?php
/**
 * ElasticSearch module setup :
 *
 *   - append weight configuration for catalog eav attribute
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

$installer = $this;
$installer->startSetup();

try {
    $installer->getConnection()->addColumn(
        $installer->getTable('catalog/eav_attribute'),
        'search_weight',
        "tinyint(1) unsigned NOT NULL DEFAULT '1' after `is_searchable`"
    );
} catch (Exception $e) {
    Mage::logException($e);
}

$installer->endSetup();