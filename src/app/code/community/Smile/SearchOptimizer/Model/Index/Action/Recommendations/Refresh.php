<?php
/**
 * Recommendations refresh indexer.
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
class Smile_SearchOptimizer_Model_Index_Action_Recommendations_Refresh
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
     * @var Smile_SearchOptimizer_Model_Indexer_Recommendations
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
        $this->_indexer  = $this->_factory->getSingleton('smile_searchoptimizer/indexer_recommendations');
    }

    /**
     * Refresh the recommendations index : just rebuild data from this data provider
     *
     * @return Smile_SearchOptimizer_Model_Index_Action_Recommendations_Refresh
     *
     * @throws Enterprise_Index_Model_Action_Exception
     */
    public function execute()
    {
        $this->_metadata->setInProgressStatus()->save();

        // Let the index process all data
        $this->_indexer->reindexAll();

        if ($this->_metadata->getStatus() == Enterprise_Mview_Model_Metadata::STATUS_IN_PROGRESS) {
            $this->_metadata->setValidStatus()->save();
        }

        return $this;
    }
}
