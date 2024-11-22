<?php

namespace AWP\IO\Stats;

use AWP\IO\ImageFetcher;
use AWP\IO\Singleton;

/**
 * Class OptimizationStats
 * 
 * Manages and tracks optimization statistics for images in the WordPress media library.
 * Handles batch processing of image statistics and maintains optimization metrics.
 *
 * @package AWP\IO\Stats
 * @since 1.0.0
 */
class OptimizationStats extends Singleton
{
    /** @var int Total count of optimized images in the media library */
    private $total_optimized_images_count;

    /** @var ImageFetcher Instance of the ImageFetcher class */
    private $fetcher;

    /** @var \wpdb WordPress database instance */
    private $db;

    /** @var int Number of images to process in each batch */
    private $batch_size = 2;

    /** @var string Option key for storing the stats processing task */
    public $task_key = 'awp_stats_processing_task';

    /**
     * Constructor.
     * 
     * Initializes the stats manager and sets up WordPress hooks for stats processing.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;

        $this->fetcher = ImageFetcher::get_instance();
        $this->total_optimized_images_count = $this->fetcher->get_total_optimized_images_count();

        add_action('awp_process_stats_batch', [$this, 'process_batch']);
        add_action('wp_ajax_awp_process_stats', [$this, 'ajax_process_stats']);
        add_action('wp_ajax_awp_get_stats_status', [$this, 'ajax_get_stats_status']);
    }

    /**
     * Initializes the statistics calculation process.
     * 
     * Checks for existing tasks and determines whether to continue processing
     * new images or start a fresh calculation.
     *
     * @return array Status information about the calculation process
     */
    public function init_stats_calculation() {
        $task = get_option($this->task_key);
        
        if ($task && $task['status'] === 'completed') {
            $new_images_count = $this->get_new_images_count($task['last_id']);
            
            if ($new_images_count > 0) {
                // Update task to process only new images
                $task['status'] = 'processing';
                $task['total_images'] = $new_images_count;
                $task['processed_count'] = 0;
                $task['existing_stats'] = $task['stats']; // Save existing stats
                $task['stats'] = [
                    'total_webp_savings' => 0,
                    'total_normal_savings' => 0,
                    'webp_conversions' => 0,
                    'png_to_jpg_conversions' => 0
                ];
                $task['started_at'] = time();
                
                update_option($this->task_key, $task);
                wp_schedule_single_event(time(), 'awp_process_stats_batch');
                
                return [
                    'status' => 'processing',
                    'progress' => [
                        'processed' => 0,
                        'total' => $task['total_images'],
                        'percentage' => 0
                    ],
                    'stats' => $task['existing_stats'], // Return existing stats while processing
                    'has_more' => true
                ];
            }
            
            // No new images, return existing stats
            return [
                'status' => 'completed',
                'stats' => $task['stats'],
                'total_optimized' => $task['total_optimized'] ?? $task['total_images']
            ];
        }

        // No existing task, start fresh calculation
        return $this->start_fresh_calculation();
    }

    /**
     * Gets the count of new images since the last processed ID.
     *
     * @param int $last_id The ID of the last processed image
     * @return int Number of new images to process
     */
    private function get_new_images_count($last_id)
    {
        return (int) $this->db->get_var($this->db->prepare(
            "SELECT COUNT(p.ID)
        FROM {$this->db->posts} p
        INNER JOIN {$this->db->postmeta} opt 
            ON p.ID = opt.post_id 
            AND opt.meta_key = '_awp_io_optimized'
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND p.ID > %d",
            $last_id
        ));
    }

    /**
     * Retrieves a batch of new images that need processing.
     *
     * @param int $last_id The ID of the last processed image
     * @return array Array of image objects to process
     */
    private function get_new_images_since($last_id)
    {
        return $this->db->get_results($this->db->prepare(
            "SELECT p.ID 
        FROM {$this->db->posts} p
        INNER JOIN {$this->db->postmeta} opt 
            ON p.ID = opt.post_id 
            AND opt.meta_key = '_awp_io_optimized'
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND p.ID > %d
        ORDER BY p.ID ASC
        LIMIT %d", // Add LIMIT to prevent processing too many images at once
            $last_id,
            $this->batch_size  // Use the existing batch_size property
        ));
    }

    /**
     * Processes a batch of images and updates their statistics.
     * 
     * Handles timeout management and schedules next batch if necessary.
     */
    public function process_batch() {
        $task = get_option($this->task_key);
        if (!$task || $task['status'] !== 'processing') {
            return;
        }

        $start_time = microtime(true);
        $max_execution_time = apply_filters('awp_stats_processing_timeout', 10); // 10 seconds

        $batch = $this->get_new_images_since($task['last_id']);
        
        if (empty($batch)) {
            $this->complete_processing($task);
            return;
        }

        foreach ($batch as $attachment) {
            $this->process_single_image($attachment->ID, $task['stats']);
            $task['processed_count']++;
            $task['last_id'] = $attachment->ID;

            // Check for timeout
            $elapsed_time = microtime(true) - $start_time;
            if ($elapsed_time >= $max_execution_time) {
                update_option($this->task_key, $task);
                wp_schedule_single_event(time(), 'awp_process_stats_batch');
                return;
            }
        }

        update_option($this->task_key, $task);

        // If not all new images processed, schedule next batch
        if ($task['processed_count'] < $task['total_images']) {
            wp_schedule_single_event(time(), 'awp_process_stats_batch');
        } else {
            $this->complete_processing($task);
        }
    }

    /**
     * Legacy method for retrieving image batches.
     * 
     * @deprecated Use get_new_images_since() instead
     * @param int $last_id The ID of the last processed image
     * @return array Array of image objects
     */
    private function get_image_batch($last_id)
    {
        return $this->db->get_results($this->db->prepare(
            "SELECT p.ID 
            FROM {$this->db->posts} p
            INNER JOIN {$this->db->postmeta} opt 
                ON p.ID = opt.post_id 
                AND opt.meta_key = '_awp_io_optimized'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND p.ID > %d
            ORDER BY p.ID ASC
            LIMIT %d",
            $last_id,
            $this->batch_size
        ));
    }

    /**
     * Processes statistics for a single image.
     * 
     * Updates various optimization metrics including WebP conversions
     * and PNG to JPG conversions.
     *
     * @param int   $attachment_id The ID of the attachment to process
     * @param array &$stats        Reference to the stats array to update
     */
    private function process_single_image($attachment_id, &$stats)
    {
        $optimization_data = get_post_meta($attachment_id, '_awp_io_optimization_data', true);

        if (!empty($optimization_data)) {
            foreach ($optimization_data as $image_data) {
                $stats['total_normal_savings'] += isset($image_data['total_saved']) ? (int)$image_data['total_saved'] : 0;

                if (!empty($image_data['webp'])) {
                    $stats['total_webp_savings'] += isset($image_data['webp']['bytes_saved']) ? (int)$image_data['webp']['bytes_saved'] : 0;
                    $stats['webp_conversions']++;
                }

                if (!empty($image_data['converted_to_jpg'])) {
                    $stats['png_to_jpg_conversions']++;
                }
            }
        }
    }

    /**
     * Completes the processing task and finalizes statistics.
     * 
     * Merges existing stats with new ones and updates the completion status.
     *
     * @param array $task The current processing task data
     */
    public function complete_processing($task) {
        // Merge existing stats with new stats
        if (!empty($task['existing_stats'])) {
            $task['stats'] = [
                'total_webp_savings' => 
                    ($task['existing_stats']['total_webp_savings'] ?? 0) + 
                    $task['stats']['total_webp_savings'],
                'total_normal_savings' => 
                    ($task['existing_stats']['total_normal_savings'] ?? 0) + 
                    $task['stats']['total_normal_savings'],
                'webp_conversions' => 
                    ($task['existing_stats']['webp_conversions'] ?? 0) + 
                    $task['stats']['webp_conversions'],
                'png_to_jpg_conversions' => 
                    ($task['existing_stats']['png_to_jpg_conversions'] ?? 0) + 
                    $task['stats']['png_to_jpg_conversions']
            ];
        }

        $task['status'] = 'completed';
        $task['completed_at'] = time();
        $task['total_optimized'] = $this->fetcher->get_total_optimized_images_count();
        
        update_option($this->task_key, $task);
    }

    /**
     * Initiates a fresh statistics calculation task.
     * 
     * Creates a new task with zeroed statistics and schedules the first batch.
     *
     * @return array Initial status of the calculation process
     */
    private function start_fresh_calculation()
    {
        $task = [
            'status' => 'processing',
            'last_id' => 0,
            'total_images' => $this->fetcher->get_total_optimized_images_count(),
            'processed_count' => 0,
            'stats' => [
                'total_webp_savings' => 0,
                'total_normal_savings' => 0,
                'webp_conversions' => 0,
                'png_to_jpg_conversions' => 0
            ],
            'started_at' => time()
        ];

        update_option($this->task_key, $task);
        wp_schedule_single_event(time(), 'awp_process_stats_batch');

        return [
            'status' => 'processing',
            'progress' => [
                'processed' => 0,
                'total' => $task['total_images'],
                'percentage' => 0
            ],
            'stats' => $task['stats']
        ];
    }

    /**
     * Retrieves the current status of statistics processing.
     * 
     * Provides detailed progress information including completion percentage
     * and current statistics.
     *
     * @param array|null $task Optional task data to use instead of fetching from database
     * @return array Status information including progress and current stats
     */
    public function get_processing_status($task = null) {
        if (!$task) {
            $task = get_option($this->task_key);
        }

        if (!$task) {
            return [
                'status' => 'not_started',
                'progress' => [
                    'processed' => 0,
                    'total' => 0,
                    'percentage' => 0
                ],
                'stats' => []
            ];
        }

        $response = [
            'status' => $task['status'],
            'progress' => [
                'processed' => $task['processed_count'] ?? 0,
                'total' => $task['total_images'] ?? 0,
                'percentage' => $task['total_images'] > 0 
                    ? round(($task['processed_count'] / $task['total_images']) * 100) 
                    : 0
            ],
            'stats' => $task['stats'] ?? []
        ];

        // Add completion details
        if ($task['status'] === 'completed') {
            $response['total_optimized'] = $task['total_optimized'] ?? 0;
        }

        // Indicate if more processing is needed
        if ($task['status'] === 'processing' && 
            ($task['processed_count'] ?? 0) < ($task['total_images'] ?? 0)) {
            $response['has_more'] = true;
        }

        return $response;
    }
}
