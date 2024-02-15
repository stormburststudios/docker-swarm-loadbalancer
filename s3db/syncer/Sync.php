<?php

namespace S3DB\Sync;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Garden\Cli\Args;
use Garden\Cli\Cli;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use S3DB\Sync\Filesystems\LocalFilesystem;
use S3DB\Sync\Filesystems\StorageFilesystem;
use Spatie\Emoji\Emoji;

class Sync
{
    protected Logger $logger;
    protected Cli $cli;
    protected Args $args;
    protected AbstractSyncer $syncer;
    protected StorageFilesystem $storageFilesystem;
    protected LocalFilesystem $localFilesystem;

    public function __construct(
    ) {
        $environment = array_merge($_ENV, $_SERVER);
        ksort($environment);

        $this->cli = new Cli();
        $this->cli->opt('postgres', 'postgres mode')
            ->opt('mysql', 'mysql mode')
            ->opt('push', 'push to s3')
            ->opt('pull', 'pull from s3')
            ->opt('prune', 'comb and prune the s3 bucket backups to reduce storage mass')
            ->opt('dry-run', 'do not actually delete things')
        ;
        $this->args = $this->cli->parse($environment['argv'], true);

        $this->logger = new Logger('syncer');
        $this->logger->pushHandler(new StreamHandler('/var/log/syncer.log', Logger::DEBUG));
        $stdout = new StreamHandler('php://stdout', Logger::DEBUG);
        $stdout->setFormatter(new ColoredLineFormatter(null, "%level_name%: %message% \n"));
        $this->logger->pushHandler($stdout);

        $this->storageFilesystem = new StorageFilesystem();
        $this->localFilesystem = new LocalFilesystem();

        if(!isset($environment['S3_API_KEY']) || !isset($environment['S3_API_SECRET'])){
            $this->logger->warning(sprintf('%s S3_API_KEY/S3_API_SECRET missing, so running in non-storing mode like a normal database.', Emoji::CHARACTER_NERD_FACE));
            sleep(60);
            exit;
        }

        if ($this->args->hasOpt('postgres') || isset($environment['PG_VERSION'])) {
            // Postgres mode is enabled if --postgres is set, or PG_VERSION envvar is set,
            // which it is when we're built ontop of the postgres docker container
            $this->logger->debug(sprintf('%s Starting in postgres mode', Emoji::CHARACTER_HOURGLASS_NOT_DONE));
            $this->syncer = new PostgresSyncer($this->logger, $this->storageFilesystem, $this->localFilesystem);
        } elseif ($this->args->hasOpt('mysql') || isset($environment['MARIADB_VERSION'])) {
            $this->logger->debug(sprintf('%s Starting in mysql mode', Emoji::CHARACTER_HOURGLASS_NOT_DONE));
            $this->syncer = new MysqlSyncer($this->logger, $this->storageFilesystem, $this->localFilesystem);
        } else {
            $this->logger->critical(sprintf('%s Must be started in either --mysql or --postgres mode!', Emoji::CHARACTER_NERD_FACE));

            exit;
        }
    }

    public function run(): void
    {
        if ($this->args->hasOpt('push')) {
            $this->logger->info(sprintf(' %s  Running push', Emoji::upArrow()));
            $this->syncer->push();
        } elseif ($this->args->hasOpt('pull')) {
            $this->logger->info(sprintf(' %s  Running pull', Emoji::downArrow()));
            $this->syncer->pull();
        } elseif ($this->args->hasOpt('prune')) {
            $this->logger->info(sprintf(' %s  Running pruner', Emoji::recyclingSymbol()));
            $this->syncer->prune($this->args->hasOpt('dry-run'));
        } else {
            $this->logger->critical(sprintf('%s Must be run in either --push or --pull mode!', Emoji::CHARACTER_NERD_FACE));

            exit;
        }
    }
}
