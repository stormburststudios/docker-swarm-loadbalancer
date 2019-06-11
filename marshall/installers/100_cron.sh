#!/bin/bash
source /app/installers/config
$APT_GET cron

chmod 600 /etc/crontab

mkdir /etc/service/cron
mv /app/etc/service/cron/cron.runit /etc/service/cron/run
chmod +x /etc/service/cron/run
# Fix cron issues in 0.9.19, see also #345: https://github.com/phusion/baseimage-docker/issues/345
sed -i 's/^\s*session\s\+required\s\+pam_loginuid.so/# &/' /etc/pam.d/cron

## Remove useless cron entries.
# Checks for lost+found and scans for mtab.
rm -f /etc/cron.daily/standard
rm -f /etc/cron.daily/upstart
rm -f /etc/cron.daily/dpkg
rm -f /etc/cron.daily/password
rm -f /etc/cron.weekly/fstrim
