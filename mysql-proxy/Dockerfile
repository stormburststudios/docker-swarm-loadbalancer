FROM benzine/marshall:latest AS mysql-proxy

LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

ENV MYSQL_PROXY_VERSION 0.8.5
ENV MYSQL_PROXY_TAR_NAME mysql-proxy-$MYSQL_PROXY_VERSION-linux-debian6.0-x86-64bit
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get upgrade -y ca-certificates tzdata && \
    apt-get -y install --no-install-recommends \
      wget \
      mysql-client \
    && \
    wget https://downloads.mysql.com/archives/get/p/21/file/$MYSQL_PROXY_TAR_NAME.tar.gz && \
    tar -xzvf $MYSQL_PROXY_TAR_NAME.tar.gz && \
    mv $MYSQL_PROXY_TAR_NAME /opt/mysql-proxy && \
    rm $MYSQL_PROXY_TAR_NAME.tar.gz && \
    DEBIAN_FRONTEND=noninteractive apt-get -y remove wget && \
    apt-get autoremove -yqq && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/ && \
    chown -R root:root /opt/mysql-proxy && \
    printf "#!/bin/bash\n\
\n\
exec /opt/mysql-proxy/bin/mysql-proxy \\\\\n\
--keepalive \\\\\n\
--log-level=error \\\\\n\
--plugins=proxy \\\\\n\
--proxy-address=\${PROXY_DB_HOST}:\${PROXY_DB_PORT} \\\\\n\
--proxy-backend-addresses=\${REMOTE_DB_HOST}:\${REMOTE_DB_PORT} \\\\\n\
--proxy-lua-script=/opt/mysql-proxy/conf/main.lua\n\
" >> /usr/local/bin//entrypoint.sh && \
    chmod u+x /usr/local/bin/entrypoint.sh && \
    ln -s /usr/local/bin/docker-entrypoint.sh /entrypoint.sh # shortcut

HEALTHCHECK --interval=5s --timeout=3s --start-period=5s --retries=3 \
    CMD mysqladmin ping -h 127.0.0.1 -p 3306 -u root || exit 1

ENTRYPOINT [ "entrypoint.sh" ]

COPY main.lua /opt/mysql-proxy/conf/main.lua

# For another derived image:

# --help-all
# --proxy-backend-addresses=mysql:3306
# --proxy-skip-profiling
# --proxy-backend-addresses=host:port
# --proxy-read-only-backend-addresses=host:port
# --keepalive
# --admin-username=User
# --admin-password=Password
# --log-level=crititcal
# The log level to use when outputting error messages.
# Messages with that level (or lower) are output.
# For example, message level also outputs message with info, warning, and error levels.
