#!/bin/bash
# shellcheck disable=SC1091
source /installers/config
${APT_GET} rsyslog

mkdir -p /etc/service/rsyslog
mv /etc/service/rsyslog/rsyslog.runit /etc/service/rsyslog/run
mv /etc/service/rsyslog/rsyslog.finish /etc/service/rsyslog/finish
chmod +x /etc/service/rsyslog/run /etc/service/rsyslog/finish
