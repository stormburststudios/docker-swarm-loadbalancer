FROM php:cli as swarm-agent
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

COPY agent.runit /etc/service/agent/run
RUN chmod +x /etc/service/*/run
COPY agent /app
COPY composer.* /app/
RUN composer install && \
    chmod +x /app/agent && \
    mkdir -p /var/log/agent \
CMD ["/app/agent"]

# Healthcheck to detect if process /app/agent is alive
HEALTHCHECK --start-period=30s \
    CMD ps aux | grep -v grep | grep "/app/agent" || exit 1

# Down-privelege to swarm-monitor
# Not sure if this is working but I don't care right now.
USER swarm-monitor

FROM ghcr.io/benzine-framework/php:nginx-8.2 as swarm-stats
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

COPY public /app/public
COPY composer.* /app/
RUN composer install

# Healthcheck looking for something alive on port 80
HEALTHCHECK --start-period=30s \
    CMD curl -s -o /dev/null -w "200" http://localhost:80/ || exit 1

# Down-privelege to swarm-monitor
# Not sure if this is working but I don't care right now.
USER swarm-monitor
