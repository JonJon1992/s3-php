<?php

namespace S3\Lib;

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

        // Configurações de endpoint
        $this->url_cdn = $config['url_cdn'] ?? '';
        $this->usePathStyle = $config['use_path_style'] ?? false;
        $this->useAccelerate = $config['use_accelerate'] ?? false;

        // ACL padrão para uploads. Por padrão (null) nenhum header ACL é
        // enviado — compatível com buckets que têm ACLs desabilitadas
        // (Object Ownership = "Bucket owner enforced"). Defina 'acl' na
        // configuração (ex.: S3::ACL_PUBLIC_READ) se precisar de ACLs.
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
            // Apagar todos os objetos primeiro, paginando para suportar
            // buckets com mais de 1000 objetos.
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

        // Usar array_filter sem callback para remover valores vazios (PHP 8.0+)
        return array_values(array_filter($files));
    }

    public function put(string $file_path, mixed $file_content, array $metadata = []): Result
    {
        $object = [
            'Bucket' => $this->bucket,
            'Key' => $this->path($file_path),
            'Body' => $file_content,
        ];

        // Merge default metadata
        $metadata = array_merge($this->defaultMetadata, $metadata);

        // Aplicar ACL padrão apenas se configurada e não sobrescrita na chamada
        if ($this->defaultAcl !== null && !isset($metadata['ACL'])) {
            $object['ACL'] = $this->defaultAcl;
        }

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

        $uploaderConfig = [
            'bucket' => $this->bucket,
            'key' => $this->path($file_path),
            'metadata' => $metadata
        ];

        if ($this->defaultAcl !== null) {
            $uploaderConfig['acl'] = $this->defaultAcl;
        }

        $uploader = new MultipartUploader($this->client, $file_path_local, $uploaderConfig);

        return $uploader->upload();
    }

    public function copy(string $source_path, string $destination_path, array $metadata = []): Result
    {
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $this->path($destination_path),
            'CopySource' => $this->bucket . '/' . $this->path($source_path),
        ];

        if ($this->defaultAcl !== null) {
            $params['ACL'] = $this->defaultAcl;
        }

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

    /**
     * Apaga múltiplos objetos. O DeleteObjects do S3 aceita no máximo 1000
     * chaves por requisição, então a lista é dividida em lotes.
     *
     * @param array<int, string> $file_paths
     * @return array<int, Result> Um Result por lote
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
     * Envia múltiplos objetos de forma concorrente usando CommandPool.
     *
     * @param array<string, mixed> $files       Mapa caminho => conteúdo (string|resource)
     * @param array<string, mixed> $metadata    Metadados aplicados a todos os objetos
     * @param int                  $concurrency Número de uploads simultâneos
     * @return array{results: array<string, ResultInterface>, errors: array<string, \Throwable>}
     */
    public function uploadMultiple(array $files, array $metadata = [], int $concurrency = 5): array
    {
        $metadata = array_merge($this->defaultMetadata, $metadata);
        $keys = array_keys($files);
        $values = array_values($files);

        $commands = function () use ($values, $keys, $metadata) {
            foreach ($values as $i => $content) {
                $args = [
                    'Bucket' => $this->bucket,
                    'Key' => $this->path($keys[$i]),
                    'Body' => $content,
                ];

                if ($this->defaultAcl !== null && !isset($metadata['ACL'])) {
                    $args['ACL'] = $this->defaultAcl;
                }

                if (!empty($metadata)) {
                    $args = array_merge($args, $metadata);
                }

                yield $this->client->getCommand('PutObject', $args);
            }
        };

        return $this->runPool($commands(), $concurrency, $keys, fn(ResultInterface $r) => $r);
    }

    /**
     * Baixa múltiplos objetos de forma concorrente usando CommandPool.
     *
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
     * Executa um CommandPool, agregando resultados e erros pela chave original.
     *
     * O CommandPool entrega o índice inteiro da iteração nos callbacks; ele é
     * mapeado de volta para a chave original via $keys.
     *
     * @param \Iterator<int, \Aws\CommandInterface> $commands
     * @param array<int, string>                    $keys     Chaves na ordem dos comandos
     * @param callable                              $onResult Transforma cada Result
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
        $file_path = $this->path($file_path);
        $cacheKey = "url_{$file_path}_{$https}_{$useCdn}";

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

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
        $resolvedPath = $this->path($file_path);
        $cacheKey = "download_{$resolvedPath}_{$expiration}_" . md5(serialize($options));

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
            'Key' => $resolvedPath
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

    /**
     * @deprecated 3.0.0 Usava AWS Signature V2, descontinuada pela AWS e não
     *             aceita na maioria das regiões. Agora delega para presignedUrl()
     *             (SigV4). Use presignedUrl() diretamente.
     */
    public function fileUrl(string $file_path, int $exp = 3600, bool $https = true): string
    {
        return $this->presignedUrl($file_path, $exp);
    }

    public function presignedUrl(string $file_path, int $exp = 3600, string $method = 'GET', array $options = []): string
    {
        $resolvedPath = $this->path($file_path);
        $cacheKey = "presigned_{$resolvedPath}_{$exp}_{$method}_" . md5(serialize($options));

        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }

        $params = [
            'Bucket' => $this->bucket,
            'Key' => $resolvedPath
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

    /**
     * Gera os dados para upload direto via formulário POST (presigned POST).
     *
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
            $conditions,
            "+{$exp} seconds"
        );

        $attributes = $postObject->getFormAttributes();

        return [
            'url' => $attributes['action'],
            'fields' => $postObject->getFormInputs(),
            'attributes' => $attributes,
        ];
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

    /**
     * Retorna tamanho total (bytes) e contagem de objetos em uma única
     * varredura do bucket, evitando listar duas vezes.
     *
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
                $size += array_sum(array_column($page['Contents'], 'Size'));
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
