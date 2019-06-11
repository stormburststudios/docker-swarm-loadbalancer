FROM $FROM
RUN apt-get -qq update && \
    apt-get -qy upgrade && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# On build, add anything in with Dockerfile into /app
ONBUILD ADD ./ /app

# If composer.json/composer.lock exist, do a composer install.
ONBUILD RUN composer install; exit 0
ONBUILD RUN composer dumpautoload -o; exit 0
ONBUILD RUN /usr/bin/install-report