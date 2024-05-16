<?php

declare(strict_types=1);

namespace Bouncer\Logger\Formatter;

use Bouncer\Settings\Settings;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Bramus\Monolog\Formatter\ColorSchemes\TrafficLight;

class ColourLine extends ColoredLineFormatter
{
    public function __construct(private readonly Settings $settings)
    {
        parent::__construct(
            new TrafficLight(),
            $this->settings->get('logger/line_format'),
            'G:i',
        );
        $this->setMaxLevelNameLength($settings->get('logger/max_level_name_length'));
    }
}
