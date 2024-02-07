FROM redis:version
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

USER redis

# Add healthcheck
HEALTHCHECK --interval=5s --timeout=3s \
  CMD redis-cli PING
