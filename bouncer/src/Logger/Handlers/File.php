<?php

declare(strict_types=1);

namespace Bouncer\Logger\Handlers;

use Monolog\Handler\StreamHandler;

class File extends StreamHandler
{
    public function withUri($uri)
    {
        $this->url    = $uri;
        $this->stream = null;

        return $this;
    }
}
