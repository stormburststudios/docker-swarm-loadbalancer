FROM php:nginx
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

COPY nginx.runit /etc/service/nginx/run
RUN wget -q -O /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x /etc/service/nginx/run \
             /usr/local/bin/wp \
    && \
    echo "alias wp=\"wp --allow-root\"" >> /etc/bash.bashrc && \
    wp --allow-root --version

USER wordpress
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/ || exit 1
