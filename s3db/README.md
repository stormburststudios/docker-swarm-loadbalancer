# S3 backed Maria & Postgres Databases

This project comes out of frustration with ephemeral services not providing a trivial database for non-critical services
that can be persisted beyond a restart.

This system starts and checks an s3 bucket for database dumps, downloads loads the most recent one and resets the database to that state.

Every 600 seconds after that, it dumps the database and persists it to s3 if there have been any changes.

This is extremely not meant for production workloads or anything you want to keep. QA systems, silly side projects, anything you don't want to pay Bezos for RDS for.
On that note, please consider using Minio or any other S3 provider than AWS.

## Configuration:

### MariaDB

```yaml
MARIADB_RANDOM_ROOT_PASSWORD: "yes"
MARIADB_USER: example
MARIADB_PASSWORD: changeme
MARIADB_DATABASE: s3db
S3_ENDPOINT: http://minio:9000/
S3_API_KEY: <<secret>>
S3_API_SECRET: <<secret>>
S3_USE_PATH_STYLE_ENDPOINT: "yes" # This is only strictly neccisary with Minio, maybe others.
S3_BUCKET: "s3db"
S3_PREFIX: "test/mariadb/"
```

### Postgres

```yaml
POSTGRES_USER: example
POSTGRES_PASSWORD: changeme
S3_ENDPOINT: http://minio:9000/
S3_API_KEY: <<secret>>
S3_API_SECRET: <<secret>>
S3_USE_PATH_STYLE_ENDPOINT: "yes" # This is only strictly neccisary with Minio, maybe others.
S3_BUCKET: "s3db"
S3_PREFIX: "test/postgres/"
```
