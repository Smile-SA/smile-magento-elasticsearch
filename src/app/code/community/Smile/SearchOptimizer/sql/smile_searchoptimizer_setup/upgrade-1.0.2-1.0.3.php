<?php
/**
 * Add an Mview metadata group for recommendations indexes
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_SearchOptimizer
 * @author    Romain RUAUD <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

if (Mage::helper("smile_elasticsearch")->isEnterpriseSupportEnabled()) {
    Mage::getModel('enterprise_mview/metadata')
        ->setViewName(Smile_SearchOptimizer_Model_Indexer_Recommendations::METADATA_VIEW_NAME)
        ->setTableName(Smile_SearchOptimizer_Model_Indexer_Recommendations::DUMMY_TABLE_NAME)
        ->setKeyColumn(null)
        ->setGroupCode(Smile_SearchOptimizer_Model_Indexer_Recommendations::METADATA_GROUP_CODE)
        ->setStatus(Enterprise_Mview_Model_Metadata::STATUS_INVALID)
        ->save();
}

$installer->endSetup();
