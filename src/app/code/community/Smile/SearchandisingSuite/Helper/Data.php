<?php
/**
* Smile Searchandising Suite utils implementation
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
* versions in the future.
*
* @category  Smile
* @package   Smile_Searchandising_Suite
* @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
* @copyright 2013 Smile
* @license   Apache License Version 2.0
*/
class Smile_SearchandisingSuite_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var string Config path for site id.
     */
    const CONFIG_SITE_ID_XPATH = 'smile_searchandisingsuite_general/website/site_id';

    /**
     * Retrieve site id from the configuration.
     *
     * @return string
     */
    public function getSiteId()
    {
        return (string) Mage::getStoreConfig(self::CONFIG_SITE_ID_XPATH);
    }
}