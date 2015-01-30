<?php
/**
 * Tracking variables block display
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
class Smile_Tracker_Block_Variables_Abstract extends Mage_Core_Block_Template
{
    /**
     * Indicate if the block is enabled or not
     *
     * @return Mage_Core_Block_Abstract Self reference
     */
    public function isEnabled()
    {
        $mainBlock = $this->getLayout()->getBlock('smile.tracker.config');

        return !is_null($mainBlock) && $mainBlock->isEnabled();
    }

    /**
     * Display the block only if main block is present and enabled
     *
     * @return string Block rendering
     */
    protected function _toHtml()
    {
        $html = '';
        if ($this->isEnabled()) {
            $html = parent::_toHtml();
        }
        return $html;
    }
}