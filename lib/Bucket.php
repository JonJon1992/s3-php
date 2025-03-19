<?php

namespace S3\Lib;

use Aws\Credentials\Credentials;
use Aws\Result;
use Aws\S3\S3MultiRegionClient;
use Exception;

class Bucket
{
    protected $client;
    protected $root = '';
    protected $bucket;
    protected $region;
    protected $access_key;
    protected $secret_key;
    protected $url_cdn = '';
    protected $timeOff;
    
    public function __construct($config)
    {
        $this->bucket = $config['bucket'];
        $this->region = $config['region'];
        $this->access_key = $config['access_key'];
        $this->secret_key = $config['secret_key'];

        $this->client = new S3MultiRegionClient([
            'version' => 'latest',
            'scheme' => 'http',
            'region' => $this->getRegion(),
            'credentials' => new Credentials($this->getAccessKey(), $this->getSecretKey())
        ]);
    }

    /**
     * @return S3MultiRegionClient
     */
    public function getClient(): S3MultiRegionClient
    {
        return $this->client;
    }

    /**
     * @return string
     */
    public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * @return mixed
     */
    public function getBucket(): mixed
    {
        return $this->bucket;
    }

    /**
     * @return mixed
     */
    public function getRegion(): mixed
    {
        return $this->region;
    }

    /**
     * @return mixed
     */
    public function getAccessKey(): mixed
    {
        return $this->access_key;
    }

    /**
     * @return mixed
     */
    public function getSecretKey(): mixed
    {
        return $this->secret_key;
    }


    public function isExistBucket(): bool
    {
        return $this->client->doesBucketExist($this->getBucket());
    }

    public function locationBucket(): Result
    {
        return $this->client->getBucketLocation(['Bucket' => $this->getBucket()]);
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function root($root = null)
    {
        if ($root === null) {
            return $this->root;
        }
        $root = trim($root);

        if (substr($root, 0, 1) == `/`) {
            $root = substr($root, 1);
        }
        if (str_ends_with($root, '/')) {
            $root = substr($root, 0, -1);
        }
        $this->root = $root;
        return $this;
    }

    public function get($file_path)
    {
        $data = $this->getObject($this->path($file_path));
        if (!$data) {
            return null;
        }
        return $data['Body']->getContents();
    }

    public function path($path, $bucket = false): string
    {
        $path = trim($path);

        if (substr($path, 0, 1) == `/`) {
            $root = substr($path, 1);
        }
        if ($this->root != '') {
            $path = $this->root . '/' . $path;
        }
        if ($bucket) {
            $path = $this->bucket . '/' . $path;
        }
        return $path;
    }

    public function getObject($file_path): ?Result
    {
        try {
            return $this->client->getObject(['Bucket' => $this->getBucket(), 'Key' => $file_path]);
        } catch (Exception $exception) {
            return null;
        }
    }

    public function existObject($file_path): bool
    {
        return $this->client->doesObjectExist($this->getBucket(), $this->path($file_path));
    }

    public function getFiles($path): array
    {
        $files = [];

        $prefix = rtrim($path, '/') . '/';

        $objs = $this->client->getPaginator('ListObjects', ['Bucket' => $this->getBucket(), 'Prefix' => $prefix]);

        foreach ($objs as $obj) {
            $items = $obj->search("Contents[*]");
            foreach ($items as $item) {
                $files[] = str_replace($prefix, '', $item['key']);
            }
        }
        return $files;
    }

    public function put($file_path, $file_content, $metadata = []): Result
    {
        $object = [
            S3::HEADER_ACL => S3::ACL_PUBLIC_READ
        ];
        $object = array_merge($object, $metadata);

        $object['Bucket'] = $this->getBucket();
        $object['Key'] = $this->path($file_path);
        $object['Body'] = $file_content;

        return $this->client->putObject($object);
    }

    public function putFile($file_path, $file_path_local, $metadata = []): Result
    {
        $metadata['Content-Type'] = mime_content_type($file_path_local);
        return $this->put($file_path, file_get_contents($file_path_local), $metadata);
    }

    public function delete($file_path): Result
    {
        return $this->client->deleteObject([
            'Bucket' => $this->getBucket(),
            'Key' => $this->path($file_path)
        ]);
    }

    public function endpoint($https = true): string
    {

        return ($https ? 'https' : 'http') . '://' . $this->bucket . '.' . $this->getEndpoint();
    }

    public function time()
    {
        if ($this->timeOff == null) {
            $this->caclTimeSet();
        }
        return time() + $this->timeOff;
    }

    public function caclTimeSet(): void
    {
        $data = $this->locationBucket();
        $timeSys = time();

        $timeAWS = strtotime($data['@metadata']['headers']['date']);

        $this->timeOff = $timeAWS - $timeSys;
    }

    public function fileUrl($file_path, $exp = 3600, $https = true, $endpointLocal = false): string
    {
        $file_path = $this->path(str_replace(['%2F', '%2B'], ['/', '+'], rawurlencode($file_path)));
        $url = $this->endpoint($https);
        $key = $this->getAccessKey();
        $exp = $this->time() + $exp;

        $signature = urlencode($this->hash("GET\n\n\n{$exp}\n/{$this->getBucket()}/{$file_path}"));

        return sprintf('%s/%s?AWSAccessKeyId=%s&Expires=%u&Signature=%s', $url, $file_path, $key, $exp, $signature);
    }

    public function hash($string): string
    {
        if (extension_loaded('hash')) {
            $string = hash_hmac('sha1', $string, $this->getSecretKey(), true);

        } else {
            $secret = str_pad($this->getSecretKey(), 64, chr(0x00));
            $string = pack('H*', sha1(($secret ^ (str_repeat(chr(0x36), 64))) . $string));
            $string = pack('H*', sha1(($secret ^ (str_repeat(chr(0x5c), 64))) . $string));
        }
        return base64_encode($string);
    }
}
