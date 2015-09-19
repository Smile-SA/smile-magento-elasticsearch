<?php
/**
 * Handles boolean attribute filtering in layered navigation.
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
class Smile_ElasticSearch_Model_Catalog_Layer_Filter_Boolean extends Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
{
    /**
     * Indicates if the filters has more value than what have been currently fetch.
     *
     * @return boolean
     */
    public function hasOthers()
    {
        return false;
    }
}