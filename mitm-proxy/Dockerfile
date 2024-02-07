FROM mitmproxy:version
ARG BUILD_DATE
ARG GIT_SHA
ARG MITM_VERSION
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker/mitm-proxy" \
      org.opencontainers.image.title="mitm-proxy with healthcheck" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.version="${MITM_VERSION}" \
      org.opencontainers.image.revision="${GIT_SHA}" \
      org.opencontainers.image.vendor="Benzine" \
      org.opencontainers.image.authors="Matthew Baggett <matthew@baggett.me>"

# Install curl
# hadolint ignore=DL3018,DL4006
RUN os=$(grep "^ID=" < /etc/os-release | cut -f2 -d'=') && \
    echo "OS: $os" && \
    if [ "$os" = "debian" ] || [ "$os" = "ubuntu" ]; then \
        apt-get update -yqq && \
        apt-get install -yqq --no-install-recommends \
            curl \
            bash \
        && \
        apt-get clean && \
        apt-get autoclean && \
        rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/lib/dpkg/status.old /var/cache/debconf/templates.dat /var/log/dpkg.log /var/log/lastlog /var/log/apt/*.log; \
    elif [ "$os" = "alpine" ]; then \
        apk add \
          --update \
          --no-cache \
            curl \
            bash \
        ; \
    else \
        echo "Unknown OS: $os"; \
      exit 1; \
    fi

USER mitmproxy

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=3s \
  CMD curl -I -x http://localhost:8080 -k https://www.google.com || exit 1
