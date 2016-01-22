<?php
/**
 * Grid showing products for the current search results and their positions
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
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2015 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Block_Adminhtml_Catalog_Search_Edit_Tab_Boost_Preview
    extends Mage_Adminhtml_Block_Widget_Grid
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /** @var null|array The current product Ids  */
    protected $_productIds = null;

    /**
     * Block constructor
     *
     * @return Smile_ElasticSearch_Block_Adminhtml_Catalog_Search_Edit_Tab_Boost_Preview self reference
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('catalog_search_boost_config');
        $this->setDefaultSort('position');
        $this->setUseAjax(true);

        return $this;
    }

    /**
     * Prepare content for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Custom results positions');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Custom results positions');
    }

    /**
     * Returns status flag about this tab can be showed or not
     *
     * @return true
     */
    public function canShowTab()
    {
        return ($this->getQuery()->getId() && Mage::helper("smile_elasticsearch")->isActiveEngine());
    }

    /**
     * Returns status flag about this tab hidden or not
     *
     * @return true
     */
    public function isHidden()
    {
        return (!$this->getQuery()->getId() || !Mage::helper("smile_elasticsearch")->isActiveEngine());
    }

    /**
     * Retrieve current query
     *
     * @return Mage_CatalogSearch_Model_Query|null
     */
    public function getQuery()
    {
        return Mage::registry('current_catalog_search');
    }

    /**
     * Get the fulltext query string.
     *
     * @return string
     */
    public function getFulltextQuery()
    {
        return $this->getQuery()->getQueryText();
    }

    /**
     * Retrieve store Id
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->getQuery()->getStoreId();
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
            ->setStoreId($this->getStoreId())
            ->addIdFilter($productIds)
            ->addAttributeToSelect($attributes);

        $collection->joinField(
            'position',
            'smile_elasticsearch/search_term_product_position',
            'position',
            'product_id = entity_id',
            'query_id = ' . (int) $this->getQuery()->getId(),
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
            'position',
            array(
                'header'   => Mage::helper('catalog')->__('Position'),
                'width'    => '1',
                'type'     => 'number',
                'index'    => 'position',
                'editable' => true,
                'renderer' => 'smile_elasticsearch/adminhtml_catalog_search_edit_tab_boost_preview_renderer_position'
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
        return $this->getUrl(
            '*/*/preview',
            array(
                '_current'   => true,
                'store_id'   => $this->getStoreId(),
                'query_text' => Mage::helper("core")->urlEncode($this->getFulltextQuery())
            )
        );
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
            ->getResultCollection()
            ->addSearchFilter($this->getFulltextQuery());

        $allowedVisibilities = Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
        $allowedStatuses = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();

        $query = $collection->getSearchEngineQuery()
            ->addFilter('terms', array('store_id' => $this->getStoreId()))
            ->addFilter('terms', array('visibility' => $allowedVisibilities))
            ->addFilter('terms', array('status' => $allowedStatuses))
            ->setLanguageCode(Mage::helper('smile_elasticsearch')->getLanguageCodeByStore($store));

        $query->setQueryType("product_search_layer");

        // Mimic query assembling, because ->search() is never really called on it
        $eventData = new Varien_Object(
            array('query' => $query->getRawQuery(), 'query_type' => $query->getQueryType(), 'store_id' => $this->getStoreId())
        );
        Mage::dispatchEvent('smile_elasticsearch_query_assembled', array('query_data' => $eventData));

        $query = $eventData->getQuery();

        return $query;
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