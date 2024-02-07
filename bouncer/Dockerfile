FROM php:cli as bouncer
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

USER root
# ts:skip=AC_DOCKER_0002 Mis-detecting usage of apt instead of apt-get
# Install nginx, certbot
RUN adduser bouncer && \
    apt-get -qq update && \
    # Install pre-dependencies to use apt-key.
    apt-get -yqq install --no-install-recommends \
        lsb-core \
        gnupg \
        && \
    # Add nginx ppa
    sh -c 'echo "deb http://ppa.launchpad.net/nginx/stable/ubuntu $(lsb_release -sc) main" \
            > /etc/apt/sources.list.d/nginx-stable.list' && \
    # Add nginx key
    apt-key adv --keyserver keyserver.ubuntu.com --recv-keys C300EE8C && \
    # Update
    apt-get -qq update && \
    # Install Nginx, Certbot bits and apache2-utils for htpasswd generation
    apt-get -yqq install --no-install-recommends \
        nginx \
        python3-certbot-nginx \
        apache2-utils \
        && \
    # Cleanup
    apt-get remove -yqq \
        lsb-core \
        cups-common \
        && \
    apt-get autoremove -yqq && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/lib/dpkg/status.old /var/cache/debconf/templates.dat /var/log/dpkg.log /var/log/lastlog /var/log/apt/*.log

# copy some default self-signed certs
COPY self-signed-certificates /certs

# Install runits for services
COPY nginx.runit /etc/service/nginx/run
COPY logs.runit /etc/service/nginx-logs/run
COPY logs.finish /etc/service/nginx-logs/finish
COPY bouncer.runit /etc/service/bouncer/run
COPY bouncer.finish /etc/service/bouncer/finish
COPY logs-nginx-access.runit /etc/service/logs-nginx-access/run
COPY logs-nginx-error.runit /etc/service/logs-nginx-error/run
RUN chmod +x /etc/service/*/run /etc/service/*/finish

# Copy default nginx bits
COPY NginxDefault /etc/nginx/sites-enabled/default.conf
COPY Nginx-tweak.conf /etc/nginx/conf.d/tweak.conf

# Disable daemonising in nginx
RUN sed -i '1s;^;daemon off\;\n;' /etc/nginx/nginx.conf && \
    sed -i 's|include /etc/nginx/sites-enabled/*|include /etc/nginx/sites-enabled/*.conf|g' /etc/nginx/nginx.conf && \
    rm /etc/nginx/sites-enabled/default && \
    rm -R /etc/nginx/sites-available

# Copy over vendored code plus install just in case
COPY vendor /app/vendor
COPY composer.* /app/
RUN composer install

# Copy over application code
COPY public /app/public
COPY bin /app/bin
COPY src /app/src
COPY templates /app/templates
RUN chmod +x /app/bin/bouncer

# Create some volumes for logs and certs
VOLUME /etc/letsencrypt
VOLUME /var/log/bouncer

# Expose ports
EXPOSE 80
EXPOSE 443

# Down-privelege to bouncer
USER bouncer

# Install Composer dependencies even though we don't need to, it should be done in the build process
RUN composer install

# Set a healthcheck to curl the bouncer and expect a 200
HEALTHCHECK --start-period=30s \
    CMD curl -s -o /dev/null -w "200" http://localhost:80/ || exit 1

# stuff some envs from build
ARG BUILD_DATE
ARG GIT_SHA
ARG GIT_COMMIT_MESSAGE
ENV BUILD_DATE=${BUILD_DATE} \
    GIT_SHA=${GIT_SHA} \
    GIT_COMMIT_MESSAGE=${GIT_COMMIT_MESSAGE}

FROM benzine/php:nginx-8.1 as test-app-a
COPY ./test/public-web-a /app/public
HEALTHCHECK --start-period=30s \
    CMD curl -s -o /dev/null -w "200" http://localhost:80/ || exit 1

FROM benzine/php:nginx-8.1 as test-app-b
COPY ./test/public-web-b /app/public
HEALTHCHECK --start-period=30s \
    CMD curl -s -o /dev/null -w "200" http://localhost:80/ || exit 1

FROM benzine/php:nginx-8.1 as test-app-c
COPY ./test/public-web-c /app/public
HEALTHCHECK --start-period=30s \
    CMD curl -s -o /dev/null -w "200" http://localhost:80/ || exit 1

