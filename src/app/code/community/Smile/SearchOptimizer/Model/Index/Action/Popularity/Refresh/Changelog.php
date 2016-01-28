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
    extends Enterprise_Mview_Model_Action_Mview_Refresh_Changelog
{
    /**
     * Refresh rows by ids from changelog.
     *
     * @return Smile_SearchOptimizer_Model_Index_Action_Popularity_Refresh_Changelog self reference
     */
    public function execute()
    {
        return $this;
    }
}