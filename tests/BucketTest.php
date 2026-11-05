<?php

namespace Tests;

use ArrayObject;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
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

    /**
     * @param array<int, Result> $results
     * @return ArrayObject<int, array{command: CommandInterface, request: RequestInterface}>
     */
    private function captureRequests(Bucket $bucket, array $results): ArrayObject
    {
        /** @var ArrayObject<int, array{command: CommandInterface, request: RequestInterface}> $captured */
        $captured = new ArrayObject();
        $queue = array_map(
            fn(Result $result) => function (CommandInterface $command, RequestInterface $request) use ($captured, $result): Result {
                $captured->append(['command' => $command, 'request' => $request]);
                return $result;
            },
            $results
        );
        $bucket->getClient()->getHandlerList()->setHandler(new MockHandler($queue));
        return $captured;
    }

    /**
     * @param ArrayObject<int, array{command: CommandInterface, request: RequestInterface}> $captured
     * @return array{command: CommandInterface, request: RequestInterface}
     */
    private function lastCall(ArrayObject $captured): array
    {
        $calls = $captured->getArrayCopy();
        $this->assertNotEmpty($calls);
        return $calls[count($calls) - 1];
    }

    /**
     * @return array<string, string>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        /** @var array<string, string> $query */
        return $query;
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

    public function testPresignedUrlWithDefaultArgumentsSignsGetObject(): void
    {
        $url = $this->makeBucket()->presignedUrl('docs/a.txt');

        $this->assertStringEndsWith('/docs/a.txt', (string) parse_url($url, PHP_URL_PATH));
        $this->assertArrayHasKey('X-Amz-Signature', $this->queryOf($url));
    }

    public function testPresignedUrlAcceptsHttpMethodsAndOperationNames(): void
    {
        $bucket = $this->makeBucket();

        foreach (['GET', 'put', 'DELETE', 'HEAD', 'GetObject'] as $method) {
            $this->assertArrayHasKey('X-Amz-Signature', $this->queryOf($bucket->presignedUrl('a.txt', 60, $method)));
        }
    }

    public function testPresignedUrlRejectsUnknownMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeBucket()->presignedUrl('a.txt', 60, 'FOO');
    }

    public function testFileUrlDelegatesToPresignedUrl(): void
    {
        $this->assertArrayHasKey('X-Amz-Signature', $this->queryOf($this->makeBucket()->fileUrl('a.txt')));
    }

    public function testDownloadUrlSetsResponseHeaders(): void
    {
        $url = $this->makeBucket()->getDownloadUrl('r.pdf', 600, [
            'response_content_disposition' => 'attachment; filename="r.pdf"',
        ]);

        $query = $this->queryOf($url);
        $this->assertSame('attachment; filename="r.pdf"', $query['response-content-disposition']);
        $this->assertSame('application/octet-stream', $query['response-content-type']);
        $this->assertSame('no-cache', $query['response-cache-control']);
    }

    public function testPresignedUrlNormalizesOptionNames(): void
    {
        $url = $this->makeBucket()->presignedUrl('a.txt', 60, 'GET', ['ResponseContentType' => 'text/plain']);

        $this->assertSame('text/plain', $this->queryOf($url)['response-content-type']);
    }

    public function testPutTranslatesHeaderNamesToSdkParams(): void
    {
        $bucket = $this->makeBucket();
        $captured = $this->captureRequests($bucket, [new Result([])]);

        $bucket->put('a.txt', 'x', [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'max-age=60',
            'x-amz-meta-author' => 'ana',
        ]);

        ['command' => $command, 'request' => $request] = $this->lastCall($captured);
        $this->assertSame('text/plain', $command['ContentType']);
        $this->assertSame('max-age=60', $command['CacheControl']);
        $this->assertSame(['author' => 'ana'], $command['Metadata']);
        $this->assertSame('text/plain', $request->getHeaderLine('Content-Type'));
    }

    public function testPutCallMetadataOverridesDefaultsAndAppliesDefaultAcl(): void
    {
        $bucket = $this->makeBucket(['acl' => S3::ACL_PUBLIC_READ]);
        $bucket->setDefaultMetadata(['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache']);
        $captured = $this->captureRequests($bucket, [new Result([])]);

        $bucket->put('a.txt', 'x', ['ContentType' => 'text/plain']);

        $command = $this->lastCall($captured)['command'];
        $this->assertSame('text/plain', $command['ContentType']);
        $this->assertSame('no-cache', $command['CacheControl']);
        $this->assertSame(S3::ACL_PUBLIC_READ, $command['ACL']);
    }

    public function testPutFileDetectsContentTypeUnlessGiven(): void
    {
        $file = tempnam(sys_get_temp_dir(), 's3');
        file_put_contents($file, 'texto simples');

        try {
            $bucket = $this->makeBucket();
            $captured = $this->captureRequests($bucket, [new Result([]), new Result([])]);

            $bucket->putFile('a.txt', $file);
            $this->assertSame('text/plain', $this->lastCall($captured)['command']['ContentType']);

            $bucket->putFile('a.csv', $file, ['Content-Type' => 'text/csv']);
            $this->assertSame('text/csv', $this->lastCall($captured)['command']['ContentType']);
        } finally {
            unlink($file);
        }
    }

    public function testPutFileThrowsForMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeBucket()->putFile('a.txt', '/nao/existe.txt');
    }

    public function testUploadMultipleTranslatesHeaderNames(): void
    {
        $bucket = $this->makeBucket();
        $captured = $this->captureRequests($bucket, [new Result([]), new Result([])]);

        $out = $bucket->uploadMultiple(['a.txt' => 'a', 'b.txt' => 'b'], ['Content-Type' => 'text/plain']);

        $this->assertSame([], $out['errors']);
        $this->assertCount(2, $captured);
        foreach ($captured as $call) {
            $this->assertSame('text/plain', $call['command']['ContentType']);
        }
    }

    public function testUploadLargeFilePassesParamsToMultipartUpload(): void
    {
        $file = tempnam(sys_get_temp_dir(), 's3');
        file_put_contents($file, 'conteudo');

        try {
            $bucket = $this->makeBucket(['acl' => S3::ACL_PRIVATE]);
            $captured = $this->captureRequests($bucket, [
                new Result(['UploadId' => 'u1']),
                new Result(['ETag' => '"e1"']),
                new Result(['Location' => 'https://example.com/big.bin']),
            ]);

            $bucket->uploadLargeFile('big.bin', $file, [
                'Content-Type' => 'application/zip',
                'x-amz-meta-origem' => 'teste',
            ]);

            $calls = $captured->getArrayCopy();
            $initiate = $calls[0]['command'];
            $this->assertSame('CreateMultipartUpload', $initiate->getName());
            $this->assertSame('application/zip', $initiate['ContentType']);
            $this->assertSame(['origem' => 'teste'], $initiate['Metadata']);
            $this->assertSame(S3::ACL_PRIVATE, $initiate['ACL']);
        } finally {
            unlink($file);
        }
    }

    public function testObjectUrlEncodesSpacesAndAccents(): void
    {
        $this->assertSame(
            'https://my-bucket.s3.us-east-1.amazonaws.com/fotos/relat%C3%B3rio%20final.pdf',
            $this->makeBucket()->getObjectUrl('fotos/relatório final.pdf', true, false)
        );
    }

    public function testObjectUrlWithoutCdnIgnoresConfiguredCdn(): void
    {
        $bucket = $this->makeBucket(['url_cdn' => 'https://cdn.example.com']);

        $this->assertSame(
            'https://my-bucket.s3.us-east-1.amazonaws.com/img.jpg',
            $bucket->getObjectUrl('img.jpg', true, false)
        );
    }

    public function testObjectUrlForCustomEndpointIncludesBucket(): void
    {
        $bucket = $this->makeBucket(['endpoint' => 'http://localhost:4566/']);

        $this->assertSame('http://localhost:4566/my-bucket/a.txt', $bucket->getObjectUrl('a.txt'));
    }

    public function testObjectUrlForChinaRegionUsesComCnDomain(): void
    {
        $bucket = $this->makeBucket(['region' => 'cn-north-1']);

        $this->assertSame(
            'https://my-bucket.s3.cn-north-1.amazonaws.com.cn/a.txt',
            $bucket->getObjectUrl('a.txt')
        );
    }

    public function testSetCdnUrlInvalidatesCachedUrls(): void
    {
        $bucket = $this->makeBucket(['url_cdn' => 'https://old.example.com/']);

        $this->assertSame('https://old.example.com/img.jpg', $bucket->getObjectUrl('img.jpg'));

        $bucket->setCdnUrl('https://new.example.com');

        $this->assertSame('https://new.example.com/img.jpg', $bucket->getObjectUrl('img.jpg'));
    }

    public function testPathStyleIsUsedForRequests(): void
    {
        $bucket = $this->makeBucket(['use_path_style' => true]);
        $captured = $this->captureRequests($bucket, [new Result([])]);

        $bucket->put('a.txt', 'x');

        $uri = $this->lastCall($captured)['request']->getUri();
        $this->assertStringNotContainsString('my-bucket', $uri->getHost());
        $this->assertSame('/my-bucket/a.txt', $uri->getPath());
    }

    public function testGetFilesWithoutPathListsWholeBucket(): void
    {
        $bucket = $this->makeBucket();
        $captured = $this->captureRequests($bucket, [
            new Result([
                'Contents' => [['Key' => 'a.txt'], ['Key' => 'dir/b.txt']],
                'IsTruncated' => false,
            ]),
        ]);

        $this->assertSame(['a.txt', 'dir/b.txt'], $bucket->getFiles());
        $this->assertNull($this->lastCall($captured)['command']['Prefix']);
    }

    public function testGetFilesAppliesRoot(): void
    {
        $bucket = $this->makeBucket();
        $bucket->root('uploads');
        $captured = $this->captureRequests($bucket, [
            new Result([
                'Contents' => [['Key' => 'uploads/img/a.jpg']],
                'IsTruncated' => false,
            ]),
        ]);

        $this->assertSame(['a.jpg'], $bucket->getFiles('img'));
        $this->assertSame('uploads/img/', $this->lastCall($captured)['command']['Prefix']);
    }

    public function testCopyEncodesSourceKey(): void
    {
        $bucket = $this->makeBucket();
        $captured = $this->captureRequests($bucket, [new Result([])]);

        $bucket->copy('fotos/minha foto.jpg', 'b.jpg');

        $command = $this->lastCall($captured)['command'];
        $this->assertSame('my-bucket/fotos/minha%20foto.jpg', $command['CopySource']);
        $this->assertNull($command['MetadataDirective']);
    }

    public function testCopyWithMetadataPreservesContentHeaders(): void
    {
        $bucket = $this->makeBucket();
        $captured = $this->captureRequests($bucket, [
            new Result(['ContentType' => 'image/jpeg', 'CacheControl' => 'max-age=60']),
            new Result([]),
        ]);

        $bucket->copy('a.jpg', 'b.jpg', ['author' => 'ana']);

        $calls = $captured->getArrayCopy();
        $this->assertSame('HeadObject', $calls[0]['command']->getName());
        $copy = $calls[1]['command'];
        $this->assertSame('image/jpeg', $copy['ContentType']);
        $this->assertSame('max-age=60', $copy['CacheControl']);
        $this->assertSame(['author' => 'ana'], $copy['Metadata']);
        $this->assertSame('REPLACE', $copy['MetadataDirective']);
    }

    /**
     * @return array<int, mixed>
     */
    private function policyConditions(array $post): array
    {
        $policy = json_decode(base64_decode($post['fields']['Policy']), true);
        return $policy['conditions'];
    }

    public function testPresignedPostAddsConditionsForFormFields(): void
    {
        $post = $this->makeBucket()->presignedPostUrl('uploads/x.txt', 600, [], ['acl' => 'private']);

        $this->assertSame('uploads/x.txt', $post['fields']['key']);
        $conditions = $this->policyConditions($post);
        $this->assertContains(['bucket' => 'my-bucket'], $conditions);
        $this->assertContains(['key' => 'uploads/x.txt'], $conditions);
        $this->assertContains(['acl' => 'private'], $conditions);
    }

    public function testPresignedPostKeepsUserConditions(): void
    {
        $post = $this->makeBucket()->presignedPostUrl('uploads/x.txt', 600, [
            ['starts-with', '$key', 'uploads/'],
            ['content-length-range', 1, 1024],
        ]);

        $conditions = $this->policyConditions($post);
        $this->assertContains(['starts-with', '$key', 'uploads/'], $conditions);
        $this->assertNotContains(['key' => 'uploads/x.txt'], $conditions);
    }
}
