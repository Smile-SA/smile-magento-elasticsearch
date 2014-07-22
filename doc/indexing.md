# Indexing external content example



## Example of indexing content into Magento (ex CMS Page):

### Event listening

* smile_elasticsearch_index_create_before  : Create the index mapping before it is published
* cms_page_save_after                      : Index a single page
* cms_page_delete_commit_after             : Delete the page
* smile_elasticsearch_index_install_before : CMS page full reindex when reindexing the whole search engine


``` xml
  <events>
    <smile_elasticsearch_index_create_before>
        <observers>
            <add_category_mapping_to_index>
                <class>smile_elasticsearch/observer</class>
                <method>addCmsPageMappingToIndex</method>
            </add_category_mapping_to_index>
        </observers>
    </smile_elasticsearch_index_create_before>
    <smile_elasticsearch_index_install_before>
      <observers>
          <add_category_mapping_to_index>
            <class>smile_elasticsearch/observer</class>
            <method>cmsPageFullReindex</method>
          </add_category_mapping_to_index>
      </observers>
    </smile_elasticsearch_index_install_before>
    <cms_page_save_after>
      <observers>
        <reindex_cms_page>
          <class>my_module/observer</class>
          <method>reindexCmsPage</method>
        </reindex_cms_page>
      </observers>
    </cms_page_save_after>
    <cms_page_delete_commit_after>
      <observers>
        <search_delete_cms_page>
          <class>my_module/observer</class>
          <method>deteleCmsPage</method>
        </search_delete_cms_page>
      </observers>
    </cms_page_delete_commit_after>
  </events>
```

### Observer.php

```php
   /**
     * Index category mapping setup
     *
     * @param Varien_Event_Observer $observer Event data
     *
     * @return Modyf_Search_Model_Observer
     */
    public function addCategoryMappingToIndex(Varien_Event_Observer $observer)
    {
        $indexProperties = $observer->getIndexProperties();
        $indexPropertiesData = $indexProperties->getData();
        $indexPropertiesData['body']['mappings']['category']['properties'] = array();
        $categoryMapping = &$indexPropertiesData['body']['mappings']['category']['properties'];
        $helper = Mage::helper('smile_elasticsearch');
        
        foreach (Mage::app()->getStores() as $store) {
            $languageCode = $helper->getLanguageCodeByStore($store);
            $categoryMapping[$helper->getSuggestFieldName($store)] = array(
                'type'     => 'completion',
                'payloads' => true,
                'max_input_length' => 500,
                'index_analyzer' => 'analyzer_' . $languageCode,
                'search_analyzer' => 'analyzer_' . $languageCode,
                'preserve_separators' => false
            );
        }

        $indexProperties->setData($indexPropertiesData);

        return $this;
    })
```

## Querying custom content

