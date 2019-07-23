#!/bin/bash
source /app/installers/config
$APT_GET rsyslog

mkdir /etc/service/rsyslog
mv /app/etc/service/rsyslog/rsyslog.runit /etc/service/rsyslog/run
chmod +x /etc/service/rsyslog/run