<?php

namespace AWP\IO\Stats;

use AWP\IO\ImageFetcher;
use AWP\IO\Singleton;

class OptimizationStats extends Singleton
{
    private $total_optimized_images_count;
    private $fetcher;
    private $db;
    private $batch_size = 2;
    public $task_key = 'awp_stats_processing_task';

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



    // Add a new method to count total new images
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



    /*Seems Not needed*/
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
