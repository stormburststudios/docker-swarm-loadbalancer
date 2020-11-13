#!/bin/bash
source /installers/config
cd /
apt-get autoremove -y
apt-get clean
rm -rf \
        /var/lib/apt/lists/* \
        /tmp/* \
        /var/tmp/* \
        /var/cache/* \
        /var/log/dpkg* \
        /usr/share/doc \
        /var/log/apt/term.log