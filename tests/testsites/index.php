<?php

declare(strict_types=1);
$environment = array_merge($_ENV, $_SERVER);
$site        = $environment['SITE_NAME']   ?? 'unknown';
$server      = $environment['SERVER_NAME'] ?? gethostname();
printf('<h1>Website %s</h1><p>Running on %s</p>', $site, $server);
