# checkov:skip=CKV_DOCKER_3 We're not adding a user, its coming down from on-high in mariadb.
FROM mariadb:injected-version

LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

# If healthcheck.sh isn't baked into the underlying image, crash.
RUN which healthcheck.sh

# Add healthcheck
HEALTHCHECK --start-period=30s --interval=10s --timeout=30s --retries=3 \
  CMD ["healthcheck.sh", "--su-mysql", "--connect", "--innodb_initialized"]

