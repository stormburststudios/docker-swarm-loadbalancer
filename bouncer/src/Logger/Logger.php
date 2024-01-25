<?php

declare(strict_types=1);

namespace Bouncer\Logger;

use Bouncer\Settings\Settings;
use Monolog\Processor;

class Logger extends \Monolog\Logger
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Processor\ProcessIdProcessor $processIdProcessor,
        private readonly Processor\MemoryPeakUsageProcessor $memoryPeakUsageProcessor,
        private readonly Processor\PsrLogMessageProcessor $psrLogMessageProcessor,
        private readonly Formatter\ColourLine $coloredLineFormatter,
        private readonly Formatter\Line $lineFormatter,
    ) {
        parent::__construct('Bouncer');

        $this
            ->pushProcessor($this->processIdProcessor)
            ->pushProcessor($this->memoryPeakUsageProcessor)
            ->pushProcessor($this->psrLogMessageProcessor)
        ;

        $this->pushHandler(
            (new Handlers\Cli('php://stdout', $this->settings->get('logger/level')))
                ->setFormatter($this->settings->get('logger/coloured_output') ? $this->coloredLineFormatter : $this->lineFormatter)
        );

        $this->pushHandler(
            (new Handlers\File($this->settings->get('logger/path'), $this->settings->get('logger/level')))
                ->setFormatter($this->lineFormatter)
        );
    }
}
