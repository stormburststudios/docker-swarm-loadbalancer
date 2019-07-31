FROM gone/php:core-build

ENV PHPFPM_MAX_CHILDREN=5

RUN apt-get -qq update && \
    apt-get -yq install --no-install-recommends \
        lsb-core \
        gnupg \
        && \
    sh -c 'echo "deb http://ppa.launchpad.net/nginx/stable/ubuntu $(lsb_release -sc) main" \
            > /etc/apt/sources.list.d/nginx-stable.list' && \
    apt-key adv --keyserver keyserver.ubuntu.com --recv-keys C300EE8C && \
    apt-get -qq update && \
    apt-get -yq install --no-install-recommends \
        nginx \
        php{{PHPVERSION}}-fpm \
        && \
    apt-get remove -yq \
        lsb-core \
        cups-common \
        software-properties-common \
        python-apt-common \
        python3-software-properties \
        python3.5 python3.5-minimal libpython3.5-minimal \
        && \
    apt-get autoremove -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Expose ports.
EXPOSE 80

# Create a healthcheck that makes sure our httpd is up
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/ || exit 1

COPY . /app
RUN bash /app/configure-nginx.php{{PHPVERSION}}.sh && \
    rm -f /app/configure-nginx.php{{PHPVERSION}}.sh && \
    rm -fr /var/www/html && \
    ln -s /app /var/www/html && \
    mv NginxDefault /etc/nginx/sites-enabled/default && \
    mkdir /etc/service/nginx && \
    mkdir /etc/service/php-fpm && \
    mv nginx.runit /etc/service/nginx/run && \
    mv php-fpm.runit /etc/service/php-fpm/run && \
    chmod +x /etc/service/*/run && \
    rm /app/* && \
    sed -i "s/{{PHP}}/{{PHPVERSION}}/g" /etc/nginx/sites-enabled/default && \
    sed -i "s/{{PHP}}/{{PHPVERSION}}/g" /etc/service/php-fpm/run && \
    # Enable status panel
    sed -i -e "s/;pm.status_path/pm.status_path/g" /etc/php/*/fpm/pool.d/www.conf && \
    # Using environment variables in config files works, it would seem. Neat!
    sed -i -e "s/pm.max_children = 5/pm.max_children = \${PHPFPM_MAX_CHILDREN}/g" /etc/php/*/fpm/pool.d/www.conf

# On build, add anything in with Dockerfile into /app
ONBUILD ADD ./ /app

# If composer.json/composer.lock exist, do a composer install.
ONBUILD RUN composer install; exit 0
ONBUILD RUN composer dumpautoload -o; exit 0
ONBUILD RUN /usr/bin/install-report