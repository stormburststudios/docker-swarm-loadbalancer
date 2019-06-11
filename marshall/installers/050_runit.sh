#!/bin/bash
source /app/installers/config
$APT_GET \
    runit
mv /app/usr/bin/runsvdir-start /usr/bin/runsvdir-start
chmod +x /usr/bin/runsvdir-start