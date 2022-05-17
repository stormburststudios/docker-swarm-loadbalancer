#!/bin/sh
sudo -u www-data /usr/bin/php "$*"
return $?
