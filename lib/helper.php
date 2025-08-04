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
     * @param bool $useCdn Use CDN if configured
     * @return string
     */
    function s3_url(string $file_path, ?string $alias = null, bool $https = true, bool $useCdn = true): string
    {
        return s3($alias)->getObjectUrl($file_path, $https, $useCdn);
    }
}

if (!function_exists('s3_presigned_url')) {
    /**
     * Get S3 presigned URL
     *
     * @param string $file_path File path
     * @param int $expiration Expiration time in seconds
     * @param string $method HTTP method
     * @param array $options Additional options
     * @param string|null $alias Bucket alias
     * @return string
     */
    function s3_presigned_url(string $file_path, int $expiration = 3600, string $method = 'GET', array $options = [], ?string $alias = null): string
    {
        return s3($alias)->presignedUrl($file_path, $expiration, $method, $options);
    }
}

if (!function_exists('s3_download_url')) {
    /**
     * Get S3 download URL with attachment disposition
     *
     * @param string $file_path File path
     * @param int $expiration Expiration time in seconds
     * @param array $options Additional options
     * @param string|null $alias Bucket alias
     * @return string
     */
    function s3_download_url(string $file_path, int $expiration = 3600, array $options = [], ?string $alias = null): string
    {
        return s3($alias)->getDownloadUrl($file_path, $expiration, $options);
    }
}

if (!function_exists('s3_cdn_url')) {
    /**
     * Get S3 object URL using CDN
     *
     * @param string $file_path File path
     * @param string|null $alias Bucket alias
     * @return string
     */
    function s3_cdn_url(string $file_path, ?string $alias = null): string
    {
        return s3($alias)->getObjectUrl($file_path, true, true);
    }
}

if (!function_exists('s3_direct_url')) {
    /**
     * Get S3 object URL without CDN
     *
     * @param string $file_path File path
     * @param string|null $alias Bucket alias
     * @param bool $https Use HTTPS
     * @return string
     */
    function s3_direct_url(string $file_path, ?string $alias = null, bool $https = true): string
    {
        return s3($alias)->getObjectUrl($file_path, $https, false);
    }
}

if (!function_exists('s3_set_cdn')) {
    /**
     * Set CDN URL for bucket
     *
     * @param string $cdnUrl CDN URL
     * @param string|null $alias Bucket alias
     * @return Bucket
     */
    function s3_set_cdn(string $cdnUrl, ?string $alias = null): Bucket
    {
        return s3($alias)->setCdnUrl($cdnUrl);
    }
}

if (!function_exists('s3_clear_cache')) {
    /**
     * Clear URL cache for bucket
     *
     * @param string|null $alias Bucket alias
     * @return Bucket
     */
    function s3_clear_cache(?string $alias = null): Bucket
    {
        return s3($alias)->clearUrlCache();
    }
}

if (!function_exists('s3_presigned_post')) {
    /**
     * Get S3 presigned POST URL for direct uploads
     *
     * @param string $file_path File path
     * @param int $expiration Expiration time in seconds
     * @param array $conditions Additional conditions
     * @param string|null $alias Bucket alias
     * @return array
     */
    function s3_presigned_post(string $file_path, int $expiration = 3600, array $conditions = [], ?string $alias = null): array
    {
        return s3($alias)->presignedPostUrl($file_path, $expiration, $conditions);
    }
}

if (!function_exists('s3_upload')) {
    /**
     * Upload file to S3
     *
     * @param string $file_path S3 file path
     * @param mixed $content File content
     * @param array $metadata Additional metadata
     * @param string|null $alias Bucket alias
     * @return \Aws\Result
     */
    function s3_upload(string $file_path, $content, array $metadata = [], ?string $alias = null): \Aws\Result
    {
        return s3($alias)->put($file_path, $content, $metadata);
    }
}

if (!function_exists('s3_upload_file')) {
    /**
     * Upload local file to S3
     *
     * @param string $file_path S3 file path
     * @param string $local_path Local file path
     * @param array $metadata Additional metadata
     * @param string|null $alias Bucket alias
     * @return \Aws\Result
     */
    function s3_upload_file(string $file_path, string $local_path, array $metadata = [], ?string $alias = null): \Aws\Result
    {
        return s3($alias)->putFile($file_path, $local_path, $metadata);
    }
}

if (!function_exists('s3_delete')) {
    /**
     * Delete file from S3
     *
     * @param string $file_path S3 file path
     * @param string|null $alias Bucket alias
     * @return \Aws\Result
     */
    function s3_delete(string $file_path, ?string $alias = null): \Aws\Result
    {
        return s3($alias)->delete($file_path);
    }
}

if (!function_exists('s3_get')) {
    /**
     * Get file content from S3
     *
     * @param string $file_path S3 file path
     * @param string|null $alias Bucket alias
     * @return string|null
     */
    function s3_get(string $file_path, ?string $alias = null): ?string
    {
        return s3($alias)->get($file_path);
    }
}

if (!function_exists('s3_metadata')) {
    /**
     * Get file metadata from S3
     *
     * @param string $file_path S3 file path
     * @param string|null $alias Bucket alias
     * @return array|null
     */
    function s3_metadata(string $file_path, ?string $alias = null): ?array
    {
        return s3($alias)->getObjectMetadata($file_path);
    }
}
