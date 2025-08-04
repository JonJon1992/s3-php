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
    protected array $urlCache = [];
    protected array $endpointConfig = [];
    protected bool $usePathStyle = false;
    protected bool $useAccelerate = false;

    public function __construct(array $config)
    {
        $this->validateConfig($config);

        $this->bucket = $config['bucket'];
        $this->region = $config['region'];
        $this->access_key = $config['access_key'];
        $this->secret_key = $config['secret_key'];

        // Configurações de endpoint
        $this->url_cdn = $config['url_cdn'] ?? '';
        $this->usePathStyle = $config['use_path_style'] ?? false;
        $this->useAccelerate = $config['use_accelerate'] ?? false;

        $clientConfig = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => new Credentials($this->access_key, $this->secret_key),
            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
            ]
        ];

        // Configuração de endpoint dinâmica
        $this->configureEndpoint($clientConfig, $config);

        $this->client = new S3Client($clientConfig);
    }

    protected function configureEndpoint(array &$clientConfig, array $config): void
    {
        // Endpoint customizado para desenvolvimento local
        if (isset($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
            $clientConfig['use_path_style_endpoint'] = true;
            $this->usePathStyle = true;
            $this->endpointConfig = [
                'type' => 'custom',
                'url' => $config['endpoint'],
                'use_path_style' => true
            ];
            return;
        }

        // S3 Transfer Acceleration
        if ($this->useAccelerate) {
            $clientConfig['use_accelerate_endpoint'] = true;
            $this->endpointConfig = [
                'type' => 'accelerate',
                'url' => "https://{$this->bucket}.s3-accelerate.amazonaws.com"
            ];
            return;
        }

        // Endpoint padrão baseado na região
        $endpoint = $this->getDefaultEndpoint();
        $clientConfig['endpoint'] = $endpoint;
        $this->endpointConfig = [
            'type' => 'standard',
            'url' => $endpoint,
            'use_path_style' => $this->usePathStyle
        ];
    }

    protected function getDefaultEndpoint(): string
    {
        // Endpoints específicos por região para melhor performance
        $endpoints = [
            'us-east-1' => 'https://s3.amazonaws.com',
            'us-east-2' => 'https://s3.us-east-2.amazonaws.com',
            'us-west-1' => 'https://s3.us-west-1.amazonaws.com',
            'us-west-2' => 'https://s3.us-west-2.amazonaws.com',
            'sa-east-1' => 'https://s3.sa-east-1.amazonaws.com',
            'eu-west-1' => 'https://s3.eu-west-1.amazonaws.com',
            'eu-central-1' => 'https://s3.eu-central-1.amazonaws.com',
            'ap-southeast-1' => 'https://s3.ap-southeast-1.amazonaws.com',
            'ap-southeast-2' => 'https://s3.ap-southeast-2.amazonaws.com',
            'ap-northeast-1' => 'https://s3.ap-northeast-1.amazonaws.com',
        ];

        return $endpoints[$this->region] ?? "https://s3.{$this->region}.amazonaws.com";
    }

    protected function validateConfig(array $config): void
    {
        $required = ['bucket', 'region', 'access_key', 'secret_key'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new InvalidArgumentException("Missing required configuration: {$field}");
            }
        }

        // Validar formato do bucket usando match expression (PHP 8.0+)
        $bucketPattern = '/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/';
        if (!preg_match($bucketPattern, $config['bucket'])) {
            throw new InvalidArgumentException("Invalid bucket name format");
        }

        // Validar região usando match expression (PHP 8.0+)
        $regionPattern = '/^[a-z0-9-]+$/';
        if (!preg_match($regionPattern, $config['region'])) {
            throw new InvalidArgumentException("Invalid region format");
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

    public function setCdnUrl(string $cdnUrl): self
    {
        $this->url_cdn = rtrim($cdnUrl, '/');
        return $this;
    }

    public function getCdnUrl(): string
    {
        return $this->url_cdn;
    }

    public function clearUrlCache(): self
    {
        $this->urlCache = [];
        return $this;
    }

    public function isExistBucket(): bool
    {
        try {
            return $this->client->doesBucketExistV2($this->bucket, false);
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
                        'Objects' => array_map(fn($obj) => ['Key' => $obj['Key']], $objects['Contents'])
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
        // Usar str_starts_with e str_ends_with (PHP 8.0+)
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

    public function getStream(string $file_path): mixed
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

        // Usar str_starts_with e str_ends_with (PHP 8.0+)
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
            return $this->client->doesObjectExistV2($this->bucket, $this->path($file_path), false);
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
                foreach ($page['CommonPrefixes'] as $commonPrefix) {
                    $files[] = rtrim(str_replace($prefix, '', $commonPrefix['Prefix']), '/');
                }
            }
        }

        // Usar array_filter sem callback para remover valores vazios (PHP 8.0+)
        return array_values(array_filter($files));
    }

    public function put(string $file_path, mixed $file_content, array $metadata = []): Result
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
        // Usar arrow function (PHP 7.4+) para melhor performance
        $objects = array_map(fn($path) => ['Key' => $this->path($path)], $file_paths);

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
        // Usar CDN se configurado
        if (!empty($this->url_cdn)) {
            return $this->url_cdn;
        }

        $scheme = $https ? 'https' : 'http';

        // Endpoint customizado
        if ($this->endpointConfig['type'] === 'custom') {
            return $this->endpointConfig['url'];
        }

        // S3 Transfer Acceleration
        if ($this->endpointConfig['type'] === 'accelerate') {
            return $this->endpointConfig['url'];
        }

        // Endpoint padrão
        if ($this->usePathStyle) {
            return "{$scheme}://s3.{$this->region}.amazonaws.com/{$this->bucket}";
        }

        return "{$scheme}://{$this->bucket}.s3.{$this->region}.amazonaws.com";
    }

    public function getObjectUrl(string $file_path, bool $https = true, bool $useCdn = true): string
    {
        $cacheKey = "url_{$file_path}_{$https}_{$useCdn}";

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $file_path = $this->path($file_path);
        $baseUrl = $this->endpoint($https);

        // Usar CDN se configurado e solicitado
        if ($useCdn && !empty($this->url_cdn)) {
            $url = $this->url_cdn . '/' . $file_path;
        } else {
            $url = $baseUrl . '/' . $file_path;
        }

        // Validar e sanitizar URL
        $url = $this->sanitizeUrl($url);

        // Cache da URL
        $this->urlCache[$cacheKey] = $url;

        return $url;
    }

    public function getDownloadUrl(string $file_path, int $expiration = 3600, array $options = []): string
    {
        $cacheKey = "download_{$file_path}_{$expiration}_" . md5(serialize($options));

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $defaultOptions = [
            'response_content_disposition' => 'attachment',
            'response_content_type' => 'application/octet-stream',
            'response_cache_control' => 'no-cache'
        ];

        $options = array_merge($defaultOptions, $options);

        $cmd = $this->client->getCommand('GetObject', array_merge([
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path)
        ], $options));

        $request = $this->client->createPresignedRequest($cmd, "+{$expiration} seconds");
        $url = (string) $request->getUri();

        // Cache da URL
        $this->urlCache[$cacheKey] = $url;

        return $url;
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

    public function presignedUrl(string $file_path, int $exp = 3600, string $method = 'GET', array $options = []): string
    {
        $cacheKey = "presigned_{$file_path}_{$exp}_{$method}_" . md5(serialize($options));

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $params = [
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path)
        ];

        // Adicionar opções customizadas
        if (!empty($options)) {
            $params = array_merge($params, $options);
        }

        $cmd = $this->client->getCommand($method, $params);
        $request = $this->client->createPresignedRequest($cmd, "+{$exp} seconds");
        $url = (string) $request->getUri();

        // Cache da URL
        $this->urlCache[$cacheKey] = $url;

        return $url;
    }

    public function presignedPostUrl(string $file_path, int $exp = 3600, array $conditions = []): array
    {
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path),
            'Expires' => time() + $exp
        ];

        if (!empty($conditions)) {
            $params['Conditions'] = $conditions;
        }

        $cmd = $this->client->getCommand('PostObject', $params);
        $request = $this->client->createPresignedRequest($cmd, "+{$exp} seconds");

        return [
            'url' => (string) $request->getUri(),
            'fields' => $request->getBody()->getContents()
        ];
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

    protected function sanitizeUrl(string $url): string
    {
        // Remover caracteres inválidos
        $sanitizedUrl = filter_var($url, FILTER_SANITIZE_URL);

        // Validar URL
        if ($sanitizedUrl === false || !filter_var($sanitizedUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL generated: {$url}");
        }

        return $sanitizedUrl;
    }

    public function getBucketSize(): int
    {
        $size = 0;
        $paginator = $this->client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->bucket
        ]);

        foreach ($paginator as $page) {
            if (isset($page['Contents'])) {
                // Usar array_sum com array_column para melhor performance (PHP 7.0+)
                $size += array_sum(array_column($page['Contents'], 'Size'));
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

    public function getEndpointConfig(): array
    {
        return $this->endpointConfig;
    }

    public function isUsingCdn(): bool
    {
        return !empty($this->url_cdn);
    }

    public function isUsingAccelerate(): bool
    {
        return $this->useAccelerate;
    }

    public function isUsingPathStyle(): bool
    {
        return $this->usePathStyle;
    }
}
