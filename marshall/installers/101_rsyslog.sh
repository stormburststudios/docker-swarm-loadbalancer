#!/bin/bash
source /app/installers/config
$APT_GET rsyslog

mkdir /etc/service/rsyslog /etc/service/rsyslog-show
mv /app/etc/service/rsyslog/rsyslog.runit /etc/service/rsyslog/run
mv /app/etc/service/rsyslog/show-rsyslog.runit /etc/service/rsyslog-show/run
chmod +x /etc/service/rsyslog/run /etc/service/rsyslog-show/run