<?php
/**
 * Popularity refresh indexer.
 * Dummy implementation for changelog, to ensure not breaking Enterprise_Index_Model_Observer::refreshIndex()
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_SearchOptimizer
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_SearchOptimizer_Model_Index_Action_Popularity_Refresh_Changelog
    extends Smile_SearchOptimizer_Model_Index_Action_Popularity_Refresh
{
    /**
     * Refresh the popularity index : just rebuild data from this data provider
     *
     * @return Smile_SearchOptimizer_Model_Index_Action_Popularity_Refresh
     *
     * @throws Enterprise_Index_Model_Action_Exception
     */
    public function execute()
    {
        $this->_metadata->setInProgressStatus()->save();

        $lastVersionDate = new Zend_Date($this->_metadata->getVersionId(), Zend_Date::TIMESTAMP);

        // Let the index process all data
        $this->_indexer->reindexPartial($lastVersionDate);

        $currentDate = new Zend_Date();
        $this->_metadata->setVersionId($currentDate->getTimestamp());

        if ($this->_metadata->getStatus() == Enterprise_Mview_Model_Metadata::STATUS_IN_PROGRESS) {
            $this->_metadata->setValidStatus()->save();
        }

        return $this;
    }
}