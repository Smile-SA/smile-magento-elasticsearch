Install
=======

:angry_face:  **Whenever possible, you must install the module and all it's requirement during the technical kickoff of the project (at the same time you install Magento). Doing so, your developer will have no adaptation to handle ES specificity**


Installing ES
-------------

The module comes with a script which can handle ElasticSearch 1.2 install for you.

Install steps :

* Install the module into Magento
* You can find the script into **scripts/install/install-es.sh**

You need to choose a name for your cluster. You can use the name of your project as name for your cluster.

Then you can run the installer :

```bash
./install_es.sh cluster_name localhost:9200
```

If you want to install ES on several nodes, you need to modify the setup command in the following way (fqdn = Full Qualified Domain Name, the address of the node) :
```bash
./install_es.sh cluster_name node1.fqdn:9200 node2.fqdn:9200 node3.fqdn:9200
```

One the installer have finished 

:angry_face: **The command should be run on every nodes.**

> **What is the installer doing ?**
> The installer proceed to ElasticSearch and all the required dependencies install from the ES official repositories.
>
> It also applies configuration specifics :
> * Unicast discorvery mode with list of nodes (usefull into firewalled environments)
> * Enable MVEL scripting (used by the module)
> * Set the logging to the correct level for production
>
> Additionally it does install the following plugins :
> * Head plugin : a lightweight admin plugin for ES
> * ICU plugin : a plugin which support internationalization enhancement for ES used by the Magento module 

The following port are used by default by ES and should be open :

|Port|Description|
|-----|-----------|
|9200 |This port is used to communicate with ES through it's REST API.<br />It should be allowed to access this port from front and from other ES nodes|
|9200 |This port is used to between ES nodes. If you plan a multiple nodes install dont't forget to open this port for every other nodes.|

Installing the module
---------------------

