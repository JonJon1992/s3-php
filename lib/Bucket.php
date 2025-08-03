<?php

namespace S3\Lib;

use Aws\Credentials\Credentials;
use Aws\Result;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;
use Aws\Exception\AwsException;
use Exception;
use InvalidArgumentException;

class Bucket
{
    protected S3Client $client;
    protected string $root = '';
    protected string $bucket;
    protected string $region;
    protected string $access_key;
    protected string $secret_key;
    protected string $url_cdn = '';
    protected ?int $timeOff = null;
    protected array $defaultMetadata = [];

    public function __construct(array $config)
    {
        $this->validateConfig($config);

        $this->bucket = $config['bucket'];
        $this->region = $config['region'];
        $this->access_key = $config['access_key'];
        $this->secret_key = $config['secret_key'];

        $clientConfig = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => new Credentials($this->access_key, $this->secret_key),
            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
            ]
        ];

        // Add endpoint for local development
        if (isset($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
            $clientConfig['use_path_style_endpoint'] = true;
        }

        $this->client = new S3Client($clientConfig);
    }

    protected function validateConfig(array $config): void
    {
        $required = ['bucket', 'region', 'access_key', 'secret_key'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new InvalidArgumentException("Missing required configuration: {$field}");
            }
        }
    }

    public function getClient(): S3Client
    {
        return $this->client;
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getAccessKey(): string
    {
        return $this->access_key;
    }

    public function getSecretKey(): string
    {
        return $this->secret_key;
    }

    public function setDefaultMetadata(array $metadata): self
    {
        $this->defaultMetadata = $metadata;
        return $this;
    }

    public function getDefaultMetadata(): array
    {
        return $this->defaultMetadata;
    }

    public function isExistBucket(): bool
    {
        try {
            return $this->client->doesBucketExist($this->bucket);
        } catch (Exception $e) {
            return false;
        }
    }

    public function createBucket(string $acl = S3::ACL_PRIVATE): Result
    {
        $params = [
            'Bucket' => $this->bucket,
            'ACL' => $acl
        ];

        if ($this->region !== 'us-east-1') {
            $params['CreateBucketConfiguration'] = [
                'LocationConstraint' => $this->region
            ];
        }

        return $this->client->createBucket($params);
    }

    public function deleteBucket(bool $force = false): Result
    {
        if ($force) {
            // Delete all objects first
            $objects = $this->client->listObjects(['Bucket' => $this->bucket]);
            if ($objects['Contents']) {
                $deleteParams = [
                    'Bucket' => $this->bucket,
                    'Delete' => [
                        'Objects' => array_map(function ($obj) {
                            return ['Key' => $obj['Key']];
                        }, $objects['Contents'])
                    ]
                ];
                $this->client->deleteObjects($deleteParams);
            }
        }

        return $this->client->deleteBucket(['Bucket' => $this->bucket]);
    }

    public function locationBucket(): Result
    {
        return $this->client->getBucketLocation(['Bucket' => $this->bucket]);
    }

    public function root(?string $root = null): self|string
    {
        if ($root === null) {
            return $this->root;
        }

        $root = trim($root);
        if (str_starts_with($root, '/')) {
            $root = substr($root, 1);
        }
        if (str_ends_with($root, '/')) {
            $root = substr($root, 0, -1);
        }

        $this->root = $root;
        return $this;
    }

    public function get(string $file_path): ?string
    {
        $data = $this->getObject($this->path($file_path));
        if (!$data) {
            return null;
        }
        return $data['Body']->getContents();
    }

    public function getStream(string $file_path)
    {
        $data = $this->getObject($this->path($file_path));
        if (!$data) {
            return null;
        }
        return $data['Body'];
    }

    public function path(string $path, bool $bucket = false): string
    {
        $path = trim($path);

        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }

        if ($this->root !== '') {
            $path = $this->root . '/' . $path;
        }

        if ($bucket) {
            $path = $this->bucket . '/' . $path;
        }

        return $path;
    }

    public function getObject(string $file_path): ?Result
    {
        try {
            return $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $file_path
            ]);
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'NoSuchKey') {
                return null;
            }
            throw $e;
        }
    }

    public function existObject(string $file_path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $this->path($file_path));
        } catch (Exception $e) {
            return false;
        }
    }

    public function getFiles(string $path = '', bool $recursive = true): array
    {
        $files = [];
        $prefix = rtrim($path, '/') . '/';

        $params = [
            'Bucket' => $this->bucket,
            'Prefix' => $prefix
        ];

        if (!$recursive) {
            $params['Delimiter'] = '/';
        }

        $paginator = $this->client->getPaginator('ListObjectsV2', $params);

        foreach ($paginator as $page) {
            if (isset($page['Contents'])) {
                foreach ($page['Contents'] as $object) {
                    $key = $object['Key'];
                    if ($key !== $prefix) {
                        $files[] = str_replace($prefix, '', $key);
                    }
                }
            }

            if (!$recursive && isset($page['CommonPrefixes'])) {
                foreach ($page['CommonPrefixes'] as $prefix) {
                    $files[] = rtrim(str_replace($prefix, '', $prefix['Prefix']), '/');
                }
            }
        }

        return array_filter($files);
    }

    public function put(string $file_path, $file_content, array $metadata = []): Result
    {
        $object = [
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path),
            'Body' => $file_content,
            'ACL' => S3::ACL_PUBLIC_READ
        ];

        // Merge default metadata
        $metadata = array_merge($this->defaultMetadata, $metadata);

        if (!empty($metadata)) {
            $object = array_merge($object, $metadata);
        }

        return $this->client->putObject($object);
    }

    public function putFile(string $file_path, string $file_path_local, array $metadata = []): Result
    {
        if (!file_exists($file_path_local)) {
            throw new InvalidArgumentException("Local file not found: {$file_path_local}");
        }

        $contentType = mime_content_type($file_path_local);
        $metadata['Content-Type'] = $contentType;

        return $this->put($file_path, file_get_contents($file_path_local), $metadata);
    }

    public function putFileStream(string $file_path, string $file_path_local, array $metadata = []): Result
    {
        if (!file_exists($file_path_local)) {
            throw new InvalidArgumentException("Local file not found: {$file_path_local}");
        }

        $contentType = mime_content_type($file_path_local);
        $metadata['Content-Type'] = $contentType;

        return $this->put($file_path, fopen($file_path_local, 'r'), $metadata);
    }

    public function uploadLargeFile(string $file_path, string $file_path_local, array $metadata = []): Result
    {
        if (!file_exists($file_path_local)) {
            throw new InvalidArgumentException("Local file not found: {$file_path_local}");
        }

        $contentType = mime_content_type($file_path_local);
        $metadata['Content-Type'] = $contentType;

        $uploader = new MultipartUploader($this->client, $file_path_local, [
            'bucket' => $this->bucket,
            'key' => $this->path($file_path),
            'acl' => S3::ACL_PUBLIC_READ,
            'metadata' => $metadata
        ]);

        return $uploader->upload();
    }

    public function copy(string $source_path, string $destination_path, array $metadata = []): Result
    {
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $this->path($destination_path),
            'CopySource' => $this->bucket . '/' . $this->path($source_path),
            'ACL' => S3::ACL_PUBLIC_READ
        ];

        if (!empty($metadata)) {
            $params['Metadata'] = $metadata;
            $params['MetadataDirective'] = 'REPLACE';
        }

        return $this->client->copyObject($params);
    }

    public function move(string $source_path, string $destination_path, array $metadata = []): Result
    {
        $result = $this->copy($source_path, $destination_path, $metadata);
        $this->delete($source_path);
        return $result;
    }

    public function delete(string $file_path): Result
    {
        return $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path)
        ]);
    }

    public function deleteMultiple(array $file_paths): Result
    {
        $objects = array_map(function ($path) {
            return ['Key' => $this->path($path)];
        }, $file_paths);

        return $this->client->deleteObjects([
            'Bucket' => $this->bucket,
            'Delete' => [
                'Objects' => $objects
            ]
        ]);
    }

    public function getObjectMetadata(string $file_path): ?array
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->path($file_path)
            ]);

            return [
                'ContentType' => $result['ContentType'] ?? null,
                'ContentLength' => $result['ContentLength'] ?? null,
                'LastModified' => $result['LastModified'] ?? null,
                'ETag' => $result['ETag'] ?? null,
                'Metadata' => $result['Metadata'] ?? []
            ];
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'NotFound') {
                return null;
            }
            throw $e;
        }
    }

    public function setObjectAcl(string $file_path, string $acl): Result
    {
        return $this->client->putObjectAcl([
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path),
            'ACL' => $acl
        ]);
    }

    public function getObjectAcl(string $file_path): Result
    {
        return $this->client->getObjectAcl([
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path)
        ]);
    }

    public function endpoint(bool $https = true): string
    {
        $scheme = $https ? 'https' : 'http';
        return "{$scheme}://{$this->bucket}.s3.{$this->region}.amazonaws.com";
    }

    public function getObjectUrl(string $file_path, bool $https = true): string
    {
        return $this->endpoint($https) . '/' . $this->path($file_path);
    }

    public function time(): int
    {
        if ($this->timeOff === null) {
            $this->calcTimeOffset();
        }
        return time() + $this->timeOff;
    }

    protected function calcTimeOffset(): void
    {
        try {
            $data = $this->locationBucket();
            $timeSys = time();
            $timeAWS = strtotime($data['@metadata']['headers']['date']);
            $this->timeOff = $timeAWS - $timeSys;
        } catch (Exception $e) {
            $this->timeOff = 0;
        }
    }

    public function fileUrl(string $file_path, int $exp = 3600, bool $https = true): string
    {
        $file_path = $this->path(str_replace(['%2F', '%2B'], ['/', '+'], rawurlencode($file_path)));
        $url = $this->endpoint($https);
        $key = $this->access_key;
        $exp = $this->time() + $exp;

        $signature = urlencode($this->hash("GET\n\n\n{$exp}\n/{$this->bucket}/{$file_path}"));

        return sprintf('%s/%s?AWSAccessKeyId=%s&Expires=%u&Signature=%s', $url, $file_path, $key, $exp, $signature);
    }

    public function presignedUrl(string $file_path, int $exp = 3600, string $method = 'GET'): string
    {
        $cmd = $this->client->getCommand($method, [
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path)
        ]);

        $request = $this->client->createPresignedRequest($cmd, "+{$exp} seconds");
        return (string) $request->getUri();
    }

    protected function hash(string $string): string
    {
        if (extension_loaded('hash')) {
            $string = hash_hmac('sha1', $string, $this->secret_key, true);
        } else {
            $secret = str_pad($this->secret_key, 64, chr(0x00));
            $string = pack('H*', sha1(($secret ^ (str_repeat(chr(0x36), 64))) . $string));
            $string = pack('H*', sha1(($secret ^ (str_repeat(chr(0x5c), 64))) . $string));
        }
        return base64_encode($string);
    }

    public function getBucketSize(): int
    {
        $size = 0;
        $paginator = $this->client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->bucket
        ]);

        foreach ($paginator as $page) {
            if (isset($page['Contents'])) {
                foreach ($page['Contents'] as $object) {
                    $size += $object['Size'];
                }
            }
        }

        return $size;
    }

    public function getBucketObjectCount(): int
    {
        $count = 0;
        $paginator = $this->client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->bucket
        ]);

        foreach ($paginator as $page) {
            if (isset($page['Contents'])) {
                $count += count($page['Contents']);
            }
        }

        return $count;
    }
}
