#!/bin/bash
#
# Logstash server install
# @author Romain Ruaud
#
# Configuration stuffs
LOGSTASH_VERSION=2.1

wget -qO - https://packages.elastic.co/GPG-KEY-elasticsearch | apt-key add -

echo "deb http://packages.elastic.co/logstash/2.2/debian stable main" > /etc/apt/sources.list.d/logstash.list

apt-get update
apt-get install logstash

# Deploy Logstash configuration
cp -rfv logstash-configuration/es-template.json /etc/logstash/
sed -e "s/SMILE_ELASTICSUITE_TRACKER_TEMPLATE/\/etc\/logstash\/es-template.json/" logstash-configuration/injest-events-output.conf > /etc/logstash/conf.d/injest-events-output.conf
cp -rfv logstash-configuration/injest-events-filter.conf /etc/logstash/conf.d/injest-events-filter.conf
cp -rfv logstash-configuration/injest-events-input.conf /etc/logstash/conf.d/injest-events-input.conf

# Start Logstash and ensure it starts with the system
service logstash restart
update-rc.d logstash defaults

echo ""
echo "Tracker installation finished."
echo "Please configure the Apache Vhost for tracking now."