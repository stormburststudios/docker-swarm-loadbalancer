# From upstream redis
# hadolint ignore=DL3007
FROM mitmproxy/mitmproxy
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

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

# Add healthcheck
#HEALTHCHECK --interval=30s --timeout=3s \
#  CMD redis-cli PING
