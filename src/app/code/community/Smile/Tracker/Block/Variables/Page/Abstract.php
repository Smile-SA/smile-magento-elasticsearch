<?php
/**
 * This block return a list of variables to be added to the tracking as page variables.
 * Various implementation are added to the pages (catalog, search, ...) to measure various things
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
class Smile_Tracker_Block_Variables_Page_Abstract extends Smile_Tracker_Block_Variables_Abstract
{
    /**
     * Set the default template for page variable blocks
     *
     * @return void Nothing
     */
    // @codingStandardsIgnoreStart
    public function _construct()
    {
        $this->setTemplate('smile/tracker/variables/page.phtml');
    }
    // @codingStandardsIgnoreEnd

    /**
     * List of the variables added to the tracker
     * Array keys are variable name
     *
     * @return array
     */
    public function getVariables()
    {
        $variables = array();
        return array();
    }
}