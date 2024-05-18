<?php

declare(strict_types=1);

namespace Bouncer\Logger;

use PhpStaticAnalysis\Attributes\Type;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use Monolog\DateTimeImmutable;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\ResettableInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractLogger implements LoggerInterface, ResettableInterface
{
    protected array $handlers;
    protected bool $microsecondTimestamps = true;
    protected \DateTimeZone $timezone;
    protected ?\Closure $exceptionHandler = null;

    /**
     * Keeps track of depth to prevent infinite logging loops.
     */
    private int $logDepth = 0;

    #[Type('\WeakMap<\Fiber<mixed, mixed, mixed, mixed>, int>')] // Keeps track of depth inside fibers to prevent infinite logging loops
    private \WeakMap $fiberLogDepth;

    /**
     * Whether to detect infinite logging loops
     * This can be disabled via {@see useLoggingLoopDetection} if you have async handlers that do not play well with this.
     */
    private bool $detectCycles = true;

    #[Param(name: 'string')] // The logging channel, a simple descriptive name that is attached to all log records
    #[Param(handlers: 'HandlerInterface[]')] // optional stack of handlers, the first one in the array is called first, etc
    #[Param(processors: 'callable[]')] // Optional array of processors
    #[Param(timezone: 'null|\DateTimeZone')] // Optional timezone, if not provided date_default_timezone_get() will be used
    #[Param(processors: 'array<(callable(LogRecord):LogRecord | ProcessorInterface)>')]
    public function __construct(protected string $name, array $handlers = [], #[Type('array<(callable(LogRecord):LogRecord | ProcessorInterface)>')]
        protected array $processors = [], ?\DateTimeZone $timezone = null)
    {
        $this->setHandlers($handlers);
        $this->timezone      = $timezone ?? new \DateTimeZone(date_default_timezone_get());
        $this->fiberLogDepth = new \WeakMap();
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return a new cloned instance with the name changed.
     */
    #[Returns('static')]
    public function withName(string $name): self
    {
        $new       = clone $this;
        $new->name = $name;

        return $new;
    }

    public function pushHandler(HandlerInterface $handler): self
    {
        array_unshift($this->handlers, $handler);

        return $this;
    }

    public function setHandlers(array $handlers): self
    {
        $this->handlers = [];
        foreach (array_reverse($handlers) as $handler) {
            $this->pushHandler($handler);
        }

        return $this;
    }

    #[Param(callback: 'ProcessorInterface|callable(LogRecord):LogRecord')]
    #[Returns('$this')]
    public function pushProcessor(callable | ProcessorInterface $callback): self
    {
        array_unshift($this->processors, $callback);

        return $this;
    }

    #[Returns('$this')]
    public function useLoggingLoopDetection(bool $detectCycles): self
    {
        $this->detectCycles = $detectCycles;

        return $this;
    }

    /**
     * Adds a log record.
     */
    #[Param(level: 'Level')] // The logging level (a Monolog or RFC 5424 level)
    #[Param(message: 'string')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    #[Param(datetime: 'null|DateTimeImmutable')] // Optional log date to log into the past or future
    #[Returns('bool')] // Whether the record has been processed
    #[Param(level: 'value-of<Level::VALUES>|Level')]
    public function addRecord(Level $level, string $message, array $context = [], ?DateTimeImmutable $datetime = null): bool
    {
        if ($this->detectCycles) {
            if (($fiber = \Fiber::getCurrent()) instanceof \Fiber) {
                $logDepth = $this->fiberLogDepth[$fiber] = ($this->fiberLogDepth[$fiber] ?? 0) + 1;
            } else {
                $logDepth = ++$this->logDepth;
            }
        } else {
            $logDepth = 0;
        }

        if ($logDepth === 3) {
            $this->warning('A possible infinite logging loop was detected and aborted. It appears some of your handler code is triggering logging, see the previous log record for a hint as to what may be the cause.');

            return false;
        }
        if ($logDepth >= 5) { // log depth 4 is let through, so we can log the warning above
            return false;
        }

        $trace   = debug_backtrace();
        $context = array_merge(
            [
                'file' => basename($trace[1]['file']),
                'line' => $trace[1]['line'],
                'pid'  => getmypid(),
            ],
            $context
        );

        try {
            $recordInitialized = $this->processors === [];

            $record = new LogRecord(
                datetime: $datetime ?? new DateTimeImmutable($this->microsecondTimestamps, $this->timezone),
                channel: $this->name,
                level: $level,
                message: $message,
                context: $context,
                extra: [],
            );
            $handled = false;

            foreach ($this->handlers as $handler) {
                if (false === $recordInitialized) {
                    // skip initializing the record as long as no handler is going to handle it
                    if (!$handler->isHandling($record)) {
                        continue;
                    }

                    try {
                        foreach ($this->processors as $processor) {
                            $record = $processor($record);
                        }
                        $recordInitialized = true;
                    } catch (\Throwable $e) {
                        $this->handleException($e, $record);

                        return true;
                    }
                }

                // once the record is initialized, send it to all handlers as long as the bubbling chain is not interrupted
                try {
                    $handled = true;
                    if (true === $handler->handle(clone $record)) {
                        break;
                    }
                } catch (\Throwable $e) {
                    $this->handleException($e, $record);

                    return true;
                }
            }

            return $handled;
        } finally {
            if ($this->detectCycles) {
                if (isset($fiber)) {
                    --$this->fiberLogDepth[$fiber];
                } else {
                    --$this->logDepth;
                }
            }
        }
    }

    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }

    public function reset(): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof ResettableInterface) {
                $handler->reset();
            }
        }

        foreach ($this->processors as $processor) {
            if ($processor instanceof ResettableInterface) {
                $processor->reset();
            }
        }
    }

    /**
     * Checks whether the Logger has a handler that listens on the given level.
     */
    #[Param(level: 'Level')]
    public function isHandling(Level $level): bool
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable($this->microsecondTimestamps, $this->timezone),
            channel: $this->name,
            message: '',
            level: $level,
        );

        foreach ($this->handlers as $handler) {
            if ($handler->isHandling($record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a log record at an arbitrary level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(level: 'mixed')] // The log level (a Monolog, PSR-3 or RFC 5424 level)
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    #[Param(level: 'Level|LogLevel::*')]
    public function log($level, string | \Stringable $message, array $context = []): void
    {
        if (!$level instanceof Level) {
            $level = Level::Critical;
        }

        $this->addRecord($level, "A Level that wasn't valid was used to write to a Logger: {level}", ['level' => $level]);
        $this->addRecord($level, (string) $message, $context);
    }

    /**
     * Adds a log record at the DEBUG level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function debug(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Debug, (string) $message, $context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function info(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Info, (string) $message, $context);
    }

    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function notice(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Notice, (string) $message, $context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function warning(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Warning, (string) $message, $context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function error(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Error, (string) $message, $context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function critical(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Critical, (string) $message, $context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function alert(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Alert, (string) $message, $context);
    }

    /**
     * Adds a log record at the EMERGENCY level.
     *
     * This method allows for compatibility with common interfaces.
     */
    #[Param(message: 'string|\Stringable')] // The log message
    #[Param(context: 'mixed[]')] // The log context
    public function emergency(string | \Stringable $message, array $context = []): void
    {
        $this->addRecord(Level::Emergency, (string) $message, $context);
    }

    /**
     * Sets the timezone to be used for the timestamp of log records.
     */
    #[Returns('$this')]
    public function setTimezone(\DateTimeZone $tz): self
    {
        $this->timezone = $tz;

        return $this;
    }

    /**
     * Returns the timezone to be used for the timestamp of log records.
     */
    public function getTimezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * Delegates exception management to the custom exception handler,
     * or throws the exception if no custom handler is set.
     */
    protected function handleException(\Throwable $e, LogRecord $record): void
    {
        if (!$this->exceptionHandler instanceof \Closure) {
            throw $e;
        }

        ($this->exceptionHandler)($e, $record);
    }

    #[Returns('array<string, mixed>')]
    public function __serialize(): array
    {
        return [
            'name'                  => $this->name,
            'handlers'              => $this->handlers,
            'processors'            => $this->processors,
            'microsecondTimestamps' => $this->microsecondTimestamps,
            'timezone'              => $this->timezone,
            'exceptionHandler'      => $this->exceptionHandler,
            'logDepth'              => $this->logDepth,
            'detectCycles'          => $this->detectCycles,
        ];
    }

    #[Param(data: 'array<string, mixed>')]
    public function __unserialize(array $data): void
    {
        foreach (['name', 'handlers', 'processors', 'microsecondTimestamps', 'timezone', 'exceptionHandler', 'logDepth', 'detectCycles'] as $property) {
            if (isset($data[$property])) {
                $this->{$property} = $data[$property];
            }
        }

        $this->fiberLogDepth = new \WeakMap();
    }
}
