FROM ubuntu:latest

MAINTAINER Matthew Baggett <hello@gone.io>

ENV DEBIAN_FRONTEND="teletype" \
    TERM=xterm-256color \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COLOUR_FAIL='\e[31m' \
    COLOUR_SUCCESS='\e[32m' \
    COLOUR_NONE='\e[39m' \
    DEFAULT_TZ='Europe/London'

CMD ["runsvdir", "-P", "/etc/service"]

WORKDIR /app

COPY ./ /app

RUN chmod +x /app/installers/install && \
    mv /app/marshall_* /etc && \
    /app/installers/install && \
    rm -rf /app/installers /app/etc/ /app/usr Dockerfile && \
    ls -lah /etc/ | grep marshall