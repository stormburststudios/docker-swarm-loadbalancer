# Todo List

- Create an actual healthcheck endpoint. It should:
  - Display if the initial docker socket scan & generation is complete
  - Show Domains served by the proxy behind a per-service option flag
    - Domains should be grouped
    - Some statistics about the domains such as the number of instances serving it should be presented
    - Maybe the amount of traffic being sent to it?
- Switch to using service labels instead of envs
- Write tests to verify the S3/Lets Encrypt functionality still works
- Log traffic to somewhere useful
- Add a way to write logs to external services, exposing the Monolog behavior
