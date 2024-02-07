FROM eclipse-mosquitto:injected-version

COPY mosquitto.conf /mosquitto/config/mosquitto.conf

RUN adduser -D mqtt
USER mqtt

HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=5 \
    CMD mosquitto_sub -h localhost -t '$SYS/#' -C 1 -W 3
