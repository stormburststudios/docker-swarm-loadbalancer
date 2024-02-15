<?php

namespace S3DB\Sync\Filesystems;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class LocalFilesystem extends Filesystem
{
    public function __construct()
    {
        $environment = array_merge($_ENV, $_SERVER);
        $localAdapter = new LocalFilesystemAdapter('/dumps/');
        parent::__construct($localAdapter);
    }
}
