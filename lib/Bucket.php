<?php

namespace S3\Lib;

use Aws\CommandInterface;
use Aws\CommandPool;
use Aws\Credentials\Credentials;
use Aws\Result;
use Aws\ResultInterface;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\S3\MultipartUploader;
use Aws\S3\PostObjectV4;
use Exception;
use InvalidArgumentException;

class Bucket
{
    protected const PRESIGN_OPERATIONS = [
        'GET' => 'GetObject',
        'PUT' => 'PutObject',
        'DELETE' => 'DeleteObject',
        'HEAD' => 'HeadObject',
    ];

    protected const HEADER_PARAMS = [
        'acl' => 'ACL',
        'cache-control' => 'CacheControl',
        'content-disposition' => 'ContentDisposition',
        'content-encoding' => 'ContentEncoding',
        'content-language' => 'ContentLanguage',
        'content-type' => 'ContentType',
        'expires' => 'Expires',
        'x-amz-acl' => 'ACL',
        'x-amz-request-payer' => 'RequestPayer',
        'x-amz-server-side-encryption' => 'ServerSideEncryption',
        'x-amz-storage-class' => 'StorageClass',
        'x-amz-website-redirect-location' => 'WebsiteRedirectLocation',
    ];

    protected const CONTENT_PARAMS = [
        'ContentType',
        'CacheControl',
        'ContentDisposition',
        'ContentEncoding',
        'ContentLanguage',
    ];

    protected const URL_CACHE_LIMIT = 1000;

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
    protected ?string $defaultAcl = null;

    public function __construct(array $config)
    {
        $this->validateConfig($config);

        $this->bucket = $config['bucket'];
        $this->region = $config['region'];
        $this->access_key = $config['access_key'];
        $this->secret_key = $config['secret_key'];
        $this->url_cdn = rtrim($config['url_cdn'] ?? '', '/');
        $this->usePathStyle = $config['use_path_style'] ?? false;
        $this->useAccelerate = $config['use_accelerate'] ?? false;

        $this->defaultAcl = $config['acl'] ?? null;

        $clientConfig = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => new Credentials($this->access_key, $this->secret_key),
            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
            ]
        ];

        $this->configureEndpoint($clientConfig, $config);

        $this->client = new S3Client($clientConfig);
    }

    protected function configureEndpoint(array &$clientConfig, array $config): void
    {
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

        if ($this->useAccelerate) {
            $clientConfig['use_accelerate_endpoint'] = true;
            $this->endpointConfig = [
                'type' => 'accelerate',
                'url' => "https://{$this->bucket}.s3-accelerate.amazonaws.com"
            ];
            return;
        }

        $clientConfig['use_path_style_endpoint'] = $this->usePathStyle;
        $this->endpointConfig = [
            'type' => 'standard',
            'url' => $this->getDefaultEndpoint(),
            'use_path_style' => $this->usePathStyle
        ];
    }

    protected function getDefaultEndpoint(): string
    {
        return "https://s3.{$this->region}.{$this->dnsSuffix()}";
    }

    protected function dnsSuffix(): string
    {
        return str_starts_with($this->region, 'cn-') ? 'amazonaws.com.cn' : 'amazonaws.com';
    }

    protected function validateConfig(array $config): void
    {
        $required = ['bucket', 'region', 'access_key', 'secret_key'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                throw new InvalidArgumentException("Missing required configuration: {$field}");
            }
        }

        $bucketPattern = '/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/';
        if (!preg_match($bucketPattern, $config['bucket'])) {
            throw new InvalidArgumentException("Invalid bucket name format");
        }

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

    public function setDefaultAcl(?string $acl): self
    {
        $this->defaultAcl = $acl;
        return $this;
    }

    public function getDefaultAcl(): ?string
    {
        return $this->defaultAcl;
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
        return $this->clearUrlCache();
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
            $paginator = $this->client->getPaginator('ListObjectsV2', [
                'Bucket' => $this->bucket
            ]);

            foreach ($paginator as $page) {
                if (empty($page['Contents'])) {
                    continue;
                }

                $this->client->deleteObjects([
                    'Bucket' => $this->bucket,
                    'Delete' => [
                        'Objects' => array_map(fn($obj) => ['Key' => $obj['Key']], $page['Contents'])
                    ]
                ]);
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
        $prefix = rtrim($this->path($path), '/');
        $prefix = $prefix === '' ? '' : $prefix . '/';

        $params = ['Bucket' => $this->bucket];

        if ($prefix !== '') {
            $params['Prefix'] = $prefix;
        }

        if (!$recursive) {
            $params['Delimiter'] = '/';
        }

        $paginator = $this->client->getPaginator('ListObjectsV2', $params);

        $prefixLen = strlen($prefix);

        foreach ($paginator as $page) {
            if (isset($page['Contents'])) {
                foreach ($page['Contents'] as $object) {
                    $key = $object['Key'];
                    if ($key !== $prefix && str_starts_with($key, $prefix)) {
                        $files[] = substr($key, $prefixLen);
                    }
                }
            }

            if (!$recursive && isset($page['CommonPrefixes'])) {
                foreach ($page['CommonPrefixes'] as $commonPrefix) {
                    $commonPrefixKey = $commonPrefix['Prefix'];
                    if (str_starts_with($commonPrefixKey, $prefix)) {
                        $files[] = rtrim(substr($commonPrefixKey, $prefixLen), '/');
                    }
                }
            }
        }

        return array_values(array_filter($files));
    }

    public function put(string $file_path, mixed $file_content, array $metadata = []): Result
    {
        return $this->client->putObject($this->buildPutParams($file_path, $file_content, $metadata));
    }

    public function putFile(string $file_path, string $file_path_local, array $metadata = []): Result
    {
        $this->assertLocalFile($file_path_local);

        return $this->put(
            $file_path,
            file_get_contents($file_path_local),
            $this->withDetectedContentType($metadata, $file_path_local)
        );
    }

    public function putFileStream(string $file_path, string $file_path_local, array $metadata = []): Result
    {
        $this->assertLocalFile($file_path_local);

        return $this->put(
            $file_path,
            fopen($file_path_local, 'r'),
            $this->withDetectedContentType($metadata, $file_path_local)
        );
    }

    public function uploadLargeFile(string $file_path, string $file_path_local, array $metadata = []): Result
    {
        $this->assertLocalFile($file_path_local);

        $params = $this->normalizeParams(array_merge(
            $this->defaultMetadata,
            $this->withDetectedContentType($metadata, $file_path_local)
        ));

        if ($this->defaultAcl !== null && !isset($params['ACL'])) {
            $params['ACL'] = $this->defaultAcl;
        }

        $uploader = new MultipartUploader($this->client, $file_path_local, [
            'bucket' => $this->bucket,
            'key' => $this->path($file_path),
            'params' => $params,
        ]);

        return $uploader->upload();
    }

    public function copy(string $source_path, string $destination_path, array $metadata = []): Result
    {
        $sourceKey = $this->path($source_path);

        $params = [
            'Bucket' => $this->bucket,
            'Key' => $this->path($destination_path),
            'CopySource' => $this->bucket . '/' . S3Client::encodeKey($sourceKey),
        ];

        if ($this->defaultAcl !== null) {
            $params['ACL'] = $this->defaultAcl;
        }

        if (!empty($metadata)) {
            $source = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $sourceKey,
            ]);

            foreach (self::CONTENT_PARAMS as $param) {
                if (!empty($source[$param])) {
                    $params[$param] = $source[$param];
                }
            }

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

    /**
     * @param array<int, string> $file_paths
     * @return array<int, Result>
     */
    public function deleteMultiple(array $file_paths): array
    {
        $results = [];

        foreach (array_chunk($file_paths, 1000) as $chunk) {
            $objects = array_map(fn($path) => ['Key' => $this->path($path)], $chunk);

            $results[] = $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $objects
                ]
            ]);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $files       Mapa caminho => conteúdo (string|resource)
     * @param array<string, mixed> $metadata    Metadados aplicados a todos os objetos
     * @param int                  $concurrency Número de uploads simultâneos
     * @return array{results: array<string, ResultInterface>, errors: array<string, \Throwable>}
     */
    public function uploadMultiple(array $files, array $metadata = [], int $concurrency = 5): array
    {
        $keys = array_map('strval', array_keys($files));
        $values = array_values($files);

        $commands = function () use ($values, $keys, $metadata) {
            foreach ($values as $i => $content) {
                yield $this->client->getCommand('PutObject', $this->buildPutParams($keys[$i], $content, $metadata));
            }
        };

        return $this->runPool($commands(), $concurrency, $keys, fn(ResultInterface $r) => $r);
    }

    /**
     * @param array<int, string> $keys        Caminhos dos objetos
     * @param int                 $concurrency Número de downloads simultâneos
     * @return array{results: array<string, string>, errors: array<string, \Throwable>}
     */
    public function downloadMultiple(array $keys, int $concurrency = 5): array
    {
        $keys = array_values($keys);

        $commands = function () use ($keys) {
            foreach ($keys as $key) {
                yield $this->client->getCommand('GetObject', [
                    'Bucket' => $this->bucket,
                    'Key' => $this->path($key),
                ]);
            }
        };

        return $this->runPool(
            $commands(),
            $concurrency,
            $keys,
            fn(ResultInterface $r) => $r['Body']->getContents()
        );
    }

    /**
     * @param \Iterator<int, CommandInterface> $commands
     * @param array<int, string>               $keys     Chaves na ordem dos comandos
     * @param callable                         $onResult Transforma cada Result
     * @return array{results: array<string, mixed>, errors: array<string, \Throwable>}
     */
    protected function runPool(\Iterator $commands, int $concurrency, array $keys, callable $onResult): array
    {
        $results = [];
        $errors = [];

        $pool = new CommandPool($this->client, $commands, [
            'concurrency' => max(1, $concurrency),
            'fulfilled' => function (ResultInterface $result, int $index) use (&$results, $keys, $onResult): void {
                $results[$keys[$index]] = $onResult($result);
            },
            'rejected' => function (\Throwable $reason, int $index) use (&$errors, $keys): void {
                $errors[$keys[$index]] = $reason;
            },
        ]);

        $pool->promise()->wait();

        return ['results' => $results, 'errors' => $errors];
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
        if ($this->url_cdn !== '') {
            return $this->url_cdn;
        }

        return $this->s3Endpoint($https);
    }

    protected function s3Endpoint(bool $https = true): string
    {
        $scheme = $https ? 'https' : 'http';
        $host = "s3.{$this->region}.{$this->dnsSuffix()}";

        return match ($this->endpointConfig['type']) {
            'custom' => rtrim($this->endpointConfig['url'], '/') . '/' . $this->bucket,
            'accelerate' => "{$scheme}://{$this->bucket}.s3-accelerate.amazonaws.com",
            default => $this->usePathStyle
                ? "{$scheme}://{$host}/{$this->bucket}"
                : "{$scheme}://{$this->bucket}.{$host}",
        };
    }

    public function getObjectUrl(string $file_path, bool $https = true, bool $useCdn = true): string
    {
        $file_path = $this->path($file_path);
        $cacheKey = 'url_' . $file_path . '_' . (int) $https . '_' . (int) $useCdn;

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $baseUrl = $useCdn && $this->url_cdn !== '' ? $this->url_cdn : $this->s3Endpoint($https);
        $url = $this->sanitizeUrl($baseUrl . '/' . S3Client::encodeKey($file_path));

        if (count($this->urlCache) >= self::URL_CACHE_LIMIT) {
            unset($this->urlCache[array_key_first($this->urlCache)]);
        }
        $this->urlCache[$cacheKey] = $url;

        return $url;
    }

    public function getDownloadUrl(string $file_path, int $expiration = 3600, array $options = []): string
    {
        $options = array_merge([
            'ResponseContentDisposition' => 'attachment',
            'ResponseContentType' => 'application/octet-stream',
            'ResponseCacheControl' => 'no-cache',
        ], $this->normalizeParams($options));

        return $this->presignedUrl($file_path, $expiration, 'GET', $options);
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

    /**
     * @deprecated 3.0.0 Usava AWS Signature V2, descontinuada pela AWS e não
     *             aceita na maioria das regiões. Agora delega para presignedUrl()
     *             (SigV4). Use presignedUrl() diretamente.
     */
    public function fileUrl(string $file_path, int $exp = 3600, bool $https = true): string
    {
        return $this->presignedUrl($file_path, $exp);
    }

    /**
     * @param string $method HTTP (GET, PUT, DELETE, HEAD) ou nome da operação do SDK (ex.: GetObject)
     */
    public function presignedUrl(string $file_path, int $exp = 3600, string $method = 'GET', array $options = []): string
    {
        $params = array_merge([
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path),
        ], $this->normalizeParams($options));

        $operation = self::PRESIGN_OPERATIONS[strtoupper($method)] ?? $method;
        $cmd = $this->client->getCommand($operation, $params);

        return (string) $this->client->createPresignedRequest($cmd, "+{$exp} seconds")->getUri();
    }

    /**
     * @param string $file_path  Caminho/chave do objeto no bucket
     * @param int    $exp        Expiração em segundos
     * @param array  $conditions Condições da policy (ex.: ['content-length-range', 1, 10485760])
     * @param array  $formInputs Campos pré-preenchidos do formulário (ex.: ['acl' => 'private'])
     * @return array{url: string, fields: array<string, string>, attributes: array<string, string>}
     */
    public function presignedPostUrl(string $file_path, int $exp = 3600, array $conditions = [], array $formInputs = []): array
    {
        $formInputs = array_merge(['key' => $this->path($file_path)], $formInputs);

        $postObject = new PostObjectV4(
            $this->client,
            $this->bucket,
            $formInputs,
            $this->buildPostConditions($conditions, $formInputs),
            "+{$exp} seconds"
        );

        $attributes = $postObject->getFormAttributes();

        return [
            'url' => $attributes['action'],
            'fields' => $postObject->getFormInputs(),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<int, mixed>    $conditions
     * @param array<string, mixed> $formInputs
     * @return array<int, mixed>
     */
    protected function buildPostConditions(array $conditions, array $formInputs): array
    {
        $covered = [];

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if (array_is_list($condition)) {
                if (isset($condition[1]) && is_string($condition[1])) {
                    $covered[strtolower(ltrim($condition[1], '$'))] = true;
                }
                continue;
            }

            foreach (array_keys($condition) as $field) {
                $covered[strtolower((string) $field)] = true;
            }
        }

        if (!isset($covered['bucket'])) {
            $conditions[] = ['bucket' => $this->bucket];
        }

        foreach ($formInputs as $field => $value) {
            if (!isset($covered[strtolower($field)])) {
                $conditions[] = [$field => $value];
            }
        }

        return $conditions;
    }

    protected function sanitizeUrl(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL generated: {$url}");
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPutParams(string $file_path, mixed $file_content, array $metadata): array
    {
        $params = $this->normalizeParams(array_merge($this->defaultMetadata, $metadata));

        if ($this->defaultAcl !== null && !isset($params['ACL'])) {
            $params['ACL'] = $this->defaultAcl;
        }

        return array_merge([
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path),
            'Body' => $file_content,
        ], $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>
     */
    protected function normalizeParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            $key = (string) $key;
            $lower = strtolower($key);

            if (str_starts_with($lower, 'x-amz-meta-')) {
                $normalized['Metadata'][substr($key, 11)] = $value;
                continue;
            }

            if (isset(self::HEADER_PARAMS[$lower])) {
                $key = self::HEADER_PARAMS[$lower];
            } elseif (preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)+$/', $key)) {
                $key = str_replace('_', '', ucwords($key, '_'));
            }

            if ($key === 'Metadata' && is_array($value)) {
                $normalized['Metadata'] = array_merge($normalized['Metadata'] ?? [], $value);
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    protected function withDetectedContentType(array $metadata, string $file_path_local): array
    {
        if (!empty($this->normalizeParams($metadata)['ContentType'])) {
            return $metadata;
        }

        $metadata['ContentType'] = mime_content_type($file_path_local) ?: 'application/octet-stream';

        return $metadata;
    }

    protected function assertLocalFile(string $file_path_local): void
    {
        if (!is_file($file_path_local)) {
            throw new InvalidArgumentException("Local file not found: {$file_path_local}");
        }
    }

    /**
     * @return array{size: int, count: int}
     */
    public function getBucketStats(): array
    {
        $size = 0;
        $count = 0;

        $paginator = $this->client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->bucket
        ]);

        foreach ($paginator as $page) {
            if (!empty($page['Contents'])) {
                $size += (int) array_sum(array_column($page['Contents'], 'Size'));
                $count += count($page['Contents']);
            }
        }

        return ['size' => $size, 'count' => $count];
    }

    public function getBucketSize(): int
    {
        return $this->getBucketStats()['size'];
    }

    public function getBucketObjectCount(): int
    {
        return $this->getBucketStats()['count'];
    }

    public function getEndpointConfig(): array
    {
        return $this->endpointConfig;
    }

    public function isUsingCdn(): bool
    {
        return $this->url_cdn !== '';
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
