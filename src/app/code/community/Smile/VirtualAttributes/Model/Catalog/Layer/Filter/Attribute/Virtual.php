<?php
/**
 * Default filter model for virtual attributes
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
 * @package   Smile_VirtualAttributes
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Apache License Version 2.0
 */
class Smile_VirtualAttributes_Model_Catalog_Layer_Filter_Attribute_Virtual
    extends Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
{
    /**
     * Create filter item object and transform the option numeric value to option label
     *
     * @param string $label Label of the filter value
     * @param mixed  $value Value of the filter
     * @param int    $count Number of result (default is 0)
     *
     * @return Mage_Catalog_Model_Layer_Filter_Item
     */
    protected function _createItem($label, $value, $count = 0)
    {
        $attributeModel = $this->getAttributeModel();
        $source         = $attributeModel->getSource();

        $label = $source->getOptionText((int) $value);

        return parent::_createItem($label, $value, $count);
    }

    /**
     * Adds facet condition to product collection.
     *
     * @see Smile_ElasticSearch_Model_Resource_Catalog_Product_Collection::addFacetCondition()
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Category
     */
    public function addFacetCondition()
    {
        $attributeModel = $this->getAttributeModel();

        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();

        // Prepare facet query group
        $virtualRule = $this->_getVirtualRule($attributeModel);

        $queries = $virtualRule->getAttributeValuesQueries();
        $options = array('queries' => $queries, 'prefix' => 'virtual_attribute_' . $attributeModel->getAttributeCode());
        $query->addFacet($this->_requestVar, 'queryGroup', $options);

        return $this;
    }

    /**
     * Applies filter to product collection.
     *
     * @param mixed $value Value of the filter
     *
     * @return Smile_ElasticSearch_Model_Catalog_Layer_Filter_Attribute
     */
    public function applyFilterToCollection($values)
    {
        $attributeModel = $this->getAttributeModel();
        if (!is_array($values)) {
            $values = array($values);
        }

        // Retrieve query associated with the filter
        /** @var Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Query_Fulltext $query */
        $query = $this->getLayer()->getProductCollection()->getSearchEngineQuery();

        $virtualRule = $this->_getVirtualRule($attributeModel);

        $queryString = $virtualRule->getSearchQueryForMultipleOptions($attributeModel, $values);
        $query->addFilter('query', array('query_string' => $queryString));

        return $this;
    }

    /**
     * Retrieve rule associated to an attribute.
     *
     * @param Mage_Eav_Model_Attribute $attributeModel The attribute Model
     *
     * @return false|\Mage_Core_Model_Abstract
     */
    protected function _getVirtualRule($attributeModel)
    {
        $virtualRule = Mage::getModel("smile_virtualattributes/rule");
        $virtualRule->setAttribute($attributeModel);

        return $virtualRule;
    }
}
