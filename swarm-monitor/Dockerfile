FROM benzine/php:cli-8.1 as swarm-agent
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

COPY agent.runit /etc/service/agent/run
RUN chmod +x /etc/service/*/run
COPY agent /app
COPY composer.* /app/
RUN composer install && \
    chmod +x /app/agent && \
    mkdir -p /var/log/agent

FROM benzine/php:nginx-8.1 as swarm-stats
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

COPY public /app/public
COPY composer.* /app/
RUN composer install
