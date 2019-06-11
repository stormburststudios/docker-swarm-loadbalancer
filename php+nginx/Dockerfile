FROM gone/php:core-build
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
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    \
    sed -i "s/cgi.fix_pathinfo.*/cgi.fix_pathinfo=0/g" /etc/php/{{PHPVERSION}}/fpm/php.ini && \
    sed -i "s/upload_max_filesize.*/upload_max_filesize = 1024M/g" /etc/php/{{PHPVERSION}}/fpm/php.ini && \
    sed -i "s/post_max_size.*/post_max_size = 1024M/g" /etc/php/{{PHPVERSION}}/fpm/php.ini && \
    sed -i "s/max_execution_time.*/max_execution_time = 0/g" /etc/php/{{PHPVERSION}}/fpm/php.ini && \
    sed -i "s/variables_order.*/variables_order = \"EGPCS\"/g" /etc/php/{{PHPVERSION}}/fpm/php.ini && \
    sed -i "s/error_reporting.*/error_reporting = E_ALL \& \~E_DEPRECATED \& \~E_STRICT \& \~E_CORE_WARNING/g" /etc/php/{{PHPVERSION}}/fpm/php.ini && \
    cp /etc/php/{{PHPVERSION}}/fpm/php.ini /etc/php/{{PHPVERSION}}/cli/php.ini && \
    echo "clear_env=no" >> /etc/php/{{PHPVERSION}}/fpm/php-fpm.conf && \
    echo "clear_env=no" >> /etc/php/{{PHPVERSION}}/fpm/pool.d/www.ini && \
    mkdir /run/php && \
    cat /etc/php/{{PHPVERSION}}/fpm/php-fpm.conf | grep clear_env && \
    cat /etc/php/{{PHPVERSION}}/fpm/pool.d/www.ini | grep clear_env

# Expose ports.
EXPOSE 80

# Create a healthcheck that makes sure our httpd is up
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/ || exit 1

COPY . /app
RUN rm -fr /var/www/html && \
    ln -s /app /var/www/html && \
    mv NginxDefault /etc/nginx/sites-enabled/default && \
    mkdir /etc/service/nginx && \
    mkdir /etc/service/php-fpm && \
    mkdir /etc/service/show_logs && \
    mv run.nginx.sh /etc/service/nginx/run && \
    mv run.php-fpm.sh /etc/service/php-fpm/run && \
    mv run.show_logs.sh /etc/service/show_logs/run && \
    chmod +x /etc/service/*/run && \
    rm /app/* && \
    sed -i "s/{{PHP}}/{{PHPVERSION}}/g" /etc/nginx/sites-enabled/default && \
    sed -i "s/{{PHP}}/{{PHPVERSION}}/g" /etc/service/php-fpm/run

# On build, add anything in with Dockerfile into /app
ONBUILD ADD ./ /app

# If composer.json/composer.lock exist, do a composer install.
ONBUILD RUN composer install; exit 0
ONBUILD RUN composer dumpautoload -o; exit 0
ONBUILD RUN /usr/bin/install-report