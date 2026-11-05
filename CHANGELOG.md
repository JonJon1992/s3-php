# Changelog

All notable changes to this project will be documented in this file.

## [3.1.0] - 2026-11-05

### Changed
- **PHP Version**: Minimum PHP version lowered from 8.5 to **8.2**. The code only uses PHP 8.0+ features.
- **`getFiles()`**: Now applies `root()` like every other method, and `getFiles()` without a path lists the whole bucket (it used to list nothing).
- **Presigned URLs are no longer cached**: `presignedUrl()`, `getDownloadUrl()` and `fileUrl()` generate a new URL on every call. In long-running processes (queue workers, Octane, Swoole) cached URLs kept being returned after they expired. The public URL cache (`getObjectUrl()`) is now capped at 1000 entries.
- **Endpoint resolution**: The S3 client no longer receives a hard-coded regional endpoint; the SDK resolves it. This fixes China regions (`cn-*`) and keeps SDK features such as dualstack and FIPS available. Public URLs for `cn-*` regions use `amazonaws.com.cn`.
- **`uploadLargeFile()`**: Now applies the default metadata from `setDefaultMetadata()`, like `put()`.

### Fixed
- **`presignedUrl()`** threw `Operation not found: GET` with its default arguments, because the HTTP method was passed straight to `getCommand()`. `GET`, `PUT`, `DELETE` and `HEAD` are now mapped to `GetObject`, `PutObject`, `DeleteObject` and `HeadObject`; SDK operation names are still accepted. This also fixes `fileUrl()` and `s3_presigned_url()`.
- **Header-style parameters were silently dropped**: The SDK ignores unknown keys, so `'Content-Type'`, `'Cache-Control'` and similar keys passed to `put()`, `putFile()`, `putFileStream()`, `uploadMultiple()` or `setDefaultMetadata()` never reached S3. They are now translated to SDK parameters (`ContentType`, `CacheControl`, ...), `x-amz-meta-*` keys go into `Metadata`, and snake_case keys (`response_content_type`) become PascalCase.
- **`putFile()`** stored every file as `application/octet-stream`. The detected content type is now sent, and it no longer overrides a content type passed by the caller.
- **`getDownloadUrl()`** did not set `Content-Disposition`, `Content-Type` or `Cache-Control` (its snake_case defaults were ignored), so the URL did not force a download.
- **`uploadLargeFile()`** discarded metadata: it was passed as `metadata`, an option `MultipartUploader` does not have. It is now sent as `params`.
- **`getObjectUrl()`** removed spaces and non-ASCII characters from the key (`FILTER_SANITIZE_URL`), producing URLs to objects that do not exist (`relatório final.pdf` became `relatriofinal.pdf`). Each path segment is now URL-encoded.
- **`getObjectUrl(..., useCdn: false)`** and `s3_direct_url()` returned the CDN URL whenever a CDN was configured.
- **Custom endpoints** (LocalStack, MinIO): Public URLs were missing the bucket name.
- **`use_path_style`** only affected URL generation; it is now passed to the S3 client.
- **`copy()` / `move()`**: `CopySource` is now URL-encoded (keys with spaces or accents failed). When new metadata is given, the source object's `Content-Type`, `Cache-Control`, `Content-Disposition`, `Content-Encoding` and `Content-Language` are preserved, since S3's `REPLACE` directive resets them.
- **`presignedPostUrl()`**: The policy now includes conditions for `bucket`, `key` and every pre-filled form field. Without them S3 rejects the upload with `Extra input fields`. Conditions given by the caller for the same field are kept.
- **`S3::use()`**: Re-configuring an alias kept returning the previously created `Bucket`.
- **`setCdnUrl()`** did not clear cached URLs. A trailing slash in the `url_cdn` config is now trimmed.
- **`getBucketStats()`**: `size` is always an `int`.

### Removed
- `docker-compose.yml`. It referenced a `Dockerfile` that was never committed.

### Dependencies
- `aws/aws-sdk-php` `^3.402`.
- New `ext-fileinfo` requirement, used by `mime_content_type()`.
- Dev: PHPUnit `^11.5 || ^12.5 || ^13.4`, PHPStan `^2.3`, Rector `^2.8`, PHP_CodeSniffer `^4.0`, Psalm `^7.0`, Infection `^0.29 || ^0.35`. The ranges allow running the suite on PHP 8.2+; the lock file uses the latest versions, which need PHP 8.4+.

### Technical Details
- `phpunit.xml`: Removed `<coverage><report>`. PHPUnit 13 refuses to run without a coverage driver when it is present; `composer test-coverage` still generates the HTML report.
- `psalm.xml`: Added the missing `psalm-baseline.xml` with the existing issues and removed an unused suppression.
- `phpstan.neon`: Ignore the SDK's strict array shapes for `S3Client` arguments in `Bucket` (for example literal `ACL` values), since the library forwards user-provided strings.
- `rector.php`: PHP set lowered to `UP_TO_PHP_82` to match the minimum version.
- `infection.json`: Removed the hard-coded `-d zend_extension=xdebug.so` (it fails when Xdebug is already loaded) and the duplicated `--configuration` option.
- `phpcs.xml`: Removed the contradictory `-n`/`-w` flags.
- `.gitignore`: `composer.lock` is no longer ignored (it was already tracked); added `.psalm/` and `.infection/`.
- Added 25 tests that check the parameters actually sent to the SDK.

## [3.0.0] - 2026-06-23

### Breaking Changes
- **PHP Version**: Minimum PHP version raised from 8.2 to **8.5**
- **Default ACL removed**: `put()`, `putFile()`, `putFileStream()`, `copy()` and `uploadLargeFile()` no longer send `ACL: public-read` automatically. No ACL header is sent unless configured, ensuring compatibility with buckets that have ACLs disabled (Object Ownership "Bucket owner enforced"). Configure `'acl'` in the bucket config, call `setDefaultAcl()`, or pass `['ACL' => ...]` per call to restore previous behavior.
- **`deleteMultiple()` return type**: Now returns `array<int, Aws\Result>` (one per 1000-key batch) instead of a single `Aws\Result`, and automatically chunks lists larger than 1000 keys (the S3 `DeleteObjects` limit).

### Fixed
- **`presignedPostUrl()`**: Rewritten using `Aws\S3\PostObjectV4`. The previous implementation used a non-existent `PostObject` command and did not work. Now returns `url`, `fields` and `attributes`.
- **`fileUrl()`**: Deprecated. It used AWS Signature V2 (decommissioned by AWS and rejected in most regions). It now delegates to `presignedUrl()` (SigV4). Use `presignedUrl()` directly.
- **`getFiles()`**: Fixed a bug where `str_replace` stripped the prefix from anywhere in the key; now strips only the leading prefix.
- **`deleteBucket(force: true)`**: Now paginates object listing, supporting buckets with more than 1000 objects and handling empty buckets safely.
- **URL cache**: Cache keys now include the resolved path (respecting `root()`), preventing stale/incorrect URLs when the root prefix changes.

### Removed
- Unused `hash()` method (SigV2 signing helper) from `Bucket`.
- Unused imports and `defaultClient` property; simplified default alias selection with `array_key_first()`.

### Added
- `'acl'` bucket configuration option and `setDefaultAcl()` / `getDefaultAcl()` methods.
- `$formInputs` parameter and `attributes` return key in `presignedPostUrl()`.
- **Performance / bulk operations**:
  - `getBucketStats()` — returns `['size' => int, 'count' => int]` in a single bucket listing (avoids listing twice via `getBucketSize()` + `getBucketObjectCount()`, which now delegate to it).
  - `uploadMultiple()` / `downloadMultiple()` — concurrent uploads/downloads via `Aws\CommandPool` with configurable concurrency, returning `['results' => ..., 'errors' => ...]` keyed by path.
  - Helper functions `s3_upload_multiple()`, `s3_download_multiple()`, `s3_bucket_stats()`.

### Technical Details
- Updated `composer.json` PHP constraint from `^8.2` to `^8.5`
- Updated dev tooling: PHPUnit `^11.5`, PHPStan `^2.1`, Rector `^2.0`, Psalm `^6.0`, Infection `^0.29`, PHP_CodeSniffer `^3.11`
- Updated `Dockerfile` to `php:8.5-cli` and removed invalid `docker-php-ext-install json openssl` (bundled in PHP 8.x)
- Updated Rector set to `UP_TO_PHP_85`
- Added unit tests covering `Bucket` path/root resolution, URL building, CDN and cache behavior

## [2.4.0] - 2025-08-04

### Changed
- **PHP Version**: Updated minimum PHP version requirement from 8.1 to 8.2 for modern PHP features
- **Performance**: Improved performance using modern PHP 8.2+ features
- **Code Quality**: Enhanced code using arrow functions, array functions, and modern patterns

### Technical Details
- Updated `composer.json` PHP version constraint from `^8.1` to `^8.2`
- Replaced anonymous functions with arrow functions for better performance
- Used `array_sum()` with `array_column()` for better array operations
- Improved string operations using modern PHP functions
- Enhanced code readability and maintainability

## [2.3.0] - 2025-08-04

### Fixed
- **Deprecated Methods**: Replaced deprecated AWS SDK methods with their V2 counterparts
  - `doesBucketExist()` → `doesBucketExistV2()`
  - `doesObjectExist()` → `doesObjectExistV2()`
- **PHP Version**: Updated minimum PHP version requirement from 8.0 to 8.1 for better AWS SDK compatibility

### Changed
- **Dependencies**: Updated AWS SDK PHP requirement to ensure compatibility with latest versions
- **Code Quality**: Fixed code style issues and improved overall code quality

### Technical Details
- Updated `Bucket::isExistBucket()` method to use `doesBucketExistV2($bucket, false)`
- Updated `Bucket::existObject()` method to use `doesObjectExistV2($bucket, $key, false)`
- Updated `composer.json` PHP version constraint from `^8.0` to `^8.1`
- Updated documentation to reflect new PHP version requirement

## [2.2.0] - 2025-08-04

### Added
- **CDN Support**: Integration with external CDNs for better performance
- **URL Caching**: Internal cache for frequently accessed URLs
- **S3 Transfer Acceleration**: Native support for accelerated transfers
- **Specialized Download URLs**: URLs with attachment disposition
- **Direct Upload**: Pre-signed URLs for direct S3 uploads
- **Enhanced Validation**: URL and configuration validation and sanitization
- **Dynamic Endpoints**: Region-optimized endpoints
- **New Helper Functions**: Simpler and more intuitive API

### Performance
- URL caching for repeated requests
- Region-specific endpoints
- S3 Transfer Acceleration for better latency
- Optimized validation and sanitization

### Security
- URL validation for generated URLs
- Parameter sanitization
- Bucket and region configuration validation

### Usability
- More intuitive API with helper functions
- Flexible configuration for different scenarios
- Complete documentation with examples

## [2.1.0] - 2025-08-04

### Added
- **Multiple Bucket Support**: Configure and use multiple S3 buckets simultaneously
- **Enhanced Error Handling**: Better exception handling and error messages
- **Stream Support**: Upload and download files using streams
- **Large File Upload**: Multipart upload support for large files
- **Object Metadata**: Get and set object metadata
- **ACL Management**: Set and get object access control lists
- **Bucket Statistics**: Get bucket size and object count

### Changed
- **Improved API**: More consistent method naming and parameters
- **Better Documentation**: Enhanced examples and usage instructions

## [2.0.0] - 2025-08-04

### Breaking Changes
- **Namespace Change**: Updated namespace structure for better organization
- **Method Signatures**: Updated method signatures for better type safety
- **Configuration**: Simplified configuration system

### Added
- **Type Hints**: Full PHP 8.0+ type hinting support
- **Return Types**: Explicit return types for all methods
- **Modern PHP Features**: Leveraging latest PHP features for better performance

## [1.0.0] - 2025-08-04

### Initial Release
- Basic S3 operations (upload, download, delete)
- Simple configuration system
- Helper functions for common operations
- Basic error handling 