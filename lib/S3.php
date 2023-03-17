<?php

namespace S3\Lib;

class S3
{
    const ENDPOINT = 's3.amazonaws.com';
    const HEADER_CONTENT_TYPE = 'Content_Type';
    const HEADER_REQUEST_PAYER = 'x-amz-request-payer';
    const HEADER_ACL = 'x-amz-acl';
    const ACL_PRIVATE = 'private';
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PUBLIC_WRITE = 'public-read-write';
    const ACL_AUTH_READ = 'authenticated-read';
    const REGION = 'sa-east-1';
    protected array $config = [];

    protected array $buckets = [];

    public static function singleton(): ?S3
    {
        static $singleton = null;
        return is_null($singleton) ? $singleton = new self() : $singleton;

    }

    public function use($alias, $config): void
    {
        $this->config[$alias] = $config;

    }

    public function buckets(): array
    {
        return $this->buckets;
    }

    public function bucket($alias = null)
    {
        if (!$alias) {
            $keys = array_keys($this->config);
            $alias = array_shift($keys);
        }

        $config = $this->config[$alias];
        if (!isset($this->buckets[$alias])) {
            $this->buckets[$alias] = new Bucket($config);
        }
        return $this->buckets[$alias];
    }
}