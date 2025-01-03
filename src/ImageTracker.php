<?php

namespace AWP\IO;

use AWP\IO\Stats\OptimizationStatsManager;

/**
 * ImageTracker Class
 *
 * Manages tracking and backup functionality for optimized images.
 * Handles creating backups, restoring original images, and cleaning up
 * when images are deleted from the media library.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class ImageTracker extends Singleton
{
    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $db;

    /**
     * Constructor.
     *
     * Initializes the database connection and sets up hooks for attachment deletion.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;

        add_action('delete_attachment', [$this, 'delete_backup_image']);
    }

    /**
     * Mark an image as optimized.
     *
     * Updates post meta to indicate that an image has been optimized
     * and stores the optimization data.
     *
     * @since 1.0.0
     * @param int   $attachment_id    The ID of the attachment
     * @param array $optimization_data Data about the optimization process
     */
    public function mark_as_optimized($attachment_id, $optimization_data)
    {
        update_post_meta($attachment_id, '_awp_io_optimized', true);
        update_post_meta($attachment_id, '_awp_io_optimization_data', $optimization_data);
    }

    /**
     * Create a backup of the original image.
     *
     * Creates a backup copy of the original image in a dedicated backup directory,
     * maintaining the same directory structure as the original.
     *
     * @since 1.0.0
     * @param int $attachment_id The ID of the attachment to backup
     */
    public function create_backup($attachment_id)
    {
        //$file_path = get_attached_file($attachment_id);
        $file_path = wp_get_original_image_path($attachment_id);
        $uploads_dir = wp_upload_dir();

        // Get the relative path by removing the base upload directory
        $relative_path = str_replace($uploads_dir['basedir'] . '/', '', $file_path);

        // Create backup directory with the same structure
        $backup_base_dir = $uploads_dir['basedir'] . '/awp-io-backups';
        $backup_full_dir = $backup_base_dir . '/' . dirname($relative_path);

        // Create all necessary directories
        wp_mkdir_p($backup_full_dir);

        // Create backup with full path structure
        $backup_path = $backup_base_dir . '/' . $relative_path;
        copy($file_path, $backup_path);

        // Store the relative backup path instead of full path
        $relative_backup_path = 'awp-io-backups/' . $relative_path;
        update_post_meta($attachment_id, '_awp_io_backup_path', $relative_backup_path);
    }

    /**
     * Restore an image from backup.
     *
     * Restores the original image from backup, regenerates thumbnails,
     * and removes optimization meta data.
     *
     * @since 1.0.0
     * @param int $attachment_id The ID of the attachment to restore
     * @return bool True if restore successful, false otherwise
     */
    public function restore_image($attachment_id)
    {
        $relative_backup_path = get_post_meta($attachment_id, '_awp_io_backup_path', true);
        $uploads_dir = wp_upload_dir();

        // Convert relative path to full path
        $backup_path = $uploads_dir['basedir'] . '/' . $relative_backup_path;
        //$current_path = get_attached_file($attachment_id);
        $current_path = wp_get_original_image_path($attachment_id);

        if (file_exists($backup_path)) {
            copy($backup_path, $current_path);
            delete_post_meta($attachment_id, '_awp_io_optimized');
            delete_post_meta($attachment_id, '_awp_io_optimization_data');
            delete_post_meta($attachment_id, '_awp_io_backup_path');
            (OptimizationStatsManager::get_instance())->remove_stats_for_attachment($attachment_id);

            // Regenerate thumbnails
            $metadata = wp_generate_attachment_metadata($attachment_id, $current_path);
            wp_update_attachment_metadata($attachment_id, $metadata);
            return true;
        }
        return false;
    }

    /**
     * Delete backup image when an attachment is deleted.
     *
     * Removes the backup file and associated meta data when an image
     * is deleted from the media library.
     *
     * @since 1.0.0
     * @param int $attachment_id The ID of the attachment being deleted
     */
    public function delete_backup_image($attachment_id)
    {
        // Get the backup path from post meta
        $relative_backup_path = get_post_meta($attachment_id, '_awp_io_backup_path', true);

        // If no backup path exists, return early
        if (empty($relative_backup_path)) {
            return;
        }

        // Get uploads directory information
        $uploads_dir = wp_upload_dir();

        // Convert relative path to full path
        $backup_path = $uploads_dir['basedir'] . '/' . $relative_backup_path;

        // Delete the backup file if it exists
        if (file_exists($backup_path)) {
            wp_delete_file($backup_path);
        }

        // Clean up post meta
        delete_post_meta($attachment_id, '_awp_io_optimized');
        delete_post_meta($attachment_id, '_awp_io_optimization_data');
        delete_post_meta($attachment_id, '_awp_io_backup_path');
    }
}
