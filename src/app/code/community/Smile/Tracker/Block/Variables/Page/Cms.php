<?php
/**
 * This block handles variables displayed on CMS pages : page identifier and label
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_Tracker
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_Tracker_Block_Variables_Page_Cms extends Smile_Tracker_Block_Variables_Page_Abstract
{
    /**
     * Append the CMS page viewed identifier and title to the list of tracked variables
     *
     * @return array
     */
    public function getVariables()
    {
        $currentPage = Mage::getSingleton('cms/page');

        return array(
            'cms.identifier' => $currentPage->getIdentifier(),
            'cms.title'      => $currentPage->getTitle()
        );
    }



}