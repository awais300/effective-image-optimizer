<?php

namespace AWP\IO\CLI;

use AWP\IO\ImageFetcher;
use AWP\IO\ImageSender;
use AWP\IO\ImageTracker;
use AWP\IO\OptimizationManager;
use WP_CLI;

/**
 * Command-line interface for the Image Optimizer plugin.
 *
 * Provides WP-CLI commands for batch optimization of images in the WordPress media library.
 * Supports various options like batch processing, dry runs, and verbose output.
 *
 * @package AWP\IO\CLI
 * @since 1.0.0
 */
class ImageOptimizerCLI
{
    /**
     * Image fetcher service instance.
     *
     * @var ImageFetcher
     * @since 1.0.0
     */
    private $fetcher;

    /**
     * Image sender service instance.
     *
     * @var ImageSender
     * @since 1.0.0
     */
    private $sender;

    /**
     * Image tracker service instance.
     *
     * @var ImageTracker
     * @since 1.0.0
     */
    private $tracker;

    /**
     * Optimization manager instance.
     *
     * @var OptimizationManager
     * @since 1.0.0
     */
    private $optimization_manager;

    /**
     * Total bytes saved across all optimized images.
     *
     * @var int
     * @since 1.0.0
     */
    private $total_saved_bytes = 0;

    /**
     * Count of successfully processed images.
     *
     * @var int
     * @since 1.0.0
     */
    private $processed_images = 0;

    /**
     * Count of failed image optimizations.
     *
     * @var int
     * @since 1.0.0
     */
    private $failed_images = 0;

    /**
     * Whether to show detailed output for each image.
     *
     * @var bool
     * @since 1.0.0
     */
    private $verbose = false;

    /**
     * Constructor. Initializes required service instances.
     *
     * @since 1.0.0
     */
    public function __construct()
    {

        $this->fetcher = ImageFetcher::get_instance();
        $this->sender = ImageSender::get_instance();
        $this->tracker = ImageTracker::get_instance();
    }

    /**
     * Restores optimized images to their original versions
     * 
     * ## OPTIONS
     * 
     * [--all]
     * : Restore all optimized images in the media library.
     * 
     * [--batch-size=<number>]
     * : Optional. Number of images to restore in this batch.
     * 
     * [--dry-run]
     * : Optional. Show how many images would be restored without actually restoring them.
     * 
     * [--verbose]
     * : Optional. Show detailed restoration results for each image.
     * 
     * ## EXAMPLES
     * 
     *     wp awp-io restore --all
     *     Restore all optimized images in the media library.
     * 
     *     wp awp-io restore --all --verbose
     *     Restore all optimized images with detailed progress output.
     * 
     *     wp awp-io restore --batch-size=50
     *     Restore a batch of 50 images.
     * 
     *     wp awp-io restore --batch-size=50 --verbose
     *     Restore a batch of 50 images with detailed progress output.
     * 
     *     wp awp-io restore --dry-run
     *     Show how many images would be restored without actually restoring them.
     * 
     * @when after_wp_load
     */
    public function restore($args, $assoc_args)
    {
        // If no arguments provided, show help
        if (empty($assoc_args)) {
            WP_CLI::runcommand('help awp-io restore');
            return;
        }

        // Set verbose mode
        $this->verbose = isset($assoc_args['verbose']);

        // Get total count of optimized images
        $total_images = $this->fetcher->get_total_optimized_images_count_for_restore();

        if ($total_images === 0) {
            WP_CLI::success('No optimized images found to restore.');
            return;
        }

        // Handle dry run
        if (isset($assoc_args['dry-run'])) {
            WP_CLI::line(sprintf('Found %d optimized images that would be restored.', $total_images));
            return;
        }

        // Check if either --all or --batch-size is provided
        if (!isset($assoc_args['all']) && !isset($assoc_args['batch-size'])) {
            WP_CLI::error('Either --all or --batch-size parameter is required. Use --dry-run to see how many images would be restored.');
            return;
        }

        // Determine how many images to process
        $images_to_process = isset($assoc_args['batch-size'])
            ? min((int)$assoc_args['batch-size'], $total_images)
            : $total_images;

        // Validate batch size
        if (isset($assoc_args['batch-size']) && $images_to_process <= 0) {
            WP_CLI::error('Batch size must be greater than 0.');
            return;
        }

        WP_CLI::line(sprintf('Found %d optimized images.', $total_images));

        if (isset($assoc_args['batch-size'])) {
            WP_CLI::line(sprintf('Will restore %d images in this run.', $images_to_process));
        }

        // Reset counters
        $this->processed_images = 0;
        $this->failed_images = 0;

        // Create progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Restoring images', $images_to_process);
        
        while ($this->processed_images < $images_to_process) {
            $images = $this->fetcher->get_optimized_images_for_restore();
            
            if (empty($images)) {
                break; // No more images to process
            }

            foreach ($images as $attachment_id) {
                if ($this->processed_images >= $images_to_process) {
                    break;
                }

                try {
                    $success = $this->tracker->restore_image($attachment_id);
                    
                    if ($success) {
                        if ($this->verbose) {
                            WP_CLI::line(sprintf('✓ Successfully restored image ID: %d', $attachment_id));
                        }
                        $this->processed_images++;
                        $progress->tick();
                    } else {
                        $this->failed_images++;
                        WP_CLI::warning(sprintf('Failed to restore image ID %d: Backup file not found', $attachment_id));
                    }
                } catch (\Exception $e) {
                    $this->failed_images++;
                    WP_CLI::warning(sprintf('Failed to restore image ID %d: %s', $attachment_id, $e->getMessage()));
                }
            }
        }

        $progress->finish();

        // Display final statistics
        WP_CLI::line("\nRestore Summary:");
        WP_CLI::line(sprintf('Total images processed: %d', $this->processed_images));
        WP_CLI::line(sprintf('Failed restorations: %d', $this->failed_images));

        if ($this->failed_images === 0) {
            WP_CLI::success('Image restoration completed successfully!');
        } else {
            WP_CLI::warning(sprintf(
                'Image restoration completed with %d failures. Check the logs for more details.',
                $this->failed_images
            ));
        }

        // Show remaining images message if applicable
        $remaining = $total_images - $this->processed_images;
        if ($remaining > 0 && !isset($assoc_args['all'])) {
            WP_CLI::line(sprintf(
                'There are still %d optimized images remaining. Run the command again to process more.',
                $remaining
            ));
        }
    }

    /**
     * Optimizes images in the media library
     * 
     * ## OPTIONS
     * 
     * [--all]
     * : Optimize all unoptimized images in the media library.
     * 
     * [--batch-size=<number>]
     * : Optional. Number of images to process in this batch.
     * 
     * [--dry-run]
     * : Optional. Show how many images would be optimized without actually optimizing them.
     * 
     * [--verbose]
     * : Optional. Show detailed optimization results for each image and its thumbnails.
     * 
     * ## EXAMPLES
     * 
     *     wp awp-io optimize --all
     *     Optimize all unoptimized images in the media library.
     * 
     *     wp awp-io optimize --all --verbose
     *     Optimize all unoptimized images with detailed progress output.
     * 
     *     wp awp-io optimize --batch-size=50
     *     Optimize a batch of 50 images.
     * 
     *     wp awp-io optimize --batch-size=50 --verbose
     *     Optimize a batch of 50 images with detailed progress output.
     * 
     *     wp awp-io optimize --dry-run
     *     Show how many images would be optimized without actually optimizing them.
     * 
     * @when after_wp_load
     */
    public function optimize($args, $assoc_args)
    {
        // If no arguments provided, show help
        if (empty($assoc_args)) {
            WP_CLI::runcommand('help awp-io optimize');
            return;
        }

        // Set verbose mode
        $this->verbose = isset($assoc_args['verbose']);

        // Get total count of unoptimized images
        $total_images = $this->fetcher->get_total_unoptimized_count();

        if ($total_images === 0) {
            WP_CLI::success('No unoptimized images found.');
            return;
        }

        // Handle dry run
        if (isset($assoc_args['dry-run'])) {
            WP_CLI::line(sprintf('Found %d unoptimized images that would be processed.', $total_images));
            return;
        }

        // Check if either --all or --batch-size is provided
        if (!isset($assoc_args['all']) && !isset($assoc_args['batch-size'])) {
            WP_CLI::error('Either --all or --batch-size parameter is required. Use --dry-run to see how many images would be optimized.');
            return;
        }

        // Determine how many images to process
        $images_to_process = isset($assoc_args['batch-size'])
            ? min((int)$assoc_args['batch-size'], $total_images)
            : $total_images;

        // Validate batch size
        if (isset($assoc_args['batch-size']) && $images_to_process <= 0) {
            WP_CLI::error('Batch size must be greater than 0.');
            return;
        }

        WP_CLI::line(sprintf('Found %d unoptimized images.', $total_images));

        if (isset($assoc_args['batch-size'])) {
            WP_CLI::line(sprintf('Will process %d images in this run.', $images_to_process));
        }

        $this->optimization_manager = OptimizationManager::get_instance($this->fetcher, $this->sender, $this->tracker);

        // Create progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Optimizing images', $images_to_process);
        
        while ($this->processed_images < $images_to_process) {
            $results = $this->optimization_manager->optimize_batch();

            if (empty($results)) {
                break; // No more images to process
            }

            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    $this->process_optimization_result($result);
                    $progress->tick();
                    $this->processed_images++;
                } else {
                    $this->failed_images++;
                    WP_CLI::warning(sprintf('Failed to optimize image ID %d: %s', $result['id'], $result['message']));
                }
            }
        }

        $progress->finish();

        // Display final statistics
        $this->display_final_stats();

        // Show remaining images message if applicable
        $remaining = $total_images - $this->processed_images;
        if ($remaining > 0 && !isset($assoc_args['all'])) {
            WP_CLI::line(sprintf(
                'There are still %d unoptimized images remaining. Run the command again to process more.',
                $remaining
            ));
        }
    }

    /**
     * Processes the optimization result for a single image.
     *
     * Handles the optimization data for an image and its thumbnails, updating statistics
     * and displaying progress information based on verbose mode setting.
     *
     * @since 1.0.0
     * @param array $result Optimization result data
     * @return void
     */
    private function process_optimization_result($result)
    {
        // Get optimization data from post meta
        $optimization_data = get_post_meta($result['id'], '_awp_io_optimization_data', true);
        
        // In non-verbose mode, only show basic progress
        if (!$this->verbose) {
            foreach ($optimization_data as $data) {
                $this->total_saved_bytes += $data['total_saved'];
                if (isset($data['webp']['bytes_saved'])) {
                    $this->total_saved_bytes += $data['webp']['bytes_saved'];
                }
            }
            return;
        }
        
        // In verbose mode, show detailed information
        WP_CLI::line(sprintf(
            'Optimizing image ID: %s and its respective thumbnails',
            $result['id']
        ));
        
        if (!empty($optimization_data)) {
            foreach ($optimization_data as $data) {
                if (isset($data['total_saved'])) {
                    $this->total_saved_bytes += $data['total_saved'];
                    
                    // Add WebP savings if available
                    $webp_saved = isset($data['webp']['bytes_saved']) ? $data['webp']['bytes_saved'] : 0;
                    $this->total_saved_bytes += $webp_saved;
                    
                    // Output individual image statistics
                    $filename = basename($data['file_name']);
                    $saved_kb = round($data['total_saved'] / 1024, 2);
                    $percent = $data['percent_saved'];
                    
                    $output = sprintf(
                        '  ✓ %s - Saved: %s KB (%s%%)',
                        $filename,
                        $saved_kb,
                        $percent
                    );
                    
                    // Add WebP savings information if available
                    if ($webp_saved > 0) {
                        $webp_saved_kb = round($webp_saved / 1024, 2);
                        $webp_percent = $data['webp']['percent_saved'];
                        $output .= sprintf(
                            ' + WebP: %s KB (%s%%)',
                            $webp_saved_kb,
                            $webp_percent
                        );
                    }
                    
                    WP_CLI::line($output);
                }
            }
        }
    }

    /**
     * Displays final optimization statistics.
     *
     * Shows a summary of the optimization process including total images processed,
     * failed optimizations, and total space saved.
     *
     * @since 1.0.0
     * @return void
     */
    private function display_final_stats()
    {
        // Convert bytes to more readable format
        $saved_size = $this->format_bytes($this->total_saved_bytes);
        WP_CLI::line("\nOptimization Summary:");
        WP_CLI::line(sprintf('Total images processed: %d', $this->processed_images));
        WP_CLI::line(sprintf('Failed optimizations: %d', $this->failed_images));
        WP_CLI::line(sprintf('Total space saved: %s', $saved_size));
        if ($this->failed_images === 0) {
            WP_CLI::success('Image optimization completed successfully!');
        } else {
            WP_CLI::warning(sprintf(
                'Image optimization completed with %d failures. Check the logs for more details.',
                $this->failed_images
            ));
        }
    }

    /**
     * Formats bytes into human readable format.
     *
     * Converts bytes into appropriate size units (GB, MB, KB, or bytes)
     * with proper rounding.
     *
     * @since 1.0.0
     * @param int $bytes Number of bytes to format
     * @return string Formatted size string with units
     */
    private function format_bytes($bytes)
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
