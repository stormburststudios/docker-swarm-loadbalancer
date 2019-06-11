FROM gone/php:core-build
RUN apt-get -qq update && \
    apt-get -yq install --no-install-recommends \
        apache2 \
        libapache2-mod-php{{PHPVERSION}} \
        && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    \
    sed -i "s/upload_max_filesize.*/upload_max_filesize = 1024M/g" /etc/php/{{PHPVERSION}}/apache2/php.ini && \
    sed -i "s/post_max_size.*/post_max_size = 1024M/g" /etc/php/{{PHPVERSION}}/apache2/php.ini && \
    sed -i "s/max_execution_time.*/max_execution_time = 0/g" /etc/php/{{PHPVERSION}}/apache2/php.ini && \
    sed -i "s/variables_order.*/variables_order = \"EGPCS\"/g" /etc/php/{{PHPVERSION}}/apache2/php.ini && \
    sed -i "s/error_reporting.*/error_reporting = E_ALL \& \~E_DEPRECATED \& \~E_STRICT \& \~E_CORE_WARNING/g" /etc/php/{{PHPVERSION}}/apache2/php.ini && \
    cp /etc/php/{{PHPVERSION}}/apache2/php.ini /etc/php/{{PHPVERSION}}/cli/php.ini && \
    sed -i "s/ServerSignature On/ServerSignature Off/g" /etc/apache2/conf-enabled/security.conf && \
    sed -i "s/ServerTokens OS/ServerTokens Prod/g" /etc/apache2/conf-enabled/security.conf

# Expose ports.
EXPOSE 80

# Create a healthcheck that makes sure our httpd is up
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/ || exit 1

COPY . /app
RUN rm -fr /var/www/html && \
    ln -s /app /var/www/html && \
    mv ApacheConfig.conf /etc/apache2/sites-enabled/000-default.conf && \
    mv envvars /etc/apache2/ && \
    mv apache2.conf /etc/apache2/ && \
    mkdir /etc/service/apache && \
    mkdir /etc/service/show_logs && \
    mv run.apache.sh /etc/service/apache/run && \
    mv run.show_logs.sh /etc/service/show_logs/run && \
    chmod +x /etc/service/*/run && \
    rm /app/* && \
    a2enmod rewrite

# On build, add anything in with Dockerfile into /app
ONBUILD ADD ./ /app

# If composer.json/composer.lock exist, do a composer install.
ONBUILD RUN composer install --ignore-platform-reqs; exit 0
ONBUILD RUN composer dumpautoload -o; exit 0
ONBUILD RUN /usr/bin/install-report