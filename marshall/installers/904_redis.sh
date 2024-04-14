#!/bin/bash
# shellcheck disable=SC1091
source /usr/local/lib/marshall_installer
install redis-tools
rm \
	/usr/bin/redis-check-aof \
	/usr/bin/redis-check-rdb \
	/usr/bin/redis-benchmark
