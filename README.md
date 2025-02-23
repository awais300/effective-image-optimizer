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
- Automatic optimization on upload
- Next-gen image delivery
- Image resizing and EXIF removal

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
     Triggered after an image optimization completes. Useful for tracking/extension

### Filters
1. **`awp_image_optimizer_excluded_thumbnails`**  
   - **Parameters:**  
     `$excluded_sizes` (array), `$attachment_id` (int)  
   - **Description:**  
     Filter thumbnail sizes excluded from optimization

2. **`awp_image_optimizer_attachment_images`**  
   - **Parameters:**  
     `$images` (array), `$attachment_id` (int)  
   - **Description:**  
     Filter the list of images/sizes processed for an attachment
