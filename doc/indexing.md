# Indexing external content example

## Usage of data providers to append external content to products

### Main concept

You can append external data/content to products during indexation via the usage of Data Providers

You can declare your Data provider in your configuration file like this :

``` xml
    <global>
        <smile_elasticsearch>
            <mapping>
                <product>
                    <data_providers>                        
                        <search_terms_position>smile_elasticsearch/engine_elasticsearch_mapping_dataProvider_terms_position</search_terms_position>
                    </data_providers>
                </product>
            </mapping>
        </smile_elasticsearch>
    </global>
```

Your DataProvider class must extend *Smile_ElasticSearch_Model_Resource_Engine_Elasticsearch_Mapping_DataProvider_Abstract* and implement following methods :

* getMappingProperties() : will return custom fields defined by your module to append to ES mapping
* getEntitiesData($storeId, $entityIds) : will return your custom data for products, and a given store

### Exemple of a dataProvider which append a custom field on products :
  
First step is to add this field into the mapping via getMappingProperties()  
  
```php
  public function getMappingProperties()
  {
      $mapping = array(
          "properties" => array(
              "my_custom_field" => array('type' => 'long', 'doc_values' => true)
          )
      );      

      return $mapping;
  }
```

Then implement the getEntitiesData() method. You must return an array indexed by the product entityId(s)

```php
public function getEntitiesData($storeId, $entityIds)
    {
        $result = array();
        
        // This piece of code is totally dummy, and is here as an exemple
        // We suppose ther is an external data source with logic to retrieve product data
        $externalDataSource = $this->_getExternalDataSource();
        $externalData       = $externalDataSource->getDataForProducts($entityIds);
        
        foreach ($externalData as $data) {
            $result[$data->entityId] = array("my_custom_field" => $data->externalCustomField);
        }
        
        return $result;
    }
```

### Other examples

Here are dataProviders already existing into the Elastic Suite :

* Custom positions for products in search results
* Custom positions for products in virtual categories
* Popularity data added to products from external source

## Example of indexing content into Magento (ex CMS Page):

### Event listening

* smile_elasticsearch_index_create_before  : Create the index mapping before it is published
* smile_elasticsearch_index_install_before : CMS page full reindex when reindexing the whole search engine
* cms_page_save_after                      : Index a single page

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
    <cms_page_save_commit_after>
      <observers>
        <reindex_cms_page>
          <class>my_module/observer</class>
          <method>reindexCmsPage</method>
        </reindex_cms_page>
      </observers>
    </cms_page_save_commit_after>
  </events>
```

### Observer.php

The first method allows to publish the schema of your document type cms_page into ES :

```php
public function addCmsPageMappingToIndex(Varien_Event_Observer $observer)
{
    $indexProperties = $observer->getIndexProperties();
    $indexPropertiesData = $indexProperties->getData();
    $properties = array();
    $categoryMapping = &$indexPropertiesData['body']['mappings']['cms_page']['properties'];
    $helper = Mage::helper('smile_elasticsearch');
    
    foreach (Mage::app()->getStores() as $store) {
        $languageCode = $helper->getLanguageCodeByStore($store);

        // String / text fields :
        foreach (array('title', 'content') as $field) {
          $field = $field . '_' . $languageCode;
          $properties[$field] = array(
            'type' => 'multi_field',
            'fields' => array(
              $field => array(
                'type' => 'string',
                'boost' => $weight > 0 ? $weight : 1,
              ),
              'untouched' => array(
                'type' => 'string',
                'index' => 'not_analyzed',
               ),
            ),
          );
        }
        
        // Other field :
        $properties['example_field'] = array(
          'type' => 'int|float|date'
        )
        
    }

    $indexPropertiesData['body']['mappings']['cms_page']['properties'] = $properties;
    $indexProperties->setData($indexPropertiesData);

    return $this;
})
```

The second method allows to index page from the CMS during full reindexing :

```php
public function cmsPageFullReindex(Varien_Event_Observer $observer)
{
  
    $engine = Mage::helper('catalogsearch')->getEngine();

    if ($engine instanceof Smile_ElasticSearch_Model_Resource_Engine_ElasticSearch) {

        foreach (Mage::app()->getStores() as $store) {
            $languageCode = $helper->getLanguageCodeByStore($store);
            $data = array();

            $pages = Mage::getResourceModel('cms/page_collection')
                ->addStoreFilter($store);
                  
            foreach ($pages as $page) {
                $data[] = array(
                    'title_' . $languageCode       => $page->getTitle(),
                    'description_' . $languageCode => $page->getDescription(),
                    // ... Other indexed fields
                );
            }

            $engine->saveEntityIndexes($store->getId(), $data, 'cms_page');
        }
    }

    return $this;
      
}
```

The third part is in charge of reindexing a page when saving it :
```php
public function reindexCmsPage(Varien_Event_Observer $observer)
{
  $page = $observer->getDataObject();
  $data = array(
    array(
        'title_' . $languageCode       => $page->getTitle(),
        'description_' . $languageCode => $page->getDescription(),
        // ... Other indexed fields
    )
  );
  
  $engine->saveEntityIndexes($store->getId(), $data, 'cms_page');
}
```

You should be able to see indexed page at the URL : http://localhost:9200/magento/cms_page/_search

## Querying custom content

