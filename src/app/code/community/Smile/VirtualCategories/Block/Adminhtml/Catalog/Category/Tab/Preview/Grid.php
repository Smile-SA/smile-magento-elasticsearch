<?php
/**
 * Preview block for virtual categories
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
class Smile_VirtualCategories_Block_Adminhtml_Catalog_Category_Tab_Preview_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    /** @var null|array The current product Ids  */
    protected $_productIds = null;

    /**
     * Block constructor
     *
     * @return Smile_VirtualCategories_Block_Adminhtml_Catalog_Category_Tab_Preview_Grid self reference
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('virtual_category_preview');
        $this->setDefaultSort('position');
        $this->setUseAjax(true);

        if ($this->getCategory()->getVirtualRulePreview() == null) {
            if ($rule = Mage::helper('smile_virtualcategories')->getVirtualRule($this->getCategory())) {
                $this->getCategory()->setVirtualRulePreview($rule);
            }
        }

        $this->setAdditionalJavaScript($this->_getPreviewPersistenceJavascript());

        return $this;
    }

    /**
     * Return custom javascript needed to ensure persistency of virtual rule while playing with the preview grid
     *
     * @return string
     */
    protected function _getPreviewPersistenceJavascript()
    {
        $javascript = "";

        if ($rule = $this->getRequest()->getParam('rule', false)) {
            $jsonRule   = Mage::helper("core")->jsonEncode($rule);
            $javascript = <<<JAVASCRIPT
            if ({$this->getJsObjectName()}.reloadParams == false) {
                {$this->getJsObjectName()}.reloadParams = { rule : Object.toJSON({$jsonRule}) };
            } else {
                {$this->getJsObjectName()}.reloadParams.rule = Object.toJSON({$jsonRule});
            }
JAVASCRIPT;
        }

        return $javascript;
    }

    /**
     * Retrieve store Id
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->getCategory()->getStoreId();
    }

    /**
     * Prepare the product collection
     * Collection is the matched products for the current query
     *
     * @return Mage_Adminhtml_Block_Widget_Grid self reference
     */
    protected function _prepareCollection()
    {
        $baseQuery  = $this->_getBaseSearchQuery();
        $productIds = $this->_getProductIdsFromSearchQuery($baseQuery);

        if (empty($productIds)) {
            $productIds = array(0);
        }

        $attributes = Mage::getModel('catalog/config')->getProductCollectionAttributes();

        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addIdFilter($productIds)
            ->addAttributeToSelect($attributes);

        $storeIds = implode(",", array_unique(array_map("intval", array(Mage_Core_Model_App::ADMIN_STORE_ID, $this->getStoreId()))));

        if ($this->getStoreId() != Mage_Core_Model_App::ADMIN_STORE_ID) {
            $collection->setStoreId($this->getStoreId());
            $joinCond = "category_id = " . (int) $this->getCategory()->getId() . " AND store_id = {$this->getStoreId()}";
        } else {
            $joinCond = "category_id = " . (int) $this->getCategory()->getId() . " AND store_id IN ({$storeIds})";
        }

        $collection->joinField(
            'position',
            'smile_virtualcategories/category_product_position',
            'position',
            'product_id = entity_id',
            $joinCond,
            'left'
        );

        $collection->getSelect()->order(
            new Zend_Db_Expr("- position DESC") // mimic a "SORT BY #field NULL LAST
        );

        $entityIds = implode(",", $productIds);
        $collection->getSelect()->order(
            new Zend_Db_Expr("FIELD(e.entity_id, {$entityIds})") // restore sort order given by ES
        );

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * Prepare grid columns
     *
     * @return Mage_Adminhtml_Block_Widget_Grid self reference
     *
     * @throws Exception
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'entity_id',
            array(
                'header'   => Mage::helper('catalog')->__('ID'),
                'sortable' => true,
                'width'    => '60',
                'index'    => 'entity_id'
            )
        );

        $this->addColumn(
            'name',
            array(
                'header' => Mage::helper('catalog')->__('Name'),
                'index'  => 'name'
            )
        );
        $this->addColumn(
            'sku',
            array(
                'header' => Mage::helper('catalog')->__('SKU'),
                'width'  => '80',
                'index'  => 'sku'
            )
        );

        $this->addColumn(
            'price',
            array(
                'header'        => Mage::helper('catalog')->__('Price'),
                'type'          => 'currency',
                'width'         => '1',
                'currency_code' => (string) Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE),
                'index'         => 'price'
            )
        );

        $this->addColumn(
            'virtual_category_position',
            array(
                'header'   => Mage::helper('catalog')->__('Position'),
                'width'    => '1',
                'type'     => 'number',
                'index'    => 'position',
                'editable' => true,
                'renderer' => 'smile_virtualcategories/adminhtml_catalog_category_tab_preview_renderer_position'
            )
        );

        return parent::_prepareColumns();
    }

    /**
     * Retrieve grid Url
     *
     * @return string
     */
    public function getGridUrl()
    {
        $parameters = array(
            '_current'     => true,
            'store_id'     => $this->getStoreId(),
            'category_id'  => $this->getCategory()->getId(),
        );

        return $this->getUrl(
            '*/*/preview',
            $parameters
        );
    }

    /**
     * Get the current edit category.
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        if (!$this->_category) {
            $this->_category = Mage::registry('current_category');
        }
        return $this->_category;
    }

    /**
     * Get the ES query to be opimized.
     *
     * @return array
     */
    private function _getBaseSearchQuery()
    {
        $store = Mage::app()->getStore($this->getStoreId());
        $collection = Mage::helper('catalogsearch')
            ->getEngine()
            ->getResultCollection();

        $allowedVisibilities = Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds();
        $allowedStatuses = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();

        $query = $collection->getSearchEngineQuery()
            ->addFilter('terms', array('visibility' => $allowedVisibilities))
            ->addFilter('terms', array('status' => $allowedStatuses));

        if ($this->getStoreId() != Mage_Core_Model_App::ADMIN_STORE_ID) {
            $query->addFilter('terms', array('store_id' => $this->getStoreId()));
        }

        // Append the query string for the virtual categories
        if ($rule = $this->getCategory()->getVirtualRulePreview()) {
            $queryString = $this->_getQueryStringFromRule($rule);
            $query->addFilter('query', array('query_string' => $queryString));
        }

        $query = $query->setLanguageCode(Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store));

        $query->setQueryType("category_products_layer");

        // Mimic query assembling, because ->search() is never really called on it
        $eventData = new Varien_Object(
            array('query' => $query->getRawQuery(), 'query_type' => $query->getQueryType(), 'store_id' => $this->getStoreId())
        );
        Mage::dispatchEvent('smile_elasticsearch_query_assembled', array('query_data' => $eventData));

        $query = $eventData->getQuery();

        return $query;
    }

    /**
     * Retrieve the ES raw query string for a given rule
     *
     * @param Smile_VirtualCategories_Model_Rule $rule The rule
     *
     * @return mixed
     */
    protected function _getQueryStringFromRule(Smile_VirtualCategories_Model_Rule $rule)
    {
        // Do not call directly getSearchQuery() on rule because it would load from cache instead of recalculate
        $rule->addUsedCategoryIds($this->getCategory()->getId());
        $rule->getConditions()->setRule($rule);
        $queryString = $rule->getConditions()->getSearchQuery();

        return $queryString;
    }

    /**
     * Load product ids for the query
     *
     * @param array $query The query to optimize.
     *
     * @return array
     */
    private function _getProductIdsFromSearchQuery($query)
    {
        if (is_null($this->_productIds)) {
            $ids    = array();
            $client = Mage::helper('catalogsearch')->getEngine()->getClient();

            $response = $client->search($query);
            foreach ($response['hits']['hits'] as $hit) {
                $currentId = $hit['fields']['entity_id'];
                if (is_array($currentId)) {
                    $currentId = current($currentId);
                }
                $ids[] = (int) $currentId;
            }
            $this->_productIds = $ids;
        }

        return $this->_productIds;
    }
}