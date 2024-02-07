# Automatic Swarm Nginx Load Balancer

## Environment variables

This container has its own environment variables, AS WELL AS scanning for some environment variables associated with your services.
These should not be confused.

### Load Balancer Configuration

#### Main configuration

| Key                                           | Default                                         | Options                                     | Behaviour                                                                                                                                                                                                                            |
| --------------------------------------------- | ----------------------------------------------- | ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --- |
| DOCKER_HOST                                   | false                                           |                                             | Define a http endpoint representing your docker socket. If this is null, it connects to /var/lib/docker.sock                                                                                                                         |
| GLOBAL_CERT                                   | false                                           | Contents of an ssl certificate              | If you want to provide a single cert for all endpoints, perhaps with a catch-all that may be later overriden, you can provide the whole contents of a certificates file here.                                                        |
| GLOBAL_CERT_KEY                               | false                                           | Contents of an ssl certificates private key | The private key related to GLOBAL CERT. These must be provided in tandem.                                                                                                                                                            |
| BOUNCER_FORCED_UPDATE_INTERVAL_SECONDS        | false                                           | positive numbers                            | To force the bouncer to update on a schedule even if no changes are detected, measured in seconds                                                                                                                                    |
| BOUNCER_MAXIMUM_NGINX_CONFIG_CREATION_NOTICES | 15                                              | positive numbers                            | To limit the number lines of output regarding which domains have been configured. Any more domains than this count, and none will be output, instead replaced by "More than 15 Nginx configs generated.. Too many to show them all!" |
| LOG_NAME                                      | bouncer                                         |                                             | The name of the log file to write to                                                                                                                                                                                                 |
| LOG_FILE                                      | /var/log/bouncer/bouncer.log                    |                                             | The path to the log file to write to                                                                                                                                                                                                 |
| LOG_LEVEL                                     | debug                                           | info, debug, critical etc                   | The level of logging to write to the log file. See Monolog docs.                                                                                                                                                                     |
| LOG_LEVEL_NAME_LENGTH                         | 4                                               | positive numbers                            | The length of the level name to be written to the log file. See Monolog docs.                                                                                                                                                        |
| LOG_LINE_FORMAT                               | [%datetime%] %level_name%: %channel%: %message% |                                             | The format of the log line. See Monolog docs.                                                                                                                                                                                        |
| LOG_COLOUR                                    | true                                            | true, false                                 | Whether to colourise the log output sent to stdout.                                                                                                                                                                                  |     |

#### For using with Lets Encrypt:tm:

| Key                       | Default   | Options                   | Behaviour                                                                            |
| ------------------------- | --------- | ------------------------- | ------------------------------------------------------------------------------------ |
| BOUNCER_LETSENCRYPT_MODE  | 'staging' | 'staging' or 'production' | Determine if this is going to connect to a production or staging Lets Encrypt server |
| BOUNCER_LETSENCRYPT_EMAIL |           | 'bob@example.com'         | Email address to associate with lets encrypt                                         |

#### For using S3-compatable storage for generated cert synchronisation with Lets Encrypt

| Key                                | Default | Options         | Behaviour                                                                             |
| ---------------------------------- | ------- | --------------- | ------------------------------------------------------------------------------------- | --- |
| BOUNCER_S3_BUCKET                  | false   |                 | enable S3 behaviour to store lets-encrypt generated certs                             |
| BOUNCER_S3_ENDPOINT                | false   |                 | define s3 endpoint to override default AWS s3 implementation, for example, with minio |
| BOUNCER_S3_KEY_ID                  | false   |                 | S3 API Key ID                                                                         |     |
| BOUNCER_s3_KEY_SECRET              | false   |                 | S3 API Key Secret                                                                     |
| BOUNCER_S3_REGION                  | false   |                 | S3 API Region                                                                         |
| BOUNCER_S3_USE_PATH_STYLE_ENDPOINT | false   | `true or false` | Needed for minio                                                                      |
| BOUNCER_S3_PREFIX                  | false   |                 | Prefix file path in s3 bucket                                                         |

### Served Instance Configuration

These environment variables need to be applied to the CONSUMING SERVICE and not the loadbalancer container itself.

| Key                            | Example                                                                 | Behaviour                                                                                                                                                                                                   |
| ------------------------------ | ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| BOUNCER_DOMAIN                 | "a.example.com"                                                         | The domain that should be directed to this container                                                                                                                                                        |
| BOUNCER_LABEL                  | "MyService"                                                             | The label that should be directed to this container                                                                                                                                                         |
| BOUNCER_AUTH                   | "username:password" e.g "root:toor"                                     | Add a HTTP BASIC auth requirement to this hostname.                                                                                                                                                         |
| BOUNCER_HOST_OVERRIDE          | "localhost:80"                                                          | Override the host header that is sent to the service. Useful for services that are not aware of their own hostname, or annoying things like [mitmproxy](https://github.com/mitmproxy/mitmproxy/issues/3234) |
| BOUNCER_LETSENCRYPT            | Values are "yes" or "true", anything else is false                      | To enable, or disable Lets Encrypt service for this hostname                                                                                                                                                |
| BOUNCER_TARGET_PORT            | 9000                                                                    | Explicitly define the port you want to hit the service on, in case of ambiguity                                                                                                                             |
| BOUNCER_ALLOW_NON_SSL          | Defaults to enabled. Values are "yes" or "true", anything else is false | Should HTTP only traffic be allowed to hit this service? If disabled, http traffic is forwarded towards https                                                                                               |
| BOUNCER_ALLOW_WEBSOCKETS       | Defaults to enabled. Values are "yes" or "true", anything else is false | Enable websocket behaviour                                                                                                                                                                                  |
| BOUNCER_ALLOW_LARGE_PAYLOADS   | Defaults to disabled.                                                   | Allows overriding the default nginx payload size. Related to BOUNCER_MAX_PAYLOADS_MEGABYTES                                                                                                                 |
| BOUNCER_MAX_PAYLOADS_MEGABYTES | numbers                                                                 | Size of max payload to allow, in megabytes. Requires BOUNCER_ALLOW_LARGE_PAYLOADS to be enabled                                                                                                             |

## Security considerations

If you're putting this behind access control to the docker socket, it will need access to the /swarm /services and /containers endpoints of the docker api.
