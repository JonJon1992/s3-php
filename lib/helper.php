<?php

use S3\Lib\Bucket;
use S3\Lib\S3;

if (!function_exists('s3')) {
    function s3($alias = null): Bucket
    {
        return S3::singleton()->bucket($alias);
    }
}