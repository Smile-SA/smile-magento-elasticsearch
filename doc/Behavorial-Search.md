Behavorial Search Features
==========================

The module comes with mechanism which allows the engine to analyze behavior of the users of the website to modify search results scoring.

This section explains how to configure this feature and extend the available models.

Data collector
--------------

### Prepare ElasticSearch

In order to make the feature working, you will need to install the ES tracking indexer shipped with the module (**es/plugins/tracking-indexer/tracking-indexer-current.jar**).

The module is normally installed with ES if you have used the automated install script (see [Installing the module](install.md)). 

You can check the module is correctly installed by running the following command from the shell :

```bash
/usr/share/elasticsearch/bin/plugin --list
```

If not installed, you can rerun the install script after you have check you have the last version of the module.
You can also run the following command to install the plugin from the source :

```bash
/usr/share/elasticsearch/bin/plugin -i tracking-indexer -u file:///SOURCE_ROOT/es/tracking-indexer/tracking-indexer-current.jar
```

> **Note :** 
> When using SPBuilder, make sure the es directory of your project is part of your delivery package. It is not the case bu default, but you can add the path to the svn-components (More details at https://wiki.smile.fr/view/Dirtech/Projets/SpBuilderProperties)

### Apache configuration

> **Note :**
> If using Varnish, you have to exclude the hit url from the cached ones.

### Smile Tracker



Optimizer models
----------------