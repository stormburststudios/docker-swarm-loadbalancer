FROM ubuntu:focal

LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

ENV DEBIAN_FRONTEND="teletype" \
    TERM=xterm-256color \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COLOUR_FAIL='\e[31m' \
    COLOUR_SUCCESS='\e[32m' \
    COLOUR_NONE='\e[39m' \
    DEFAULT_TZ='Europe/London'


WORKDIR /app
ENV PATH="/app:/app/bin:/app/vendor/bin:${PATH}"
ENV PS1="\[\e[31m\][\[\e[m\]\[\e[38;5;172m\]\u\[\e[m\]@\[\e[38;5;153m\]\h\[\e[m\] \[\e[38;5;214m\]\W\[\e[m\]\[\e[31m\]]\[\e[m\]\\$ "

COPY installers /installers
COPY etc /etc
COPY usr /usr

CMD ["/usr/bin/marshall"]

RUN /installers/install && \
    rm -rf /marshall /installers && \
    chmod +x /usr/bin/marshall
