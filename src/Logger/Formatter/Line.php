<?php

declare(strict_types=1);

namespace Bouncer\Logger\Formatter;

use Monolog\Formatter\LineFormatter;
use Bouncer\Settings\Settings;

class Line extends LineFormatter
{
    public function __construct(private readonly Settings $settings)
    {
        parent::__construct(
            $this->settings->get('logger/line_format'),
            'G:i',
        );
        $this->setMaxLevelNameLength($settings->get('logger/max_level_name_length'));
    }
}
