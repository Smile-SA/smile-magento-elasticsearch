<?php
/**
* Add a table dedicated to contain specific boosted products positions for virtual categories
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
* versions in the future.
*
* @category  Smile
* @package   Smile_VirtualCategories
* @author    Romain Ruaud <romain.ruaud@smile.fr>
* @copyright 2015 Smile
* @license   Apache License Version 2.0
*/
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

/**
* Create table 'smile_virtualcategories/category_product_position'
*/
$table = $installer->getConnection()
    ->newTable($installer->getTable('smile_virtualcategories/category_product_position'))
    ->addColumn(
        'category_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'nullable' => false,
            'primary'  => true,
        ),
        'Query ID'
    )
    ->addColumn(
        'product_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        255,
        array(
            'nullable' => false,
            'primary'  => true
        ),
        'Product Id'
    )
    ->addColumn(
        'store_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        10,
        array(
            'nullable' => false,
            'primary'  => true
        ),
        'Store Id'
    )
    ->addColumn(
        'position',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        255,
        array(
            'nullable' => false,
        ),
        'Product Position'
    )
    ->addIndex(
        $installer->getIdxName('smile_virtualcategories/category_product_position', array('category_id')),
        array('category_id')
    )
    ->addIndex(
        $installer->getIdxName('smile_virtualcategories/category_product_position', array('product_id')),
        array('product_id')
    )
    ->addForeignKey(
        $installer->getFkName(
            'smile_virtualcategories/category_product_position',
            'category_id',
            'catalog/category',
            'entity_id'
        ),
        'category_id',
        $installer->getTable('catalog/category'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setComment('Products positions per virtual category table');

$installer->getConnection()->createTable($table);

$installer->endSetup();
