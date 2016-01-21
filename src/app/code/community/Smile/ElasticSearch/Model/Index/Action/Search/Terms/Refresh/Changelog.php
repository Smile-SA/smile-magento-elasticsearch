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
class Smile_ElasticSearch_Model_Index_Action_Search_Terms_Refresh_Changelog
    extends Enterprise_Mview_Model_Action_Mview_Refresh_Changelog
{
    /**
     * Last version ID
     *
     * @var int
     */
    protected $_lastVersionId;

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

        /** @var $changelog Enterprise_Index_Model_Changelog */
        $changelog = $this->_factory->getModel(
            'enterprise_index/changelog',
            array(
                'connection' => $this->_connection,
                'metadata'   => $this->_metadata
            )
        );

        $this->_changedIds = $changelog->loadByMetadata();
        $this->_changedIds = array_unique($this->_changedIds);
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

            try {

                if (!empty($this->_changedIds)) {

                    $this->_metadata->setInProgressStatus()->save();

                    $engine       = Mage::helper('catalogsearch')->getEngine();
                    $mapping      = $engine->getCurrentIndex()->getMapping('product');
                    $dataprovider = $mapping->getDataProvider('search_terms_position');

                    $dataprovider->updateAllData(null, $this->_changedIds);

                    $this->_updateMetadata();
                }

            } catch (Exception $e) {
                $this->_metadata->setInvalidStatus()->save();
                throw new Enterprise_Index_Model_Action_Exception($e->getMessage(), $e->getCode());
            }
        }

        return $this;
    }

    /**
     * Set changelog valid and update version id into metedata
     *
     * @return Enterprise_Index_Model_Action_Abstract
     */
    protected function _updateMetadata()
    {
        if ($this->_metadata->getStatus() == Enterprise_Mview_Model_Metadata::STATUS_IN_PROGRESS) {
            $this->_metadata->setValidStatus();
        }
        $this->_metadata->setVersionId($this->_getLastVersionId())->save();
        return $this;
    }

    /**
     * Return last version ID
     *
     * @return string
     */
    protected function _getLastVersionId()
    {
        $changelogName = $this->_metadata->getChangelogName();
        if (empty($changelogName)) {
            return 0;
        }

        if (!$this->_lastVersionId) {
            $select = $this->_connection->select()
                ->from($changelogName, array('version_id'))
                ->order('version_id DESC')
                ->limit(1);

            $this->_lastVersionId = (int) $this->_connection->fetchOne($select);
        }

        return $this->_lastVersionId;
    }
}