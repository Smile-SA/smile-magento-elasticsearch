Behavorial Search Features
==========================

The module comes with mechanism which allows the engine to analyze behavior of the users of the website to modify search results scoring.

This section explains how to configure this feature and extend the available models.

Data collector
--------------

### Install Logstash

Logstash (version >=2.1.1) is required for the data collector to work properly.

If you did use the install-es.sh script bundled with this package, Logstash should already been installed with default configuration.

In another case, to install Logstash on Debian :

> $ wget -qO - https://packages.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
>
> $ echo "deb http://packages.elastic.co/logstash/2.2/debian stable main" | sudo tee -a /etc/apt/sources.list
>
> $ sudo apt-get update && sudo apt-get install logstash

For other distros take a look at : https://www.elastic.co/guide/en/logstash/current/package-repositories.html

### Apache configuration

You will need a new domain name to collect tracking.

For a site named **www.mysite.com**, you can use a new domain called **t.mysite.com** or **hit.mysite.com** by example.

#### Install the vhost :

You need to replace MAGENTO_ROOT on the sample configuration below by your Magento's root directory

``` conf
<VirtualHost *:80>
    ServerName t.mysite.com

    DocumentRoot <MAGENTO_ROOT>/js/smile/tracker/

    <Directory <MAGENTO_ROOT>/js/smile/tracker/>
      AllowOverride None
      Order allow,deny
      Allow from all
    </Location>

    RewriteEngine on
    RewriteRule .* /hit.png

    LogFormat "%h %l %u [%{%Y-%m-%d %T}t] \"%r\" %>s %b \"%{Referer}i\" \"%{User-agent}i\"" timed_combined
    CustomLog /var/log/smile_searchandising_suite/apache_raw_events/event.log timed_combined

</VirtualHost>
```

#### Create log target folder

Even if not mandatory, it is strongly advised that you create a separated directory to store the logs produced by the tracker :

Following instruction need to be adapted if you don't use the same path as described upon.

> mkdir -p /var/log/smile_searchandising_suite/apache_raw_events


> **Note :**
> * On multi-front servers, you have to append the VirtualHost on all frontal server, on consider redirecting the traffic for this domain on a dedicated server.
> * On multi-front servers, you have to append logstash to all front server, or consider using a log collector policy to gather all logs into a same place.
> * If using Varnish, you have to exclude the hit domain from the cache.
> * If using SSL on your website, you will need **to duplicate this configuration on the SSL port (443)** in order your website respond to https://t.mysite.com correctly. **You will need a valid certificate for this domain.**
> * Use the same domain name for SSL and non-SSL (a limitation into the tracking module does not allow different domain name).

### Smile Tracker

The Smile_Tracker module is shipped with the ES install. This module is in charge of tagging pages of the website with small PNG image with relay information about customer navigation to the newly created Apache vhost.

The only thing, you have to do is configuring the URL of your tracker.
This configuration can be found into **System > Configuration > Smile Searchandising Suite> Tagging** :

![Tracking configuration](assets/config-tracker.png)

|Param|Description|
|-----|-----------|
|Tracker Base Url|The URL of the Apache proxy. <br/> Use an any png suffix (h.png, hit.png, ...). <br /> Prefer URL relative protocol with // (http://www.paulirish.com/2010/the-protocol-relative-url/) especially if using a website with SSL.|
|Cookie params|Cookies can be adjusted to change the duration of the session or of the visitor identification. Their name can be changed (not recommended) to avoid collision with other cookies.|


Optimizer models
----------------

Once the data collect is installed, you can use the optimizer module.
The purpose of this module is to apply custom scoring models to search.

You can access the optimizers though **Catalog > Search > Optimizers** and will found a list of all optimizers applied on the website :

![Optimizer list](assets/optimizer-list.png)

> **About applying optimizer on the categories list**
>
> There is a regression in ES (https://github.com/elasticsearch/elasticsearch/issues/6788), which preventing using the rescorer on categories.
> The bug will be fixed in a future release and updating ES will apply optimizer as a secondary sort on category product list (after the position of the product chosen by the admin).

At this time two model of optimizer are available :

* **Constant Score**
* **Popularity**

### Constant Score Optimizers

![Constant score optimizer](assets/constant-score-optimizer.png)

> **Description :** This model apply a a boost defined in percent by the admin to a selection of products.

### Popularity Optimizers

![Popularity](assets/popularity-optimizer.png)

> **Description :** This model give a boost to product according to their popularity. Value of the boost is computed using the params below

|Param|Description|
|-----|-----------|
|Popularity type|The type of event that will be counted to determine popularity : number of sales or number of views|
|Scale function|The function that will be applied to the number of sales / views. Most of time, use logarithm (log10) is the best model and avoid product to be overrated|
|Scale factor|A multiplication factor applied to the count before applying the scale function. <br />Use low scale factor for views (0.1) and higher for sales (10)|
|Decrease duration (in days)|Every day, the count is decreased by a small amount. This param set the number of day before the count reaches 50% of it's value|

**Exemples :**

|scale function |scale factor|Count = 10|Count=100|Count = 1000|
|---------------|------------|----------|---------|------------|
|log            |           1|         1|        2|           3|
|log            |          10|         2|        3|           4|
|log            |         0.1|         1|        1|           2|
|sqrt           |           1|       3.1|       10|          31|
|sqrt           |          10|        10|       31|         100|
|sqrt           |         0.1|         1|      3.1|          10|
|linear         |           1|        10|      100|        1000|
|linear         |          10|       100|     1000|       10000|
|linear         |         0.1|         1|       10|         100|

### Custom Optimizer Development

You can develop your own model in your module by implementing a class inherited **Smile_SearchOptimizer_Model_Optimizer_Abstract** and by appending it to the config :

**Example :**

The constant score implementation :

``` php
class Smile_SearchOptimizer_Model_Optimizer_ConstantScore extends Smile_SearchOptimizer_Model_Optimizer_Abstract
{
    /**
     * @var string
     */
    protected $_name = 'Constant score';

    /**
     * Append model configuration to the form.
     *
     * @param Varien_Data_Form                      $form      Form the config should be added to.
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     *
     * @return Smile_SearchOptimizer_Model_Optimizer_Abstract Self reference.
     */
    public function prepareForm($form, $optimizer)
    {
        parent::prepareForm($form, $optimizer);

        $fieldset = $form->getElement('model_config_fieldset');

        $fieldset->addField(
            'config_boost_value',
            'text',
            array(
              'name'      => 'config[boost_value]',
              'label'     => Mage::helper('smile_searchoptimizer')->__('Boost value (%)'),
              'title'     => Mage::helper('smile_searchoptimizer')->__('Boost value (%)'),
              'required'  => true
            )
        );
    }

    /**
     * Apply the model to the query.
     *
     * @param Smile_SearchOptimizer_Model_Optimizer $optimizer Current optimizer.
     * @param array                                 $query     Query to optimize.
     *
     * @return array The modified query.
     */
    public function apply($optimizer, $query)
    {
        $boostFactor = 1 + ((float) $optimizer->getConfig('boost_value') / 100);
        $rescoreQuery = array(
          'function_score' => array(
             'boost_factor' => $boostFactor,
             'boost_mode'   => 'replace'
          )
        );

        $filterRuleSearchQuery = $optimizer->getFilterRuleSearchQuery();

        if ($filterRuleSearchQuery !== false) {
            $rescoreQuery['function_score']['filter'] = array(
              'query' => array('query_string' => array('query' => $filterRuleSearchQuery))
            );
        } else {
            $rescoreQuery['function_score']['query'] = array('match_all' => array());
        }

        $query['body']['rescore'][] = array(
          'window_size' => 1000,
          'query' => array(
            'rescore_query' => $rescoreQuery,
            'score_mode'    => 'multiply'
          )
        );

        return $query;
    }
}
```

It's declaration into config.xml :

``` xml
<config>
   <global>
       <smile_searchoptimizer>
            <optimizer_models>
                <constant_score>smile_searchoptimizer/optimizer_constantScore</constant_score>
            </optimizer_models>
       </smile_searchoptimizer>
   </global>
</config>