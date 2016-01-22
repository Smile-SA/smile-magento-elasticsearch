<?php
/**
 * Indexer for Custom search terms positions, based on Mview integration (EE)
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Model_Index_Action_Search_Terms_Refresh_Row
    extends Enterprise_Mview_Model_Action_Mview_Refresh_Row
{
    /**
     * Connection instance
     *
     * @var Varien_Db_Adapter_Interface
     */
    protected $_connection;

    /**
     * Mview metadata instance
     *
     * @var Enterprise_Mview_Model_Metadata
     */
    protected $_metadata;

    /**
     * Mview factory instance
     *
     * @var Enterprise_Mview_Model_Factory
     */
    protected $_factory;

    /**
     * Array of product IDs to reindex
     *
     * @var array
     */
    protected $_productIds = array();

    /**
     * Constructor with parameters
     *
     * @param array $args Array of arguments with keys
     *  - 'metadata' Enterprise_Mview_Model_Metadata
     *  - 'connection' Varien_Db_Adapter_Interface
     *  - 'factory' Enterprise_Mview_Model_Factory
     */
    public function __construct(array $args)
    {
        $this->_app      = !empty($args['app']) ? $args['app'] : Mage::app();
        $this->_factory  = $args['factory'];

        parent::__construct($args);
    }

    /**
     * Refresh rows by ids from changelog.
     *
     * @return Smile_ElasticSearch_Model_Index_Action_Search_Terms_Refresh_Changelog
     *
     * @throws Enterprise_Index_Model_Action_Exception
     */
    public function execute()
    {
        if (!$this->_metadata->isValid()) {
            throw new Enterprise_Index_Model_Action_Exception("Can't perform operation, incomplete metadata!");
        }

        if (Mage::helper('smile_elasticsearch')->isActiveEngine() == true) {

            $this->_setProductIdsFromValue();

            $engine       = Mage::helper('catalogsearch')->getEngine();
            $mapping      = $engine->getCurrentIndex()->getMapping('product');
            $dataprovider = $mapping->getDataProvider('search_terms_position');

            $dataprovider->updateAllData(null, $this->_productIds);
        }

        return $this;
    }

    /**
     * Set value ID to product IDs to be re-indexed
     *
     * @return void Nothing
     */
    protected function _setProductIdsFromValue()
    {
        $this->_productIds = $this->_keyColumnIdValue;
    }
}