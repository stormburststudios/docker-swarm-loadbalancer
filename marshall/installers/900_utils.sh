#!/bin/bash
# shellcheck disable=SC1091
source /installers/config

${APT_GET} \
	inetutils-ping \
	nano \
	host \
	curl \
	wget \
	unzip \
	ca-certificates \
	jq
