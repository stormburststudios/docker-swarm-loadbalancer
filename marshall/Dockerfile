# checkov:skip=CKV_DOCKER_3 user cannot be determined at this stage.
FROM ubuntu:version

LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

ARG MARSHALL_VERSION
ARG MARSHALL_BUILD_DATE
ARG MARSHALL_BUILD_HOST
ENV DEBIAN_FRONTEND="teletype" \
    TERM=xterm-256color \
    DEFAULT_TZ='Europe/London' \
    MARSHALL_VERSION=${MARSHALL_VERSION} \
    MARSHALL_BUILD_DATE=${MARSHALL_BUILD_DATE} \
    MARSHALL_BUILD_HOST=${MARSHALL_BUILD_HOST}

WORKDIR /app
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
ENV PATH="/app:/app/bin:/app/vendor/bin:${PATH}"
ENV PS1="\[\e[31m\][\[\e[m\]\[\e[38;5;172m\]\u\[\e[m\]@\[\e[38;5;153m\]\h\[\e[m\] \[\e[38;5;214m\]\W\[\e[m\]\[\e[31m\]]\[\e[m\]\\$ "

COPY installers /installers
COPY etc /etc
COPY usr /usr

CMD ["/usr/bin/marshall"]

RUN /installers/install && \
    rm -rf /marshall /installers && \
    chmod +x /usr/bin/marshall

# Disable healthcheck, as healthcheck is nonsensical for this container.
HEALTHCHECK NONE
