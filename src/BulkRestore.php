<?php

namespace AWP\IO;

/**
 * BulkRestore Class
 *
 * Handles bulk restoration of optimized images back to their original state.
 * Manages the restoration process, progress tracking, and AJAX interactions.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class BulkRestore extends Singleton
{
    /**
     * Instance of ImageFetcher class
     *
     * @var ImageFetcher
     */
    private $fetcher;

    /**
     * Instance of ImageTracker class
     *
     * @var ImageTracker
     */
    private $tracker;

    /**
     * Transient key for storing bulk restore progress
     *
     * @var string
     */
    private $transient_key = 'bulk_restore_total_images';

    /**
     * Constructor.
     *
     * Initializes required dependencies and sets up WordPress hooks.
     */
    public function __construct()
    {
        $this->fetcher = ImageFetcher::get_instance();
        $this->tracker = ImageTracker::get_instance();

        add_action('wp_ajax_start_bulk_restore', [$this, 'handle_bulk_restore']);
        add_action('wp_ajax_init_bulk_restore', [$this, 'init_bulk_restore']);
    }

    /**
     * Initialize bulk restore process.
     *
     * Sets up the initial state for bulk restore operation by counting
     * total optimized images and storing the count in a transient.
     *
     * @since 1.0.0
     * @return void
     */
    public function init_bulk_restore()
    {
        check_ajax_referer('start_optimization_nonce', 'nonce');

        // Get initial total of optimized images
        $total_optimized = $this->fetcher->get_total_optimized_images_count_for_restore();

        // Store this number for progress calculation
        set_transient($this->transient_key, $total_optimized, HOUR_IN_SECONDS);

        wp_send_json_success([
            'total_images' => $total_optimized
        ]);
    }

    /**
     * Handle bulk restore AJAX request.
     *
     * Processes a batch of images for restoration, tracks progress,
     * and returns results to the client.
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_bulk_restore()
    {
        check_ajax_referer('start_optimization_nonce', 'nonce');

        $optimized_images = $this->fetcher->get_optimized_images_for_restore();
        $results = [];
        $is_complete = empty($optimized_images);

        // Get the initial total from transient
        $initial_total = get_transient($this->transient_key);

        // If transient expired or doesn't exist, get current total
        if (false === $initial_total) {
            $initial_total = $this->fetcher->get_total_optimized_images_count_for_restore();
            set_transient($this->transient_key, $initial_total, HOUR_IN_SECONDS);
        }

        // Get current total of remaining optimized images
        $current_total = $this->fetcher->get_total_optimized_images_count_for_restore();

        // Calculate restored images
        $restored_count = absint($initial_total - $current_total);

        // Calculate progress based on initial total
        $progress = $initial_total > 0 ?
            round(($restored_count / $initial_total) * 100) :
            100;

        foreach ($optimized_images as $attachment_id) {
            $result = [
                'id' => $attachment_id,
                'status' => 'error',
                'message' => ''
            ];

            try {
                if ($this->tracker->restore_image($attachment_id)) {
                    $result['status'] = 'success';
                    $result['message'] = 'Image restored successfully';
                } else {
                    $result['message'] = 'Failed to restore image. Backup file is missing.';
                }
            } catch (\Exception $e) {
                $result['message'] = $e->getMessage();
            }

            $results[] = $result;
        }

        // If complete, clean up the transient
        if ($is_complete) {
            delete_transient($this->transient_key);
        }

        wp_send_json_success([
            'results' => $results,
            'is_complete' => $is_complete,
            'progress' => $progress,
            'restored_count' => $restored_count,
            'initial_total' => $initial_total
        ]);
    }
}
