FROM mongo:7.0
HEALTHCHECK --interval=5s --timeout=3s --start-period=0s --retries=5 \
  CMD echo 'db.stats().ok' | mongosh --norc --quiet --host=localhost:27017
COPY mongo-init.js /docker-entrypoint-initdb.d/
