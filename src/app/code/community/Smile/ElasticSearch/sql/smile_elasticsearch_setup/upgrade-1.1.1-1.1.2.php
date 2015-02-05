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
    $connection->addColumn($table, 'is_used_in_autocomplete', "tinyint(1) unsigned NOT NULL DEFAULT '0'");

    // Enable 'is_used_in_automcomplete' by default for the attribute 'name' for product AND category
    $attributeId = $installer->getAttributeId(Mage_Catalog_Model_Product::ENTITY, 'name');
    $connection->update($table, array('is_used_in_autocomplete' => 1), $connection->quoteInto('attribute_id = ?', $attributeId));

    $attributeId = $installer->getAttributeId(Mage_Catalog_Model_Category::ENTITY, 'name');
    $connection->update($table, array('is_used_in_autocomplete' => 1), $connection->quoteInto('attribute_id = ?', $attributeId));

    // Append a column 'is_snowball_used'
    $connection->addColumn($table, 'is_snowball_used', "tinyint(1) unsigned NOT NULL DEFAULT '1'");

    // Append a column 'is_fuziness_enabled', 'fuziness_value and 'fuzziness_prefix_length' into the table
    $connection->addColumn($table, 'is_fuzziness_enabled', "tinyint(1) unsigned NOT NULL DEFAULT '1'");
    $connection->addColumn($table, 'fuzziness_value', "float unsigned NOT NULL DEFAULT '0.75'");
    $connection->addColumn($table, 'fuzziness_prefix_length', "int unsigned NOT NULL DEFAULT '2'");

} catch (Exception $e) {
    Mage::logException($e);
}

$installer->endSetup();
