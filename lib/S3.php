<?php

namespace S3\Lib;

use Aws\S3\S3Client;
use Aws\S3\S3MultiRegionClient;
use InvalidArgumentException;

class S3
{
    const ENDPOINT = 's3.amazonaws.com';
    const ENDPOINT_LOCAL = 'http://localhost:4566';
    const HEADER_CONTENT_TYPE = 'Content-Type';
    const HEADER_REQUEST_PAYER = 'x-amz-request-payer';
    const HEADER_ACL = 'x-amz-acl';
    const ACL_PRIVATE = 'private';
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PUBLIC_WRITE = 'public-read-write';
    const ACL_AUTH_READ = 'authenticated-read';
    const ACL_BUCKET_OWNER_READ = 'bucket-owner-read';
    const ACL_BUCKET_OWNER_FULL_CONTROL = 'bucket-owner-full-control';
    const REGION = 'sa-east-1';

    protected array $config = [];
    protected array $buckets = [];
    protected ?S3Client $defaultClient = null;

    public static function singleton(): self
    {
        static $singleton = null;
        return $singleton ??= new self();
    }

    public function use(string $alias, array $config): self
    {
        if (empty($alias)) {
            throw new InvalidArgumentException('Alias cannot be empty');
        }

        $requiredKeys = ['bucket', 'region', 'access_key', 'secret_key'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                throw new InvalidArgumentException("Missing required configuration key: {$key}");
            }
        }

        $this->config[$alias] = $config;
        return $this;
    }

    public function buckets(): array
    {
        return $this->buckets;
    }

    public function bucket(?string $alias = null): Bucket
    {
        if (!$alias) {
            $keys = array_keys($this->config);
            if (empty($keys)) {
                throw new InvalidArgumentException('No S3 configuration found. Use S3::use() to configure first.');
            }
            $alias = array_shift($keys);
        }

        if (!isset($this->config[$alias])) {
            throw new InvalidArgumentException("S3 configuration for alias '{$alias}' not found");
        }

        if (!isset($this->buckets[$alias])) {
            $this->buckets[$alias] = new Bucket($this->config[$alias]);
        }

        return $this->buckets[$alias];
    }

    public function getConfig(string $alias): array
    {
        if (!isset($this->config[$alias])) {
            throw new InvalidArgumentException("Configuration for alias '{$alias}' not found");
        }
        return $this->config[$alias];
    }

    public function hasConfig(string $alias): bool
    {
        return isset($this->config[$alias]);
    }

    public function removeConfig(string $alias): self
    {
        unset($this->config[$alias], $this->buckets[$alias]);
        return $this;
    }

    public function clearConfigs(): self
    {
        $this->config = [];
        $this->buckets = [];
        return $this;
    }
}