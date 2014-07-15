Developper documentation
========================

**First you should not modify any file shipped with the module (even templates or layout). It should be considered in the same way than Magento core.
You should use your own module / design / skin to extend the module to grant upgrabilty of the module.
Avoid to copy / paste layout files into you own design. Prefer create a new layout file and work into.**

Indexing :
----------

The search engine can mix several types of document into the same index (product, categories, …). 
Every document have a type associated with him.

By default the index is named **magento-YYYY-MM-DD-HHmmss**.
The creation date is used for **YYYY-MM-DD-HHmmss** and the alias configured for the index is used as prefix.

The process for a full reindex is :

1. New index is created,
2. Data are pushed into
3. The alias is switched when indexing is finished
4. The old index is removed

Layered categories :
--------------------
All categories are anchor categories by default.
As a result only **catalog_category_layered** layout handle is used and the **catalog_category_default** one is useless.


Overriding facet templates :
----------------------------

By default all facets share the same template (**smile/elasticsearch/catalog/layer/filter.phtml**). It can be overridden using the same technique I used to override the price facet by using layout :

```xml
<action method="addFilterTemplate">
    <filterName>price</filterName>
    <template>smile/elasticsearch/catalog/layer/filter/price.phtml</template>
</action>
```
Overriding autocomplete templates :
-----------------------------------

Content and rendering of autocomplete can be managed with the **catalogsearch_ajax_suggest** handle of the layout (bellow the version shipped with the module) :

```xml
<catalogsearch_ajax_suggest>
    <remove name="root" />
    <remove name="core_profiler" />

    <block name="autocomplete" type="core/template" template="smile/elasticsearch/autocomplete/autocomplete.phtml" output="toHtml">
        <block type="core/text_list" name="complete_list">
                
            <block type="smile_elasticsearch/catalogsearch_autocomplete_suggest_terms" 
                   name="autocomplete.popular.searches"
                   template="smile/elasticsearch/autocomplete/suggest/terms.phtml" />
                
            <block type="smile_elasticsearch/catalogsearch_autocomplete_suggest_product" 
                   name="autocomplete.popular.product" 
                   template="smile/elasticsearch/autocomplete/suggest/product.phtml" />
                       
            <block type="smile_elasticsearch/catalogsearch_autocomplete_suggest_category" 
                   name="autocomplete.popular.category" 
                   template="smile/elasticsearch/autocomplete/suggest/category.phtml" />      
            </block>    
        </block>
    </block>
</catalogsearch_ajax_suggest>
```