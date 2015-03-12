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
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */

/**
 * @var Mage_Catalog_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

try {
    // Append a column 'is_used_in_autocomplete' into the db
    $connection = $installer->getConnection();
    $table = $installer->getTable('catalog/eav_attribute');
    $connection->addColumn($table, 'facet_min_coverage_rate', "tinyint(1) unsigned NOT NULL DEFAULT '90'");

} catch (Exception $e) {
    Mage::logException($e);
}

$installer->endSetup();
