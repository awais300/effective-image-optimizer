# Effective Image Optimizer

A comprehensive WordPress plugin for intelligent image optimization, WebP conversion, and media library management.

## Features
- Batch optimization of WordPress media library
- WebP conversion with browser detection
- PNG to JPG conversion
- Backup/Restore functionality
- Optimization statistics tracking
- WP-CLI integration
- Bulk restore original images
- Statistics Dashboard - Track savings and conversions
- Automatic optimization on upload
- Automatic conversion (PNG, JPG and GIF) to WebP before upload.
- Next-gen image delivery
- Image EXIF removal
- Image Resizing - Maintain aspect ratio (cover/contain)

## Core Components

### Database Schema (Schema Class)
**Namespace:** `AWP\IO`
- Manages database tables for optimization history
- Handles table creation/truncation
- Tables: 
  - Optimization history (savings, conversions)
  - Reoptimization tracking

### Image Tracking & Backup (ImageTracker)
**Namespace:** `AWP\IO`
- Manages image backups in dedicated directory
- Handles restoration of original images
- Tracks optimization status through post meta
- Cleans up optimized files and metadata
- Integrates with stats tracking

### Image Management (ImageFetcher)
**Namespace:** `AWP\IO`
- Retrieves optimized/unoptimized images in batches
- Handles media library queries
- Excludes thumbnail sizes based on settings
- Provides progress tracking for bulk operations

### Optimization Core (OptimizationManager)
**Namespace:** `AWP\IO`
- Coordinates optimization process
- Handles batch processing
- Integrates with ImageSender for API communication
- Manages success/failure tracking
- Supports single image and bulk operations

### WP-CLI Integration
**Namespace:** `AWP\IO\CLI`
- Commands:
  - `wp awp-io optimize`: Bulk optimization
  - `wp awp-io restore`: Bulk restoration
- Features:
  - Dry run mode
  - Verbose output
  - Progress bars
  - Batch size control
  - Re-optimization support
  - Re-try failed optimizations support

### WP-CLI Commands:
WP-CLI Commands
# Optimize all unprocessed images
wp awp-io optimize --all

# Optimize 50 images with verbose output
wp awp-io optimize --batch-size=50 --verbose

# Restore all optimized images
wp awp-io restore --all

# Re-optimize previously processed images
wp awp-io optimize --all --re-optimize

# Retry failed optimizations
wp awp-io optimize --all --retry-failed-only

### Cloudflare Integration
**Namespace:** `AWP\IO`
- Features:
  - Update image on Cloudflare after image optimization.
  - Update image on Cloudflare after image restore.

### Admin Interface (ImageOptimizerOptions)
**Namespace:** `AWP\IO\Admin`
- Settings page with tabs:
  1. General Settings
  2. Advanced Options
  3. Bulk Optimization
  4. Bulk Restore
- AJAX-powered

## Actions & Filters

### Actions
1. **`awp_image_optimization_completed`**  
   - **Parameters:**  
     `$attachment_id` (int), `$optimization_data` (array)  
   - **Description:**  
     Triggered after an image optimization completes. Useful for tracking or extending functionality.

2. **`awp_image_after_optimization_cleanup`**  
   - **Parameters:**  
     `$attachment_id` (int)  
   - **Description:**  
     Triggered after cleanup tasks are performed following image optimization. Useful for additional cleanup or logging.

### Filters
1. **`awp_image_optimizer_excluded_thumbnails`**  
   - **Parameters:**  
     `$excluded_sizes` (array), `$attachment_id` (int)  
   - **Description:**  
     Filters thumbnail sizes excluded from optimization.

2. **`awp_image_optimizer_attachment_images`**  
   - **Parameters:**  
     `$images` (array), `$attachment_id` (int)  
   - **Description:**  
     Filters the list of images/sizes processed for an attachment.

3. **`awp_io_remote_url`**  
   - **Parameters:**  
     `$remote_url` (string)  
   - **Description:**  
     Filters the remote URL used for image optimization requests. Allows customization of the optimization server endpoint.

4. **`awp_io_remote_url_validate_api`**  
   - **Parameters:**  
     `$validate_api_url` (string)  
   - **Description:**  
     Filters the remote URL used for validating the API key. Allows customization of the API key validation endpoint.
