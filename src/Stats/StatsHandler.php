<?php

namespace AWP\IO\Stats;


use AWP\IO\Singleton;

class StatsHandler extends Singleton {
    protected function __construct() {
        add_action('awp_process_stats_batch', [$this, 'process_stats_batch']);
        add_action('wp_ajax_awp_process_stats', [$this, 'ajax_process_stats']);
        add_action('wp_ajax_awp_get_stats_status', [$this, 'ajax_get_stats_status']);
        
        // Remove direct cache invalidation
        // Instead, let the OptimizationStats handle the processing of new images
        // 
    }

    public function process_stats_batch() {
        $stats = OptimizationStats::get_instance();
        $stats->process_batch();
    }

    public function ajax_process_stats() {
        check_ajax_referer('start_optimization_nonce', 'nonce');
        $stats = OptimizationStats::get_instance();
        $result = $stats->init_stats_calculation();
        wp_send_json_success($result);
    }

    public function ajax_get_stats_status() {
        check_ajax_referer('start_optimization_nonce', 'nonce');
        $stats = OptimizationStats::get_instance();
        $status = $stats->get_processing_status();
        wp_send_json_success($status);
    }

    // Optional: If you want to keep track of when images are optimized
    public function track_image_optimization($attachment_id) {
        // This could be used to log or trigger specific actions when an image is optimized
        do_action('awp_image_optimization_tracking', $attachment_id);
    }
}