#!/bin/bash

tail -f /var/log/nginx/error.log &
tail -f /var/log/nginx/access.log
