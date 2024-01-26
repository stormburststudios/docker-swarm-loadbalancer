# From upstream redis
# hadolint ignore=DL3007
FROM redis
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"
# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s \
  CMD redis-cli PING
