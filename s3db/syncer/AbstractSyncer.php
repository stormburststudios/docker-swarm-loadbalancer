<?php

namespace S3DB\Sync;

use Carbon\Carbon;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemReader;
use Monolog\Logger;
use Rych\ByteSize\ByteSize;
use S3DB\Sync\Filesystems\LocalFilesystem;
use S3DB\Sync\Filesystems\StorageFilesystem;
use Spatie\Emoji\Emoji;
use Westsworld\TimeAgo;

abstract class AbstractSyncer
{
    public function __construct(
        protected Logger $logger,
        protected StorageFilesystem $storageFilesystem,
        protected LocalFilesystem $localFilesystem
    ) {
    }

    abstract public function push();

    abstract public function pull();

    protected function download(): string
    {
        $filesInS3 = $this->storageFilesystem->listContents('/')->toArray();
        usort($filesInS3, function (FileAttributes $a, FileAttributes $b) {
            return $a->lastModified() < $b->lastModified();
        });

        $showLimit = 5;
        $this->logger->debug(sprintf(
            '%s Found %d dumps. Showing the last %d',
            Emoji::magnifyingGlassTiltedLeft(),
            count($filesInS3),
            $showLimit
        ));

        /** @var FileAttributes $file */
        foreach (array_slice($filesInS3, 0, $showLimit) as $file) {
            $this->logger->debug(sprintf(
                '%s Found %s. It is %s and was created %s',
                Emoji::magnifyingGlassTiltedLeft(),
                $file->path(),
                ByteSize::formatMetric(
                    $file->fileSize()
                ),
                (new TimeAgo())->inWords((new \DateTime())->setTimestamp($file->lastModified()))
            ));
        }

        // Choose which we're downloadin'
        $latest = $filesInS3[0];
        $this->logger->debug(sprintf(
            '%s  Selecting %s... Downloading %s...',
            Emoji::downArrow(),
            $latest->path(),
            ByteSize::formatMetric($latest->fileSize())
        ));

        $localDownloadedFile = basename($latest->path());
        $this->localFilesystem->writeStream(
            $localDownloadedFile,
            $this->storageFilesystem->readStream(
                $latest->path()
            )
        );

        return $localDownloadedFile;
    }

    protected function upload(string $remoteStorageFile, string $localCompressedDumpFile): void
    {
        $startUpload = microtime(true);
        $this->storageFilesystem->writeStream(
            $remoteStorageFile,
            $this->localFilesystem->readStream($localCompressedDumpFile)
        );
        $this->logger->info(sprintf(
            'Uploaded %s as %s to S3 in %s seconds',
            $localCompressedDumpFile,
            $remoteStorageFile,
            number_format(microtime(true) - $startUpload, 3)
        ));
    }

    protected function cleanup(array $files): void
    {
        $cumulativeBytes = 0;
        foreach ($files as $file) {
            $cumulativeBytes += $this->localFilesystem->fileSize($file);
            $this->localFilesystem->delete($file);
        }
        $this->logger->debug(sprintf(
            '%s  Cleanup: Deleted %d files, freed %s',
            Emoji::wastebasket(),
            count($files),
            ByteSize::formatMetric($cumulativeBytes)
        ));
    }

    protected function compress(string $file): string
    {
        $startCompression = microtime(true);
        passthru(sprintf('xz -f -T0 -6 /dumps/%s', $file));
        $compressedFile = "{$file}.xz";
        $this->logger->debug(sprintf(
            '%s Dump file was made, and is %s compressed in %s seconds',
            Emoji::computerDisk(),
            ByteSize::formatMetric(
                $this->localFilesystem->fileSize($compressedFile)
            ),
            number_format(microtime(true) - $startCompression, 3)
        ));

        return $compressedFile;
    }

    protected function decompress(string $compressedFile): string
    {
        $startDecompression = microtime(true);
        if (!substr($compressedFile, -3, 3) == '.xz') {
            $this->logger->critical(sprintf(
                '%s Compressed file %s does not end in .xz',
                Emoji::explodingHead(),
                $compressedFile
            ));

            exit;
        }
        $uncompressedFile = substr($compressedFile, 0, -3);
        passthru(sprintf('xz -d -f /dumps/%s', $compressedFile));

        $this->logger->debug(sprintf(
            '%s Dump file %s was uncompressed from %s to %s in %s seconds',
            Emoji::computerDisk(),
            $uncompressedFile,
            ByteSize::formatMetric($this->storageFilesystem->fileSize($compressedFile)),
            ByteSize::formatMetric($this->localFilesystem->fileSize($uncompressedFile)),
            number_format(microtime(true) - $startDecompression, 3)
        ));

        return $uncompressedFile;
    }

    protected function checksumCheck($dumpFile): void
    {
        // Checksum dump and don't upload if the checksum is the same as last time.
        $hash = sha1_file("/dumps/{$dumpFile}");
        if ($this->localFilesystem->has('previous_hash') && $hash == $this->localFilesystem->read('previous_hash')) {
            $this->logger->debug(sprintf(
                '%s Dump of %s matches previous dump (%s), not uploading the same file again.',
                Emoji::abacus(),
                $dumpFile,
                substr($hash, 0, 7)
            ));

            exit;
        }
        $this->localFilesystem->write('previous_hash', $hash);
    }

    protected function verifyDumpSucceeded($dumpFile): void
    {
        if (!$this->localFilesystem->fileExists($dumpFile)) {
            $this->logger->critical('Database dump failed');

            exit;
        }
        if (!$this->localFilesystem->fileSize($dumpFile) > 0) {
            $this->logger->critical('Dump file was created, but was empty.');

            exit;
        }
        $this->logger->debug(sprintf(
            'Dump file was made, and is %s uncompressed',
            ByteSize::formatMetric(
                $this->localFilesystem->fileSize($dumpFile)
            )
        ));
    }

    public function prune($dryRun = true) : void {
        $timeAgo = new TimeAgo();
        $buckets = [];

        // Organise each file into buckets
        $allFiles = $this->storageFilesystem->listContents(".",  FilesystemReader::LIST_DEEP)->toArray();
        foreach($allFiles as $file){
            $date = (new Carbon())->setTimestamp($file['lastModified']);
            $buckets[$timeAgo->inWords($date)][$date->format("Y-m-d H:i:s")] = $file;
            ksort($buckets[$timeAgo->inWords($date)]);
        }

        // Sift each bucket to get the newest file...
        $this->logger->debug(sprintf(
            "%s  Sifting %d buckets of %d items...",
            Emoji::beachWithUmbrella(),
            count($buckets),
            count($allFiles)
        ));
        $siftedBuckets = [];
        foreach($buckets as $bucketName => $bucketOptions){
            $siftedBuckets[$bucketName] = reset($bucketOptions);
        }

        // Build a list to save...
        $saveList = [];
        foreach($siftedBuckets as $bucketName => $selectedFile){
            /** @var FileAttributes $selectedFile */
            $saveList[] = $selectedFile->path();
            $this->logger->debug(sprintf(
                "%s Saving %s from %s",
                Emoji::smilingFaceWithHalo(),
                $selectedFile->path(),
                $timeAgo->inWords((new Carbon())->setTimestamp($selectedFile->lastModified()))
            ));
        }
        // Build the culling list
        $cullingList = [];
        foreach($allFiles as $file){
            /** @var FileAttributes $file */
            if(!in_array($file->path(), $saveList)){
                $cullingList[] = $file;
                $this->logger->info(sprintf(
                    " %s  Culling %s from %s",
                    Emoji::recyclingSymbol(),
                    $file->path(),
                    $timeAgo->inWords((new Carbon())->setTimestamp($file->lastModified()))
                ));
            }
        }

        $freedBytes= 0;
        foreach($cullingList as $fileToCull){
            /** @var FileAttributes $fileToCull */
            $freedBytes += $this->storageFilesystem->fileSize($fileToCull->path());
            $this->logger->debug(sprintf(
                "%s Deleting %s saving %s.",
                Emoji::fire(),
                $fileToCull->path(),
                ByteSize::formatMetric($fileToCull->fileSize())
            ));
            if(!$dryRun) {
                $this->storageFilesystem->delete($fileToCull->path());
            }
        }

        $this->logger->info(sprintf(
            " %s Deleted %d files and saved %s disk space",
            Emoji::trumpet(),
            count($cullingList),
            ByteSize::formatMetric($freedBytes)
        ));
    }
}
