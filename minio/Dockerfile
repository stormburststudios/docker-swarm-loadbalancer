FROM minio/minio
HEALTHCHECK --interval=5s --timeout=3s --start-period=0s --retries=5 \
    CMD mc ping local -c 1 -q
CMD ["server", "--console-address", ":9001", "/data"]
