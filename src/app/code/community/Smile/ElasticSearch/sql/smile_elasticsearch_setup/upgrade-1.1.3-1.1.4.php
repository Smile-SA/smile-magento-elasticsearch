<?php
/**
 * ElasticSearch module setup :
 *
 *   - Append the coverage rate to the facet setting
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Nicolas LŒUILLET <nicolas.loeuillet@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */

/**
 * @var Mage_Catalog_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

try {
    // Append a column 'facets_max_size' into the db
    $connection = $installer->getConnection();
    $table = $installer->getTable('catalog/eav_attribute');
    $connection->addColumn($table, 'facets_max_size', "int unsigned NOT NULL DEFAULT '1000'");

} catch (Exception $e) {
    Mage::logException($e);
}

$installer->endSetup();
