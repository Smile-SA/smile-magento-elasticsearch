<?php
/**
 * Common methods used by ES filter blocks.
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
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Block_Catalog_Layer_Filter_Abstract extends Mage_Catalog_Block_Layer_Filter_Abstract
{
    /**
     * Return the filter request var (used to build URL)
     *
     * @return string
     */
    public function getRequestVar()
    {
        return $this->_filter->getRequestVar();
    }

    /**
     * Indicates if the filters has more value than what have been currently fetch.
     *
     * @return boolean
     */
    public function hasOthers()
    {
        return $this->_filter->hasOthers();
    }
}
