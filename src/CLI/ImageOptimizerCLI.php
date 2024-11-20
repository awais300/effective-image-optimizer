<?php

namespace AWP\IO\CLI;

use AWP\IO\ImageFetcher;
use AWP\IO\ImageSender;
use AWP\IO\ImageTracker;
use AWP\IO\OptimizationManager;
use WP_CLI;

class ImageOptimizerCLI
{
    private $fetcher;
    private $sender;
    private $tracker;
    private $optimization_manager;
    private $total_saved_bytes = 0;
    private $processed_images = 0;
    private $failed_images = 0;
    private $verbose = false;

    public function __construct()
    {

        $fetcher = new ImageFetcher();
        $sender = new ImageSender();
        $tracker = new ImageTracker();
        $optimization_manager = new OptimizationManager($fetcher, $sender, $tracker);


        $this->fetcher = $fetcher;
        $this->sender = $sender;
        $this->tracker = $tracker;
        $this->optimization_manager = $optimization_manager;
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
