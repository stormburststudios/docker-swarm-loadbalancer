#!/bin/bash
source /app/installers/config
cd /
apt-get autoremove -y
apt-get clean
rm -rf \
        /var/lib/apt/lists/* \
        /tmp/* \
        /var/tmp/*
