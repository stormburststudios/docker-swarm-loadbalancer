<?php

namespace S3DB\Sync;

use Spatie\Emoji\Emoji;

class MysqlSyncer extends AbstractSyncer
{
    public function push(): void
    {
        // Dump file from Postgres
        $dumpFile = 'dump.sql';
        $command = sprintf('mysqldump -u $MARIADB_USER -p$MARIADB_PASSWORD --extended-insert --quick --add-locks --add-drop-database --add-drop-table --add-drop-trigger --skip-dump-date $MARIADB_DATABASE > /dumps/%s', $dumpFile);
        passthru($command);

        // Verify the dump worked
        $this->verifyDumpSucceeded($dumpFile);

        // Checksum dump and don't upload if the checksum is the same as last time.
        $this->checksumCheck($dumpFile);

        // XZ compress dump
        $compressedDumpFile = $this->compress($dumpFile);

        // Upload
        $storageFile = sprintf('s3db-%s.sql.xz', date('Ymd-His'));
        $this->upload($storageFile, $compressedDumpFile);

        // Cleanup
        $this->cleanup([$compressedDumpFile]);
    }

    public function pull(): void
    {
        // Download latest dumpfile
        $localDownloadedFile = $this->download();

        // Decompress
        $localDecompressedFile = $this->decompress($localDownloadedFile);

        // Push into MySQL
        $startImport = microtime(true);
        $command = sprintf('mysql -u $MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE < /dumps/%s', $localDecompressedFile);
        exec($command);
        $this->logger->info(sprintf(
            '%s Imported %s to MySQL in %s seconds',
            Emoji::accordion(),
            $localDecompressedFile,
            number_format(microtime(true) - $startImport, 3)
        ));

        // Cleanup
        $this->cleanup([$localDecompressedFile]);
    }
}
