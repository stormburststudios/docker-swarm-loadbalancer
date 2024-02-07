#!/bin/bash
# shellcheck disable=SC1091,SC2312
source /installers/config
echo "force-unsafe-io" >/etc/dpkg/dpkg.cfg.d/force-unsafe-io

# We're gonna move the sources to .d/ubuntu.list, then copy it, then manipulate it for a mirror list.
#(
#	cat /etc/apt/sources.list
#	sed 's/http\:\/\/archive\.ubuntu\.com\/ubuntu\//mirror\:\/\/mirrors.ubuntu.com\/mirrors.txt/g' </etc/apt/sources.list |
#		sed "s|deb http://security.ubuntu.com|# deb http://security.ubuntu.com|g"
#) |
#	sed '/^#/d' |
#	sed '/^$/d' \
#		>/etc/apt/sources.list.d/ubuntu.list
#rm /etc/apt/sources.list
#touch /etc/apt/sources.list

# Remove duplicate sources
sort /etc/apt/sources.list.d/ubuntu.list | uniq >/etc/apt/sources.list.d/ubuntu.list.uniq
mv /etc/apt/sources.list.d/ubuntu.list.uniq /etc/apt/sources.list.d/ubuntu.list

# Update apt repos
apt-get -qq update

# System upgrade
apt-get -yq upgrade

# Install apt-utils & ca-certificates to prevent some screaming.
${APT_GET} ca-certificates
${APT_GET} apt apt-utils
