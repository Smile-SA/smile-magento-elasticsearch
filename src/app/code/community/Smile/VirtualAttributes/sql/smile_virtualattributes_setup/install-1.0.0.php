<?php
/**
 * ElasticSuite Virtual Attributes module setup :
 *
 *   - Append a table to store virtual attributes options values.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Romain RUAUD <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */

/**
 * @var Mage_Catalog_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

try {
    $connection = $installer->getConnection();

    /**
     * Create table 'smile_virtualattributes/attribute_option_value'
     */
    $table = $installer->getConnection()
        ->newTable($installer->getTable('smile_virtualattributes/attribute_option_value'))
        ->addColumn('value_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ), 'Value Id')
        ->addColumn('option_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ), 'Option Id')
        ->addColumn('label', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
            'nullable'  => true
        ), 'Option Label')
        ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
            'unsigned'  => true,
            'nullable'  => false,
            'default'   => '0',
        ), 'Store Id')
        ->addColumn('value', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', array(
            'nullable'  => true,
            'default'   => null,
        ), 'Value')
        ->addIndex($installer->getIdxName('smile_virtualattributes/attribute_option_value', array('store_id')),
            array('store_id'))
        ->addIndex($installer->getIdxName('smile_virtualattributes/attribute_option_value', array('option_id')),
            array('option_id'))
        ->addForeignKey(
            $installer->getFkName('smile_virtualattributes/attribute_option_value', 'option_id', 'eav/attribute_option', 'option_id'),
            'option_id', $installer->getTable('eav/attribute_option'), 'option_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
        ->addForeignKey(
            $installer->getFkName('smile_virtualattributes/attribute_option_value', 'store_id', 'core/store', 'store_id'),
            'store_id', $installer->getTable('core/store'), 'store_id',
            Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
        ->setComment('Smile Virtual Attributes Options Values');

    $installer->getConnection()->createTable($table);

} catch (Exception $e) {
    Mage::logException($e);
}

$installer->endSetup();
