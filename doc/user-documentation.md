User documentation
==================

Global configuration
--------------------

The global configuration of the module can be found into the **Catalog Search** section of **System > Configuration > Catalog**.

First, you have access to the global configuration of the search engine shared with all other engines (MySQL and SolR) :

![alt text](assets/config-1.png)


Then you have access to the ES search engine parameters. 
You can check here if the search engine is correctly set up (**Search Engine** param should be set to **Smile Serchandizing Suite**). 
Other parameters should be changed only by developers and are documented into the [Installing the module](install.md).

![alt text](assets/config-2.png)


In the end, you have access to ElasticSearch specific configuration parameters

![alt text](assets/config-3.png)

|Param Name|Description|
-----------|------------
|Facets Max Size|Maximum number of values to display into a facets <br /> **Note :** *This param applies only on term facet and is not used for prices, rating and categories facets.*|
|Enable Search on Options Labels|By default Magento searches only on option ids for select attributes. <br/> This setting allows to use their textual value into fulltext. <br/> Since there is no uses case where the default behavior is expected this setting will be removed from a future version and it's value will be yes by default.|
|Enable ICU Folding Token Filter|ES plugin filter used to clean UTF-8 invalid characters. This feature comes at the expense of the performances and should be enabled only if your catalog contains bad UTF-8 characters.|
|Enable Fuzzy Search|Enable the approching search. Used to automatically fix user mistyping. This feature replaces the traditionnal "Did you mean" feature into the module|
|Fuzzy Prefix Length, Fuzzy Min Similarity, Fuzzy Prefix Length, Fuzzy Max Expansions|Fuzzy search fine tunning. <br /> This parameters can be used to fix the fuzzy search behavior if it match too many or not enough products.|
|Fuzzy Query Boost|Relative weight of fuzzy search result. <br /> Exact matches have a weight of 1. <br /> The default value (0.3) will result approching result being 3 times less important than exact ones.|


Attributes configuration
------------------------

Fulltext search optimization
----------------------------

Front Office
------------

### Facets


### Autocomplete

