```bash
 ▄████  ▒█████   ███▄    █ ▓█████       ██▓ ▒█████
 ██▒ ▀█▒▒██▒  ██▒ ██ ▀█   █ ▓█   ▀      ▓██▒▒██▒  ██▒
▒██░▄▄▄░▒██░  ██▒▓██  ▀█ ██▒▒███        ▒██▒▒██░  ██▒
░▓█  ██▓▒██   ██░▓██▒  ▐▌██▒▒▓█  ▄      ░██░▒██   ██░
░▒▓███▀▒░ ████▓▒░▒██░   ▓██░░▒████▒ ██▓ ░██░░ ████▓▒░
 ░▒   ▒ ░ ▒░▒░▒░ ░ ▒░   ▒ ▒ ░░ ▒░ ░ ▒▓▒ ░▓  ░ ▒░▒░▒░
░▄▄▄▄ ░ ░▄▄▄░ ▒░ ░ ░██████░▓█████ ░ ░ ██▓ ███▄░▄███▓ ▄▄▄        ▄████ ▓█████
▓█████▄ ▒████▄░   ▒██    ▒░▓█   ▀ ░  ▓██▒▓██▒▀█▀ ██▒▒████▄     ██▒ ▀█▒▓█   ▀
▒██▒ ▄██▒██  ▀█▄  ░ ▓██▄   ▒███      ▒██▒▓██    ▓██░▒██  ▀█▄  ▒██░▄▄▄░▒███
▒██░█▀  ░██▄▄▄▄██   ▒   ██▒▒▓█  ▄    ░██░▒██    ▒██ ░██▄▄▄▄██ ░▓█  ██▓▒▓█  ▄
░▓█  ▀█▓ ▓█   ▓██▒▒██████▒▒░▒████▒   ░██░▒██▒   ░██▒ ▓█   ▓██▒░▒▓███▀▒░▒████▒
░▒▓███▀▒ ▒▒   ▓▒█░▒ ▒▓▒ ▒ ░░░ ▒░ ░   ░▓  ░ ▒░   ░  ░ ▒▒   ▓▒█░ ░▒   ▒ ░░ ▒░ ░
▒░▒   ░   ▒   ▒▒ ░░ ░▒  ░ ░ ░ ░  ░    ▒ ░░  ░      ░  ▒   ▒▒ ░  ░   ░  ░ ░  ░
 ░    ░   ░   ▒   ░  ░  ░     ░       ▒ ░░      ░     ░   ▒   ░ ░   ░    ░
 ░            ░  ░      ░     ░  ░    ░         ░         ░  ░      ░    ░  ░ 
```
[![Build](https://github.com/goneio/base-image/actions/workflows/build.yml/badge.svg)](https://github.com/goneio/base-image/actions/workflows/build.yml)

Docker PHP Base kit based on lessons learned from phusion/baseimage using runit to allow for multiple processes, featuring multiple versions of PHP and NodeJS.

| Name                 | Architecture |                                                                                                Size |        Last Updated |                                                                                  Microbadger                                                                                   |
|----------------------|--------------|----------------------------------------------------------------------------------------------------:|--------------------:|:------------------------------------------------------------------------------------------------------------------------------------------------------------------------------:|
| gone/marshall:latest | ARM64, AMD64 | [![Layers](https://img.shields.io/badge/49.04MB-green.svg)](https://hub.docker.com/r/gone/marshall) | 2021-02-16 13:04:48 | [![](https://images.microbadger.com/badges/image/gone/marshall:latest.svg)](https://microbadger.com/images/gone/marshall:latest "Get your own image badge on microbadger.com") |
| gone/php:apache      | AMD64        |     [![Layers](https://img.shields.io/badge/127.09MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:19:35 |      [![](https://images.microbadger.com/badges/image/gone/php:apache.svg)](https://microbadger.com/images/gone/php:apache "Get your own image badge on microbadger.com")      |
| gone/php:cli         | AMD64        |     [![Layers](https://img.shields.io/badge/123.56MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:19:35 |         [![](https://images.microbadger.com/badges/image/gone/php:cli.svg)](https://microbadger.com/images/gone/php:cli "Get your own image badge on microbadger.com")         |
| gone/php:nginx       | AMD64        |     [![Layers](https://img.shields.io/badge/133.72MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:19:33 |       [![](https://images.microbadger.com/badges/image/gone/php:nginx.svg)](https://microbadger.com/images/gone/php:nginx "Get your own image badge on microbadger.com")       |
| gone/php:nginx-8.0   | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/133.65MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:19:02 |   [![](https://images.microbadger.com/badges/image/gone/php:nginx-8.0.svg)](https://microbadger.com/images/gone/php:nginx-8.0 "Get your own image badge on microbadger.com")   |
| gone/php:nginx-7.0   | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/133.31MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:18:58 |   [![](https://images.microbadger.com/badges/image/gone/php:nginx-7.0.svg)](https://microbadger.com/images/gone/php:nginx-7.0 "Get your own image badge on microbadger.com")   |
| gone/php:nginx-7.4   | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/133.72MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:18:52 |   [![](https://images.microbadger.com/badges/image/gone/php:nginx-7.4.svg)](https://microbadger.com/images/gone/php:nginx-7.4 "Get your own image badge on microbadger.com")   |
| gone/php:nginx-7.1   | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/133.55MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:18:16 |   [![](https://images.microbadger.com/badges/image/gone/php:nginx-7.1.svg)](https://microbadger.com/images/gone/php:nginx-7.1 "Get your own image badge on microbadger.com")   |
| gone/php:nginx-7.3   | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/133.95MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:17:53 |   [![](https://images.microbadger.com/badges/image/gone/php:nginx-7.3.svg)](https://microbadger.com/images/gone/php:nginx-7.3 "Get your own image badge on microbadger.com")   |
| gone/php:nginx-7.2   | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/133.96MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:17:45 |   [![](https://images.microbadger.com/badges/image/gone/php:nginx-7.2.svg)](https://microbadger.com/images/gone/php:nginx-7.2 "Get your own image badge on microbadger.com")   |
| gone/php:apache-7.0  | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/126.67MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:17:25 |  [![](https://images.microbadger.com/badges/image/gone/php:apache-7.0.svg)](https://microbadger.com/images/gone/php:apache-7.0 "Get your own image badge on microbadger.com")  |
| gone/php:apache-7.4  | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/127.09MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:17:01 |  [![](https://images.microbadger.com/badges/image/gone/php:apache-7.4.svg)](https://microbadger.com/images/gone/php:apache-7.4 "Get your own image badge on microbadger.com")  |
| gone/php:apache-7.3  | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/127.32MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:16:19 |  [![](https://images.microbadger.com/badges/image/gone/php:apache-7.3.svg)](https://microbadger.com/images/gone/php:apache-7.3 "Get your own image badge on microbadger.com")  |
| gone/php:apache-7.1  | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/126.91MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:16:18 |  [![](https://images.microbadger.com/badges/image/gone/php:apache-7.1.svg)](https://microbadger.com/images/gone/php:apache-7.1 "Get your own image badge on microbadger.com")  |
| gone/php:apache-8.0  | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/127.02MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:16:12 |  [![](https://images.microbadger.com/badges/image/gone/php:apache-8.0.svg)](https://microbadger.com/images/gone/php:apache-8.0 "Get your own image badge on microbadger.com")  |
| gone/php:cli-7.2     | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/123.78MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:15:47 |     [![](https://images.microbadger.com/badges/image/gone/php:cli-7.2.svg)](https://microbadger.com/images/gone/php:cli-7.2 "Get your own image badge on microbadger.com")     |
| gone/php:cli-8.0     | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/123.44MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:14:58 |     [![](https://images.microbadger.com/badges/image/gone/php:cli-8.0.svg)](https://microbadger.com/images/gone/php:cli-8.0 "Get your own image badge on microbadger.com")     |
| gone/php:apache-7.2  | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/127.34MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:14:06 |  [![](https://images.microbadger.com/badges/image/gone/php:apache-7.2.svg)](https://microbadger.com/images/gone/php:apache-7.2 "Get your own image badge on microbadger.com")  |
| gone/php:cli-7.4     | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/123.56MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:13:56 |     [![](https://images.microbadger.com/badges/image/gone/php:cli-7.4.svg)](https://microbadger.com/images/gone/php:cli-7.4 "Get your own image badge on microbadger.com")     |
| gone/php:cli-7.0     | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/123.27MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:13:12 |     [![](https://images.microbadger.com/badges/image/gone/php:cli-7.0.svg)](https://microbadger.com/images/gone/php:cli-7.0 "Get your own image badge on microbadger.com")     |
| gone/php:cli-7.3     | AMD64, ARM64 |     [![Layers](https://img.shields.io/badge/123.80MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:12:44 |     [![](https://images.microbadger.com/badges/image/gone/php:cli-7.3.svg)](https://microbadger.com/images/gone/php:cli-7.3 "Get your own image badge on microbadger.com")     |
| gone/php:cli-7.1     | ARM64, AMD64 |     [![Layers](https://img.shields.io/badge/123.36MB-green.svg)](https://hub.docker.com/r/gone/php) | 2021-02-16 13:12:11 |     [![](https://images.microbadger.com/badges/image/gone/php:cli-7.1.svg)](https://microbadger.com/images/gone/php:cli-7.1 "Get your own image badge on microbadger.com")     |
| gone/php:apache-5.6  | AMD64        |    [![Layers](https://img.shields.io/badge/150.67MB-orange.svg)](https://hub.docker.com/r/gone/php) | 2020-12-01 06:20:02 |  [![](https://images.microbadger.com/badges/image/gone/php:apache-5.6.svg)](https://microbadger.com/images/gone/php:apache-5.6 "Get your own image badge on microbadger.com")  |
| gone/php:nginx-5.6   | AMD64        |    [![Layers](https://img.shields.io/badge/154.13MB-orange.svg)](https://hub.docker.com/r/gone/php) | 2020-12-01 06:18:40 |   [![](https://images.microbadger.com/badges/image/gone/php:nginx-5.6.svg)](https://microbadger.com/images/gone/php:nginx-5.6 "Get your own image badge on microbadger.com")   |
| gone/php:cli-5.6     | AMD64        |     [![Layers](https://img.shields.io/badge/144.93MB-green.svg)](https://hub.docker.com/r/gone/php) | 2020-12-01 06:15:17 |     [![](https://images.microbadger.com/badges/image/gone/php:cli-5.6.svg)](https://microbadger.com/images/gone/php:cli-5.6 "Get your own image badge on microbadger.com")     |
