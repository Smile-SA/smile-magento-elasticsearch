<?php
/**
 * Indexer for custom products positions in Virtual categories, based on Mview integration (EE)
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
class Smile_VirtualCategories_Model_Index_Action_VirtualCategories_Product_Position_Refresh
    implements Enterprise_Mview_Model_Action_Interface
{
    /**
     * Mview metadata instance
     *
     * @var Enterprise_Mview_Model_Metadata
     */
    protected $_metadata;

    /**
     * Application instance
     *
     * @var Mage_Core_Model_App
     */
    protected $_app;

    /**
     * Mview factory instance
     *
     * @var Enterprise_Mview_Model_Factory
     */
    protected $_factory;

    /**
     * The recommendation indexer
     *
     * @var Smile_VirtualCategories_Model_Indexer_VirtualCategories_Product_Position
     */
    protected $_indexer;

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
        $this->_metadata = $args['metadata'];
        $this->_app      = !empty($args['app']) ? $args['app'] : Mage::app();
        $this->_factory  = $args['factory'];
        $this->_indexer  = $this->_factory->getSingleton('smile_virtualcategories/indexer_virtualCategories_product_position');
    }

    /**
     * Refresh the custom products position index : just rebuild data from this data provider
     *
     * @return Smile_VirtualCategories_Model_Index_Action_VirtualCategories_Product_Position_Refresh
     *
     * @throws Enterprise_Index_Model_Action_Exception
     */
    public function execute()
    {
        if (!Mage::helper('smile_elasticsearch')->isActiveEngine() == false) {

            $this->_metadata->setInProgressStatus()->save();

            // Let the index process all data
            $this->_indexer->reindexAll();

            if ($this->_metadata->getStatus() == Enterprise_Mview_Model_Metadata::STATUS_IN_PROGRESS) {
                $this->_metadata->setValidStatus()->save();
            }
        }

        return $this;
    }
}