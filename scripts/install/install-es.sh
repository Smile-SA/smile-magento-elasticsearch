#!/bin/bash
#
# ElasticSearch server install
# @author Aurelien FOUCRET
#

# Configuration stuffs
ES_VERSION=1.5
ES_PLUGIN_CMD=/usr/share/elasticsearch/bin/plugin
ES_LOCAL_PLUGIN_DIR=`dirname $0`/../../es/plugins

if [ "$#" -lt 2 ]; then
    echo "Usage : ./install-es.sh cluster_name server1:port <server2:port> ..."
    exit 1
fi
CLUSTER_NAME=$1
HOSTS=`printf -- "\"%s\", " ${@:2} | rev | cut -c3- | rev`

# Installing Java and ES from the packages
wget -O - http://packages.elasticsearch.org/GPG-KEY-elasticsearch | apt-key add -
echo "deb http://packages.elasticsearch.org/elasticsearch/$ES_VERSION/debian stable main" > /etc/apt/sources.list.d/elaticsearch.list
apt-get update
apt-get install elasticsearch openjdk-7-jdk

# Deploy configuration
sed -e "s/CLUSTER_NAME/\"$CLUSTER_NAME\"/;s/HOSTS/$HOSTS/;" es-conf-templates/elasticsearch.yml > /etc/elasticsearch/elasticsearch.yml
cp -rfv es-conf-templates/logging.yml /etc/elasticsearch/

# Start ES and ensure it starts with the system
service elasticsearch restart
update-rc.d elasticsearch defaults

# Installing plugins required by Magento modules
$ES_PLUGIN_CMD -r head
$ES_PLUGIN_CMD -install mobz/elasticsearch-head
$ES_PLUGIN_CMD -r kopf
$ES_PLUGIN_CMD -install lmenezes/elasticsearch-kopf/master
$ES_PLUGIN_CMD -r analysis-icu
$ES_PLUGIN_CMD -install elasticsearch/elasticsearch-analysis-icu/2.5.0
$ES_PLUGIN_CMD -r analysis-phonetic
$ES_PLUGIN_CMD -install elasticsearch/elasticsearch-analysis-phonetic/2.5.0
