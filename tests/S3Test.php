<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use S3\Lib\S3;
use S3\Lib\Bucket;
use InvalidArgumentException;

class S3Test extends TestCase
{
    protected function setUp(): void
    {
        // Limpar configurações antes de cada teste
        S3::singleton()->clearConfigs();
    }

    public function testSingletonReturnsSameInstance()
    {
        $instance1 = S3::singleton();
        $instance2 = S3::singleton();

        $this->assertSame($instance1, $instance2);
    }

    public function testUseMethodWithValidConfig()
    {
        $s3 = S3::singleton();
        $config = [
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ];

        $result = $s3->use('test', $config);

        $this->assertSame($s3, $result);
        $this->assertTrue($s3->hasConfig('test'));
        $this->assertEquals($config, $s3->getConfig('test'));
    }

    public function testUseMethodWithEmptyAlias()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias cannot be empty');

        S3::singleton()->use('', []);
    }

    public function testUseMethodWithMissingRequiredKeys()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required configuration key: bucket');

        S3::singleton()->use('test', [
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ]);
    }

    public function testBucketMethodWithValidAlias()
    {
        $s3 = S3::singleton();
        $config = [
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ];

        $s3->use('test', $config);
        $bucket = $s3->bucket('test');

        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('test-bucket', $bucket->getBucket());
        $this->assertEquals('us-east-1', $bucket->getRegion());
    }

    public function testBucketMethodWithDefaultAlias()
    {
        $s3 = S3::singleton();
        $config = [
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ];

        $s3->use('default', $config);
        $bucket = $s3->bucket();

        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('test-bucket', $bucket->getBucket());
    }

    public function testBucketMethodWithNoConfig()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No S3 configuration found. Use S3::use() to configure first.');

        S3::singleton()->bucket();
    }

    public function testBucketMethodWithInvalidAlias()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("S3 configuration for alias 'invalid' not found");

        S3::singleton()->bucket('invalid');
    }

    public function testGetConfigMethod()
    {
        $s3 = S3::singleton();
        $config = [
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ];

        $s3->use('test', $config);
        $retrievedConfig = $s3->getConfig('test');

        $this->assertEquals($config, $retrievedConfig);
    }

    public function testGetConfigMethodWithInvalidAlias()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Configuration for alias 'invalid' not found");

        S3::singleton()->getConfig('invalid');
    }

    public function testHasConfigMethod()
    {
        $s3 = S3::singleton();

        $this->assertFalse($s3->hasConfig('test'));

        $s3->use('test', [
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ]);

        $this->assertTrue($s3->hasConfig('test'));
    }

    public function testRemoveConfigMethod()
    {
        $s3 = S3::singleton();
        $config = [
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ];

        $s3->use('test', $config);
        $this->assertTrue($s3->hasConfig('test'));

        $result = $s3->removeConfig('test');
        $this->assertSame($s3, $result);
        $this->assertFalse($s3->hasConfig('test'));
    }

    public function testClearConfigsMethod()
    {
        $s3 = S3::singleton();

        $s3->use('test1', [
            'bucket' => 'test-bucket-1',
            'region' => 'us-east-1',
            'access_key' => 'test-key-1',
            'secret_key' => 'test-secret-1'
        ]);

        $s3->use('test2', [
            'bucket' => 'test-bucket-2',
            'region' => 'us-east-1',
            'access_key' => 'test-key-2',
            'secret_key' => 'test-secret-2'
        ]);

        $this->assertTrue($s3->hasConfig('test1'));
        $this->assertTrue($s3->hasConfig('test2'));

        $result = $s3->clearConfigs();
        $this->assertSame($s3, $result);
        $this->assertFalse($s3->hasConfig('test1'));
        $this->assertFalse($s3->hasConfig('test2'));
    }

    public function testBucketsMethod()
    {
        $s3 = S3::singleton();

        $this->assertEmpty($s3->buckets());

        $s3->use('test', [
            'bucket' => 'test-bucket',
            'region' => 'us-east-1',
            'access_key' => 'test-key',
            'secret_key' => 'test-secret'
        ]);

        $s3->bucket('test'); // Criar instância do bucket

        $buckets = $s3->buckets();
        $this->assertArrayHasKey('test', $buckets);
        $this->assertInstanceOf(Bucket::class, $buckets['test']);
    }
}
