<?php
/**
 * Handles attribute filtering in search.
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
class Smile_ElasticSearch_Model_Catalogsearch_Layer_Filter_Attribute extends Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
{
    /**
     * Indicate if attribute is filterable in search.
     *
     * @param Mage_Catalog_Model_Entity_Attribute $attribute Attribute to be tested
     *
     * @return bool
     */
    protected function _getIsFilterableAttribute($attribute)
    {
        return $attribute->getIsFilterableInSearch();
    }
}
