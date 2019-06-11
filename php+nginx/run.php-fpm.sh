#!/bin/bash

sleep 3;
env | sed "s/\(.*\)=\(.*\)/env[\1]='\2'/" > /etc/php/{{PHP}}/fpm/conf.d/env.conf
cat /etc/php/{{PHP}}/fpm/conf.d/env.conf
/usr/sbin/php-fpm{{PHP}}
sleep infinity;

