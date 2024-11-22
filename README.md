# Effective Image Optimizer Documentation

## Overview
The Effective Image Optimizer is a WordPress plugin that provides efficient image optimization and compression capabilities for your media library. It supports WebP conversion, PNG to JPEG conversion, and maintains detailed optimization statistics.

## Requirements
- WordPress 5.0 or higher
- PHP 7.2 or higher

## Installation
1. Upload the plugin files to `/wp-content/plugins/effective-image-optimizer`
2. Activate the plugin through the WordPress plugins screen
3. Configure the plugin settings under Settings > Image Optimizer

## Core Components

### Stats Management

#### OptimizationStats Class
**Namespace:** `AWP\IO\Stats`

Manages and tracks optimization statistics for images in the WordPress media library. Handles batch processing of image statistics and maintains optimization metrics.

**Key Methods:**
- `init_stats_calculation()`: Initializes the statistics calculation process
- `process_batch()`: Processes a batch of images and updates their statistics
- `get_processing_status()`: Retrieves the current status of statistics processing
- `process_single_image()`: Updates various optimization metrics for a single image

#### StatsHandler Class
**Namespace:** `AWP\IO\Stats`

Handles statistics processing and AJAX requests for optimization stats. Coordinates with OptimizationStats to track and update image optimization metrics.

**Key Methods:**
- `process_stats_batch()`: Processes a batch of optimization statistics
- `ajax_process_stats()`: Handles AJAX request to initiate stats processing
- `ajax_get_stats_status()`: Handles AJAX request to get current stats processing status
- `track_image_optimization()`: Tracks individual image optimization events

### Helper Functions

Located in `includes/functions.php`, these utility functions provide easy access to plugin settings and debugging capabilities:

- `get_optimizer_settings($name = null)`: Retrieves optimizer settings
  - Parameters:
    - `$name` (string|null): Optional specific setting name to retrieve
  - Returns: The setting value if name is provided, all settings otherwise

- `get_default_optimizer_settings()`: Gets the default optimizer settings
  - Returns: Array of default optimizer settings

- `dd($mix, $log = 0)`: Debug function to print variables
  - Parameters:
    - `$mix` (mixed): The variable to debug
    - `$log` (bool): Whether to write to error log instead of screen output

## Settings Interface

The plugin provides a comprehensive settings interface with three main sections:

### 1. General Settings
- API Key configuration
- Thumbnail compression options
- Backup settings
- EXIF data removal
- Large image resizing options

### 2. Advanced Settings
- Next Generation Images (WebP) conversion
- WebP delivery settings
- Automatic optimization on upload
- PNG to JPEG conversion
- Thumbnail size exclusions

### 3. Optimization Settings
Provides an interface for:
- Starting batch optimization
- Viewing optimization progress
- Displaying optimization statistics including:
  - WebP savings
  - Normal compression savings
  - Number of WebP conversions
  - Number of PNG to JPG conversions

## Internationalization

The plugin supports translation through WordPress's standard localization system. All user-facing strings are marked for translation using the 'text-domain' domain.

## Actions and Filters

### Actions
- `awp_process_stats_batch`: Triggered to process a batch of statistics
- `awp_image_optimization_tracking`: Triggered when an image is optimized

### Filters
- `awp_stats_processing_timeout`: Modifies the timeout for stats processing

## Security Considerations

The plugin implements several security measures:
- WordPress nonce verification for form submissions
- Capability checks for administrative actions
- Sanitization of input data
- Secure handling of file operations

## Development

### File Structure
```
effective-image-optimizer/
├── assets/
├── docs/
├── includes/
│   └── functions.php
├── languages/
├── src/
│   ├── Stats/
│   │   ├── OptimizationStats.php
│   │   └── StatsHandler.php
│   └── Bootstrap.php
├── templates/
│   └── admin/
│       └── image-optimizer-settings.php
└── effective-image-optimizer.php
```

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License
This plugin is licensed under the GPL v3 or later.
See LICENSE.txt for more information.
