<?php
/**
 * Smile tracker utils
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
class Smile_Tracker_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Module status configuration path
     * @var string
     */
    const CONFIG_IS_ENABLED_XPATH = 'smile_tracker/general/enabled';

    /**
     * Tracking URL configuration path
     * @var string
     */
    const CONFIG_BASE_URL_XPATH   = 'smile_tracker/general/base_url';

    /**
     * Coookie configuration configuration path
     * @var string
     */
    const CONFIG_COOKIE           = 'smile_tracker/session';

    /**
     * Return the module activation status
     *
     * @return bool
     */
    public function isEnabled()
    {
        return (bool) Mage::getStoreConfig(self::CONFIG_IS_ENABLED_XPATH);
    }

    /**
     * Return the tracking base URL (params are added later)
     *
     * @return string
     */
    public function getBaseUrl()
    {
        $result = Mage::getStoreConfig(self::CONFIG_BASE_URL_XPATH);

        if (!$result) {
            $result = Mage::getBaseUrl('js') . 'smile/tracker/hit.png';
        }

        return $result;
    }

    /**
     * Return an array containing the cookie configuration
     *
     * @return array
     */
    public function getCookieConfig ()
    {
        return Mage::getStoreConfig(self::CONFIG_COOKIE);
    }
}