Install
=======

:angry_face:  **Whenever possible, you must install the module and all it's requirement during the technical kickoff of the project (at the same time you install Magento). Doing so, your developer will have no adaptation to handle ES specificity**


Installing ES
-------------

### Automated install

The module comes with a script which can handle ElasticSearch install for you.

Install steps :

* Install the module into Magento
* You can find the script into **scripts/install/install-es.sh**

You need to choose a name for your cluster. You can use the name of your project as name for your cluster.

Then you can run the installer :

<pre><code class="bash">
./install_es.sh cluster_name localhost:9200
</code></pre>

If you want to install ES on several nodes, you need to modify the setup command in the following way (fqdn = Full Qualified Domain Name, the address of the node) :
<pre><code class="bash">
./install_es.sh cluster_name node1.fqdn:9200 node2.fqdn:9200 node3.fqdn:9200
</code></pre>

:angry_face: The command should be run on every nodes.

### Manual install

Installing the module
---------------------

