# Changelog

All notable changes to this project will be documented in this file.

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