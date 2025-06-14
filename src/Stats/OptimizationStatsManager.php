<?php

namespace AWP\IO\Stats;

use AWP\IO\Singleton;
use AWP\IO\Schema;

/**
 * Class OptimizationStatsManager
 *
 * This class is responsible for managing and tracking optimization statistics for image attachments.
 * It extends the Singleton class to ensure only one instance of the manager is created.
 *
 * @package AWP\IO\Stats
 * @since 1.0.0
 */
class OptimizationStatsManager extends Singleton
{
    /**
     * Constructor. Sets up WordPress hooks for stats processing.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('awp_image_optimization_completed', [$this, 'track_stats'], 10, 2);
        add_action('wp_ajax_awp_io_get_stats', [$this, 'ajax_get_stats']);
    }

    /**
     * Handle AJAX request to fetch stats.
     *
     * @since 1.0.0
     */
    public function ajax_get_stats()
    {
        // Check nonce for security
        check_ajax_referer('start_optimization_nonce', 'nonce');

        // Get the stats
        $stats = $this->get_total_stats();

        // Ensure all values are valid numbers
        $total_normal_savings = isset($stats->total_normal_savings) ? (int) $stats->total_normal_savings : 0;
        $total_webp_savings = isset($stats->total_webp_savings) ? (int) $stats->total_webp_savings : 0;
        $total_webp_conversions = isset($stats->total_webp_conversions) ? (int) $stats->total_webp_conversions : 0;
        $total_png_to_jpg_conversions = isset($stats->total_png_to_jpg_conversions) ? (int)$stats->total_png_to_jpg_conversions : 0;

        // Send the response
        wp_send_json_success([
            'total_normal_savings' => $total_normal_savings,
            'total_webp_savings' => $total_webp_savings,
            'total_webp_conversions' => $total_webp_conversions,
            'total_png_to_jpg_conversions' => $total_png_to_jpg_conversions,
        ]);
    }

    /**
     * Track optimization stats for an attachment.
     *
     * This method calculates and stores the optimization statistics for a given attachment.
     *
     * @param int $attachment_id The attachment ID.
     * @param array $optimization_data The optimization data containing details about the optimization process.
     * @return void
     * @since 1.0.0
     */
    public function track_stats($attachment_id, $optimization_data)
    {
        global $wpdb;

        if (empty($optimization_data)) {
            return;
        }

        // Initialize totals
        $normal_savings = 0;
        $webp_savings = 0;
        $png_to_jpg_conversions = 0;
        $webp_conversions = 0;

        // Calculate totals from optimization data
        foreach ($optimization_data as $size_data) {
            $normal_savings += $size_data['total_saved'] ?? 0;

            if (isset($size_data['webp'])) {
                $webp_savings += $size_data['webp']['bytes_saved'] ?? 0;
                $webp_conversions++;
            }

            if (!empty($size_data['converted_to_jpg'])) {
                $png_to_jpg_conversions++;
            }
        }

        // Insert or update stats in the table using a single query
        $table_name = $wpdb->prefix . Schema::HISTORY_TABLE_NAME;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (attachment_id, normal_savings, webp_savings, png_to_jpg_conversions, webp_conversions)
             VALUES (%d, %d, %d, %d, %d)
             ON DUPLICATE KEY UPDATE
             normal_savings = normal_savings + VALUES(normal_savings),
             webp_savings = webp_savings + VALUES(webp_savings),
             png_to_jpg_conversions = png_to_jpg_conversions + VALUES(png_to_jpg_conversions),
             webp_conversions = webp_conversions + VALUES(webp_conversions)",
            $attachment_id,
            $normal_savings,
            $webp_savings,
            $png_to_jpg_conversions,
            $webp_conversions
        ));
    }

    /**
     * Get optimization stats for a specific attachment.
     *
     * This method retrieves the optimization statistics for a given attachment ID.
     *
     * @param int $attachment_id The attachment ID.
     * @return array An array of optimization stats for the attachment.
     * @since 1.0.0
     */
    public function get_stats_for_attachment($attachment_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . Schema::HISTORY_TABLE_NAME;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE attachment_id = %d ORDER BY optimized_at DESC",
                $attachment_id
            )
        );
    }

    /**
     * Remove optimization stats for a specific attachment.
     *
     * This method deletes all optimization statistics associated with the given attachment ID.
     *
     * @param int $attachment_id The attachment ID for which stats should be removed.
     * @return int|false The number of rows deleted, or false on error.
     * @since 1.0.0
     */
    public function remove_stats_for_attachment($attachment_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . Schema::HISTORY_TABLE_NAME;

        return $wpdb->delete(
            $table_name,
            ['attachment_id' => $attachment_id],
            ['%d'] // Specifies the format of the attachment_id (integer)
        );
    }

    /**
     * Get total optimization stats across all attachments.
     *
     * This method retrieves the cumulative optimization statistics for all attachments.
     *
     * @return object|null An object containing the total optimization stats, or null if no stats are found.
     * @since 1.0.0
     */
    public function get_total_stats()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . Schema::HISTORY_TABLE_NAME;

        return $wpdb->get_row(
            "SELECT 
                SUM(normal_savings) AS total_normal_savings,
                SUM(webp_savings) AS total_webp_savings,
                SUM(png_to_jpg_conversions) AS total_png_to_jpg_conversions,
                SUM(webp_conversions) AS total_webp_conversions
             FROM {$table_name}"
        );
    }
}
