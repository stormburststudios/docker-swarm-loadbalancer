<?php

namespace S3DB\Sync\Filesystems;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

class StorageFilesystem extends Filesystem
{
    public function __construct()
    {
        $environment = array_merge($_ENV, $_SERVER);
        $s3Adapter = new AwsS3V3Adapter(
            new S3Client(array_filter([
                'endpoint' => $environment['S3_ENDPOINT'] ?? null,
                'use_path_style_endpoint' => isset($environment['S3_USE_PATH_STYLE_ENDPOINT']),
                'credentials' => [
                    'key' => $environment['S3_API_KEY'],
                    'secret' => $environment['S3_API_SECRET'],
                ],
                'region' => $environment['S3_REGION'] ?? 'us-east',
                'version' => 'latest',
            ])),
            $environment['S3_BUCKET'],
            $environment['S3_PREFIX'] ?? null
        );
        parent::__construct($s3Adapter);
    }
}
