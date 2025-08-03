<?php

use S3\Lib\Bucket;
use S3\Lib\S3;

if (!function_exists('s3')) {
    /**
     * Get S3 bucket instance
     * 
     * @param string|null $alias Bucket alias
     * @return Bucket
     * @throws InvalidArgumentException
     */
    function s3(?string $alias = null): Bucket
    {
        return S3::singleton()->bucket($alias);
    }
}

if (!function_exists('s3_config')) {
    /**
     * Configure S3 bucket
     * 
     * @param string $alias Bucket alias
     * @param array $config Configuration array
     * @return S3
     */
    function s3_config(string $alias, array $config): S3
    {
        return S3::singleton()->use($alias, $config);
    }
}

if (!function_exists('s3_exists')) {
    /**
     * Check if S3 object exists
     * 
     * @param string $file_path File path
     * @param string|null $alias Bucket alias
     * @return bool
     */
    function s3_exists(string $file_path, ?string $alias = null): bool
    {
        return s3($alias)->existObject($file_path);
    }
}

if (!function_exists('s3_url')) {
    /**
     * Get S3 object URL
     * 
     * @param string $file_path File path
     * @param string|null $alias Bucket alias
     * @param bool $https Use HTTPS
     * @return string
     */
    function s3_url(string $file_path, ?string $alias = null, bool $https = true): string
    {
        return s3($alias)->getObjectUrl($file_path, $https);
    }
}

if (!function_exists('s3_presigned_url')) {
    /**
     * Get S3 presigned URL
     * 
     * @param string $file_path File path
     * @param int $expiration Expiration time in seconds
     * @param string $method HTTP method
     * @param string|null $alias Bucket alias
     * @return string
     */
    function s3_presigned_url(string $file_path, int $expiration = 3600, string $method = 'GET', ?string $alias = null): string
    {
        return s3($alias)->presignedUrl($file_path, $expiration, $method);
    }
}