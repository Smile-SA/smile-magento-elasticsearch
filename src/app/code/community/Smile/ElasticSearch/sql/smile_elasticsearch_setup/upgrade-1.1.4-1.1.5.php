<?php
/**
 * ElasticSearch module setup :
 *
 *   - Fields used in autocomplete configuration
 *   - Optionnal snowball (language analyzer usage)
 *   - Fuzziness enabling and config of the fuzziness distance config can be edited per attributes
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
    // Append a column 'facets_sort_order' into the db
    $connection = $installer->getConnection();
    $table = $installer->getTable('catalog/eav_attribute');
    $connection->addColumn($table, 'facets_sort_order', "varchar(25) NOT NULL DEFAULT 'count'");

} catch (Exception $e) {
    Mage::logException($e);
}

$installer->endSetup();
