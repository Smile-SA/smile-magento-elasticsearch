<?php
/**
 * Tracker init and install block.
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
class Smile_Tracker_Block_Config extends Mage_Core_Block_Template
{

    /**
     * Append the tracking scripts to the head only if the module is enabled.
     *
     * @return Mage_Core_Block_Abstract Self reference
     */
    // @codingStandardsIgnoreStart
    public function _prepareLayout()
    {
        if ($this->isEnabled() && $this->getLayout()->getBlock('head')) {
            $this->getLayout()->getBlock('head')->setIsSmileTrackerEnabled(true);
        }
        return $this;
    }
    // @codingStandardsIgnoreEnd

    /**
     * Is tracker enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::helper('smile_tracker')->isEnabled();
    }

    /**
     * Return the hit HTTP URL.
     * If not set from confi, returns an internal URL that is visible into server access logs.
     *
     * @return string
     */
    public function getBeaconUrl()
    {
        return Mage::helper('smile_tracker')->getBaseUrl();
    }

    /**
     * This URL is the root of Boomerang bandwidth measures URLs.
     *
     * @return string
     */
    public function getBwBaseUrl()
    {
        return Mage::getBaseUrl('js') . 'smile/tracker/boomerang/images/';
    }

    /**
     * Return the tracked site id.
     *
     * @return string
     */
    public function getSiteId()
    {
        return Mage::helper('smile_searchandisingsuite')->getSiteId();
    }

    /**
     * Return the tracked store id.
     *
     * @return int
     */
    public function getStoreId()
    {
        return Mage::app()->getStore()->getId();
    }

    /**
     * Return the session cookie configuration (names and lifetimes)
     * for cookies used by the tracker (visit/session and visitor).
     *
     * @return array
     */
    public function getCookieConfig()
    {
        $config = Mage::helper('smile_tracker')->getCookieConfig();
        return $config;
    }
}