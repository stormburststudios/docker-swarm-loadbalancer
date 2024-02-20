FROM marshall:build AS release

RUN apt-get -qq update && \
    apt-get -yqq install perl && \
    apt-get -yqq autoremove && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/lib/dpkg/status.old /var/cache/debconf/templates.dat /var/log/dpkg.log /var/log/lastlog /var/log/apt/*.log && \
    perl -v
