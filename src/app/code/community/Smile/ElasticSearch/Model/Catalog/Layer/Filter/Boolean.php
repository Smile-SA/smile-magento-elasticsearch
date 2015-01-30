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
     * Returns facets data of current attribute.
     *
     * @return array
     */
    protected function _getFacets()
    {
        $facets = parent::_getFacets();
        $result = array();
        foreach ($facets as $value => $count) {
            $key = 0; // false by default
            if ($value === 'true' || $value === 'T' || $value === '1' || $value === 1 || $value === true) {
                $key = 1;
            }
            $result[$key] = $count;
        }

        return $result;
    }

    /**
     * Checks if given filter is valid before being applied to product collection.
     *
     * @param string $filter Filter to be validated
     *
     * @return bool
     */
    protected function _isValidFilter($filter)
    {
        return $filter === '0' || $filter === '1' || false === $filter || true === $filter;
    }
}
