# checkov:skip=CKV_DOCKER_3 We're not adding a user, its coming down from on-high in postgres.
FROM postgres:injected-version

LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

# Add healthcheck script
COPY postgres_healthcheck.sh /usr/local/bin/postgres_healthcheck
RUN chmod +x /usr/local/bin/postgres_healthcheck

# Add healthcheck
HEALTHCHECK --start-period=30s --interval=10s --timeout=30s --retries=3 \
    CMD ["/usr/local/bin/postgres_healthcheck"]
