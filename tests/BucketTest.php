<?php

namespace Tests;

use Aws\MockHandler;
use Aws\Result;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use S3\Lib\Bucket;
use S3\Lib\S3;
use InvalidArgumentException;

class BucketTest extends TestCase
{
    /**
     * Cria um bucket de teste. A construção não faz chamadas de rede.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeBucket(array $overrides = []): Bucket
    {
        return new Bucket(array_merge([
            'bucket' => 'my-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret',
        ], $overrides));
    }

    public function testConstructorExposesBasicGetters(): void
    {
        $bucket = $this->makeBucket(['region' => 'sa-east-1']);

        $this->assertSame('my-bucket', $bucket->getBucket());
        $this->assertSame('sa-east-1', $bucket->getRegion());
        $this->assertSame('test-key', $bucket->getAccessKey());
    }

    public function testInvalidBucketNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeBucket(['bucket' => 'Invalid_Bucket']);
    }

    public function testInvalidRegionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeBucket(['region' => 'US East 1']);
    }

    public function testPathStripsLeadingSlash(): void
    {
        $bucket = $this->makeBucket();

        $this->assertSame('folder/file.txt', $bucket->path('/folder/file.txt'));
    }

    public function testPathAppliesRoot(): void
    {
        $bucket = $this->makeBucket();
        $bucket->root('uploads');

        $this->assertSame('uploads/file.txt', $bucket->path('file.txt'));
        $this->assertSame('my-bucket/uploads/file.txt', $bucket->path('file.txt', true));
    }

    public function testRootTrimsSlashes(): void
    {
        $bucket = $this->makeBucket();
        $bucket->root('/uploads/');

        $this->assertSame('uploads', $bucket->root());
    }

    public function testDefaultAclIsNullByDefault(): void
    {
        $this->assertNull($this->makeBucket()->getDefaultAcl());
    }

    public function testAclFromConfig(): void
    {
        $bucket = $this->makeBucket(['acl' => S3::ACL_PUBLIC_READ]);

        $this->assertSame(S3::ACL_PUBLIC_READ, $bucket->getDefaultAcl());
    }

    public function testSetDefaultAcl(): void
    {
        $bucket = $this->makeBucket();
        $result = $bucket->setDefaultAcl(S3::ACL_PRIVATE);

        $this->assertSame($bucket, $result);
        $this->assertSame(S3::ACL_PRIVATE, $bucket->getDefaultAcl());
    }

    public function testObjectUrlVirtualHosted(): void
    {
        $bucket = $this->makeBucket();

        $this->assertSame(
            'https://my-bucket.s3.us-east-1.amazonaws.com/a/b.txt',
            $bucket->getObjectUrl('a/b.txt', true, false)
        );
    }

    public function testObjectUrlPathStyle(): void
    {
        $bucket = $this->makeBucket(['use_path_style' => true]);

        $this->assertSame(
            'https://s3.us-east-1.amazonaws.com/my-bucket/a.txt',
            $bucket->getObjectUrl('a.txt', true, false)
        );
    }

    public function testObjectUrlUsesCdnWhenConfigured(): void
    {
        $bucket = $this->makeBucket(['url_cdn' => 'https://cdn.example.com']);

        $this->assertTrue($bucket->isUsingCdn());
        $this->assertSame(
            'https://cdn.example.com/img.jpg',
            $bucket->getObjectUrl('img.jpg')
        );
    }

    public function testObjectUrlCacheRespectsRoot(): void
    {
        $bucket = $this->makeBucket(['url_cdn' => 'https://cdn.example.com']);

        $first = $bucket->getObjectUrl('img.jpg');
        $bucket->root('v2');
        $second = $bucket->getObjectUrl('img.jpg');

        $this->assertNotSame($first, $second);
        $this->assertSame('https://cdn.example.com/img.jpg', $first);
        $this->assertSame('https://cdn.example.com/v2/img.jpg', $second);
    }

    public function testClearUrlCacheReturnsSelf(): void
    {
        $bucket = $this->makeBucket();

        $this->assertSame($bucket, $bucket->clearUrlCache());
    }

    public function testEndpointConfigForCustomEndpoint(): void
    {
        $bucket = $this->makeBucket([
            'endpoint' => 'http://localhost:4566',
        ]);

        $config = $bucket->getEndpointConfig();
        $this->assertSame('custom', $config['type']);
        $this->assertTrue($bucket->isUsingPathStyle());
    }

    public function testEndpointConfigForAccelerate(): void
    {
        $bucket = $this->makeBucket(['use_accelerate' => true]);

        $this->assertTrue($bucket->isUsingAccelerate());
        $this->assertSame('accelerate', $bucket->getEndpointConfig()['type']);
    }

    /**
     * Injeta respostas simuladas no cliente do bucket (sem rede).
     *
     * @param array<int, Result> $results
     */
    private function mockResponses(Bucket $bucket, array $results): void
    {
        $bucket->getClient()->getHandlerList()->setHandler(new MockHandler($results));
    }

    public function testGetBucketStatsSinglePass(): void
    {
        $bucket = $this->makeBucket();
        $this->mockResponses($bucket, [
            new Result([
                'Contents' => [
                    ['Key' => 'a.txt', 'Size' => 100],
                    ['Key' => 'b.txt', 'Size' => 250],
                ],
                'IsTruncated' => false,
            ]),
        ]);

        $stats = $bucket->getBucketStats();

        $this->assertSame(350, $stats['size']);
        $this->assertSame(2, $stats['count']);
    }

    public function testUploadMultipleConcurrent(): void
    {
        $bucket = $this->makeBucket();
        $this->mockResponses($bucket, [
            new Result([]),
            new Result([]),
        ]);

        $out = $bucket->uploadMultiple([
            'a.txt' => 'conteudo-a',
            'b.txt' => 'conteudo-b',
        ]);

        $this->assertSame([], $out['errors']);
        $this->assertArrayHasKey('a.txt', $out['results']);
        $this->assertArrayHasKey('b.txt', $out['results']);
    }

    public function testDownloadMultipleConcurrent(): void
    {
        $bucket = $this->makeBucket();
        $this->mockResponses($bucket, [
            new Result(['Body' => Utils::streamFor('conteudo-a')]),
            new Result(['Body' => Utils::streamFor('conteudo-b')]),
        ]);

        $out = $bucket->downloadMultiple(['a.txt', 'b.txt']);

        $this->assertSame([], $out['errors']);
        $this->assertSame('conteudo-a', $out['results']['a.txt']);
        $this->assertSame('conteudo-b', $out['results']['b.txt']);
    }

    public function testDeleteMultipleChunksAt1000(): void
    {
        $bucket = $this->makeBucket();
        $this->mockResponses($bucket, [
            new Result([]),
            new Result([]),
        ]);

        $keys = [];
        for ($i = 0; $i < 1001; $i++) {
            $keys[] = "file-{$i}.txt";
        }

        $results = $bucket->deleteMultiple($keys);

        // 1001 chaves => 2 lotes (1000 + 1)
        $this->assertCount(2, $results);
    }
}
