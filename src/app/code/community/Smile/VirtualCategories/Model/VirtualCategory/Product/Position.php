<?php
/**
 * Model to manage appliance of custom sort order for virtual categories when needed
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile_VirtualCategories
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualCategories_Model_VirtualCategory_Product_Position extends Varien_Object
{
    /**
     * Append sort order based on custom products positions for a given search term
     *
     * @param Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract $query    The ES query
     * @param Mage_Catalog_Model_Category                                            $category The category
     *
     * @return Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract
     */
    public function applyCustomProductsPositions($query, $category)
    {
        if (isset($query['body']['sort'])) {

            if ($this->hasCustomPositions($category)) {

                $sortDefinition = array(
                    'order'           => 'asc',
                    'missing'         => Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Abstract::SORT_ORDER_LAST - 1,
                    'ignore_unmapped' => true
                );

                $sortDefinition['nested_path']   = 'virtual_category_position';
                $sortDefinition['nested_filter'] = array(
                    'term' => array('virtual_category_id' => (int) $category->getId())
                );

                $sort = array("category_product_position" => $sortDefinition);

                array_unshift($query['body']['sort'], $sort);
            }
        }

        return $query;
    }

    /**
     * Verify if a given search query has custom positions defined for products
     *
     * @param Mage_Catalog_Model_Category|int $category The concerned category
     *
     * @return bool
     */
    public function hasCustomPositions($category)
    {
        return Mage::getResourceModel("smile_virtualcategories/catalog_virtualCategory_product_position")
            ->hasCustomPositions($category);
    }
}