<?php
/**
 * Product attributes autocomplete block implementation.
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
class Smile_ElasticSearch_Block_Catalogsearch_Autocomplete_Suggest_Product_Attributes
  extends Smile_ElasticSearch_Block_Catalogsearch_Autocomplete_Suggest_Product
{
    /**
     * List of attributes used into completion sorted by code.
     *
     * @var array
     */
    protected $_attributesByCode = array();

    /**
     * Url request variable (default to "q").
     *
     * @var string
     */
    protected $_requestVar = 'q';

    /**
     * Append facets to the main query.
     *
     * @return Smile_ElasticSearch_Block_Catalogsearch_Autocomplete_Suggest_Product_Attributes
     */
    protected function _prepareLayout()
    {
        if ($this->isActive()) {
            $query = $this->getProductCollection()->getSearchEngineQuery();
            $mapping = $query->getMapping();
            $languageCode = $query->getLanguageCode();
            $attributes = $this->getAttributes();

            foreach ($attributes as $attribute) {
                $fieldType = Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_Abstract::FIELD_TYPE_FACET;
                $fieldName = $mapping->getFieldName($attribute, $languageCode, $fieldType);
                $facetOptions = array(
                    'key_field' => $fieldName, 'value_script' => '_score', 'order' => 'total', 'size' => $this->getMaxSize()
                );
                $query->addFacet($attribute, 'termsStats', $facetOptions);
            }
        }

        return $this;
    }

    /**
     * Return the list of all suggested products to be faceted.
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    public function getProductCollection()
    {
        $productCollection = false;

        $productSuggestBlock = $this->getLayout()->getBlock('autocomplete.popular.product');
        if ($productSuggestBlock) {
            $productCollection = $productSuggestBlock->getProductCollection();
        } else {
            $productCollection = parent::getProductCollection();
        }

        return $productCollection;
    }

    /**
     * List of suggestions for the current query
     *
     * @return array
     */
    public function getSuggestions()
    {
        if ($this->getData('suggestions') == null) {
            $suggestions = array();

            foreach ($this->getAttributes() as $attributeCode) {
                $attribute = $this->_attributesByCode[$attributeCode];
                $facetData = $this->getProductCollection()->getFacet($attributeCode)->getResponse();
                foreach ($facetData['terms'] as $termData) {
                    $suggestions[] = $this->_createSuggestion($attribute, $termData['term'], $termData['total']);
                }
            }
            uasort($suggestions, array($this, '_suggestionsComparator'));
            $this->setSuggestions(array_slice($suggestions, 0, $this->getMaxSize()));
        }

        return $this->getData('suggestions');
    }

    /**
     * Util method used to sort the list of suggestions by score
     *
     * @param Varien_Object $a First suggestion.
     * @param Varien_Object $b Second suggestion.
     *
     * @return number
     */
    protected function _suggestionsComparator($a, $b)
    {
        $result = 1;
        if ($a->getScore() == $b->getScore()) {
            $result = 0;
        } else if ($a->getScore() > $b->getScore()) {
            $result = -1;
        }
        return $result;
    }


    /**
     * Generate an object representing the suggestion.
     *
     * @param Mage_Eav_Model_Entity_Attribute $attribute Suggested attribute.
     * @param string                          $value     Suggested value.
     * @param float                           $score     Suggestion score.
     *
     * @return Varien_Object
     */
    protected function _createSuggestion($attribute, $value, $score)
    {
        $urlParams = array($this->_requestVar => $value, $attribute->getAttributeCode() => $value);
        $url = Mage::getUrl('catalogsearch/result/index', array('_query' => $urlParams));
        return new Varien_Object(array('label' => $value, 'url' => $url, 'score' => $score, 'attribute' => $attribute));
    }

    /**
     * Get list of the attributes used as facet into autocomplete.
     *
     * @return array
     */
    public function getAttributes()
    {
        if ($this->getData('attributes') == null) {

            $attributes = array();
            $collection = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setEntityTypeFilter(Mage_Catalog_Model_Product::ENTITY)
                ->addFieldToFilter('is_displayed_in_autocomplete', 1);

            foreach ($collection as $attribute) {
                $attributes[] = $attribute->getAttributeCode();
                $this->_attributesByCode[$attribute->getAttributeCode()] = $attribute;
            }

            $this->setAttributes($attributes);
        }

        return $this->getData('attributes');
    }

    /**
     * Returns attributes suggested orderded by score
     *
     * @param boolean  $useLabel Returns the frontend label instead of the attribute code.
     * @param int|null $topN     Returns only topN results (number of suggest by type is considered).
     *
     * @return array
     */
    public function getSuggestedTypes($useLabel = false, $topN = null)
    {
        $attributes = array();
        $suggestions = $this->getSuggestions();
        foreach ($suggestions as $suggestion) {
            $attribute = $suggestion->getAttribute();

            if ($useLabel) {
                $attribute = $attribute->getFrontendLabel();
            } else {
                $attribute = $attribute->getAttributeCode();
            }

            if (!isset($attributes[$attribute])) {
                 $attributes[$attribute] = 1;
            } else {
                $attributes[$attribute]++;
            }
        }

        asort($attributes);
        $attributes = array_keys($attributes);

        if ($topN) {
            $attributes = array_slice($attributes, 0, $topN);
        }

        return $attributes;
    }

    /**
     * Display types in suggestion only if we suggest several types.
     *
     * @return boolean
     */
    public function displayTypes()
    {
        if ($this->getData('display_types') === null) {
            $suggestedTypes = $this->getSuggestedTypes();
            $this->setDisplayTypes(count($suggestedTypes) > 1);
        }
        return $this->getData('display_types');
    }

    /**
     * Generate a title if not set from the layout.
     *
     * @return string
     */
    public function getTitle()
    {
        if ($this->getData('title') == null) {
            $titleParts = $this->getSuggestedTypes(true, 2);
            if (count($titleParts) > 1) {
                $titleParts[] = '...';
            }
            $titleParts = implode(', ', $titleParts);
            $this->setTitle($titleParts);
        }

        return $this->getData('title');
    }

    /**
     * Get number of suggestion to display
     *
     * @return int
     */
    public function getMaxSize()
    {
        return Mage::getStoreConfig('elasticsearch_advanced_search_settings/attribute_autocomplete/max_size');
    }
}
