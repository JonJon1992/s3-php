# Changelog

All notable changes to this project will be documented in this file.

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