<?php

declare(strict_types=1);

namespace Bouncer;

enum EnumCertType
{
    case NO_CERT;

    case LETSENCRYPT_CERT;

    case TEMPORARY_CERT;

    case GLOBAL_CERT;

    case CUSTOM_CERT;
}
