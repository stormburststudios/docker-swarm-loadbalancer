FROM octoprint/octoprint AS octoprint
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

# hadolint ignore=DL3013
RUN apt-get update -q && \
    apt-get install --no-install-recommends -yq \
        cpulimit \
        python-dev libpq-dev \
        postgresql-client \
    && \
    apt-get autoremove -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/lib/dpkg/status.old /var/cache/debconf/templates.dat /var/log/dpkg.log /var/log/lastlog /var/log/apt/*.log && \
    pip install --no-cache-dir psycopg2

FROM octoprint AS builder
RUN apt-get update -q && \
    apt-get install --no-install-recommends -yq \
        unzip \
        subversion
WORKDIR /build
# hadolint ignore=DL3003
RUN curl -s -L https://github.com/pranjalv123/mjpg-streamer-yu12/archive/refs/heads/master.zip --output mjpeg-streamer-yu12.zip && \
    unzip -q mjpeg-streamer-yu12.zip && \
    cd mjpg-streamer-yu12-master/mjpg-streamer && \
    make

FROM octoprint AS octoprint-mjpg-streamer
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"
COPY --from=builder /build/mjpg-streamer-yu12-master/mjpg-streamer/mjpg_streamer /usr/local/bin/mjpg_streamer
COPY --from=builder /build/mjpg-streamer-yu12-master/mjpg-streamer/*.so /usr/local/lib/mjpg-streamer/