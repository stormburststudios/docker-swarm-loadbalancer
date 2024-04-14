#!/bin/bash
# shellcheck disable=SC1091,SC2164
source /usr/local/lib/marshall_installer
title "Installing version control tools"

# MB: This is all a bunch of hacky stuff because I really, really don't want to install perl.
(
	install equivs
	mkdir /tmp/equivs
	cd /tmp/equivs
	{
		echo "Section: misc"
		echo "Priority: optional"
		echo "Standards-Version: 3.9.2"
		echo "Package: perl-local"
		echo "Provides: perl, liberror-perl"
	} >perl-local
	equivs-build perl-local 2>/dev/null 1>&2
	dpkg -i perl-*.deb 2>/dev/null 1>&2
	remove equivs
	rm -rf /tmp/equivs
)

install git
