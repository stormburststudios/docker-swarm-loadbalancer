#!/bin/bash
# shellcheck disable=SC1091
source /installers/config
${APT_GET} tzdata
echo "${DEFAULT_TZ}" >/etc/timezone
