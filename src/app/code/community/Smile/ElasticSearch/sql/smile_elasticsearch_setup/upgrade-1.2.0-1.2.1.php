<?php
/**
 * Append Mview index (EE feature) integration for custom products positions in search
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

if (Mage::helper("smile_elasticsearch")->isEnterpriseSupportEnabled()) {

    Mage::getModel('enterprise_mview/metadata')
        ->setViewName(Smile_ElasticSearch_Model_Indexer_Search_Terms_Position::METADATA_VIEW_NAME)
        ->setTableName($installer->getTable('smile_elasticsearch/search_term_product_position'))
        ->setKeyColumn("product_id")
        ->setGroupCode(Smile_ElasticSearch_Model_Indexer_Search_Terms_Position::METADATA_GROUP_CODE)
        ->setStatus(Enterprise_Mview_Model_Metadata::STATUS_INVALID)
        ->save();

    $client = Mage::getModel('enterprise_mview/client');

    /* @var $client Enterprise_Mview_Model_Client */
    $client->init('smile_elasticsearch/search_term_product_position');

    $client->execute(
        'enterprise_mview/action_changelog_create', array(
            'table_name' => $installer->getTable('smile_elasticsearch/search_term_product_position')
        )
    );

    $client->execute(
        'enterprise_mview/action_changelog_subscription_create', array(
            'target_table'  => $installer->getTable('smile_elasticsearch/search_term_product_position'),
            'target_column' => "product_id"
        )
    );
}