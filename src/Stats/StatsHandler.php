<?php

namespace AWP\IO\Stats;

use AWP\IO\Singleton;

/**
 * Handles statistics processing and AJAX requests for optimization stats.
 * 
 * This class manages the processing of optimization statistics through
 * batch operations and AJAX endpoints. It coordinates with OptimizationStats
 * to track and update image optimization metrics.
 *
 * @package AWP\IO\Stats
 * @since 1.0.0
 */
class StatsHandler extends Singleton {

    /**
     * Constructor. Sets up WordPress hooks for stats processing.
     * 
     * @since 1.0.0
     */
    protected function __construct() {
        add_action('awp_process_stats_batch', [$this, 'process_stats_batch']);
        add_action('wp_ajax_awp_process_stats', [$this, 'ajax_process_stats']);
        add_action('wp_ajax_awp_get_stats_status', [$this, 'ajax_get_stats_status']);
        add_filter('awp_stats_processing_timeout', [$this, 'change_stats_processing_timeout']);
    }

    /**
     * Modifies the timeout for stats processing.
     * 
     * @since 1.0.0
     * @return int Modified timeout value in seconds
     */
    public function change_stats_processing_timeout() {
        return 25;
    }

    /**
     * Processes a batch of optimization statistics.
     * 
     * Delegates the batch processing to OptimizationStats instance.
     * 
     * @since 1.0.0
     */
    public function process_stats_batch() {
        $stats = OptimizationStats::get_instance();
        $stats->process_batch();
    }

    /**
     * Handles AJAX request to initiate stats processing.
     * 
     * Verifies nonce and starts the statistics calculation process.
     * 
     * @since 1.0.0
     */
    public function ajax_process_stats() {
        check_ajax_referer('start_optimization_nonce', 'nonce');
        $stats = OptimizationStats::get_instance();
        $result = $stats->init_stats_calculation();
        wp_send_json_success($result);
    }

    /**
     * Handles AJAX request to get current stats processing status.
     * 
     * Verifies nonce and returns the current status of stats processing.
     * 
     * @since 1.0.0
     */
    public function ajax_get_stats_status() {
        check_ajax_referer('start_optimization_nonce', 'nonce');
        $stats = OptimizationStats::get_instance();
        $status = $stats->get_processing_status();
        wp_send_json_success($status);
    }

    /**
     * Tracks individual image optimization events.
     * 
     * Triggers an action that can be used to track when specific images
     * are optimized, allowing for custom tracking implementations.
     * 
     * @since 1.0.0
     * @param int $attachment_id The ID of the optimized attachment
     */
    public function track_image_optimization($attachment_id) {
        // This could be used to log or trigger specific actions when an image is optimized
        do_action('awp_image_optimization_tracking', $attachment_id);
    }
}