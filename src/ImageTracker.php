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
        add_action('awp_image_optimization_completed', [$this, 'remove_restore_attempt_meta'], 10, 2);
    }

    /**
     * Marks an attachment as optimized and updates its optimization data.
     * 
     * Updates or appends optimization data for different image sizes while maintaining
     * original file sizes and accumulating total saved bytes across multiple optimizations.
     * 
     * @param int   $attachment_id     WordPress attachment ID
     * @param array $optimization_data Array of optimization data with following structure:
     *                                [
     *                                    'total_saved'     => (int) Bytes saved in optimization
     *                                    'total_original'   => (int) Original file size in bytes
     *                                    'percent_saved'    => (float) Percentage of size reduction
     *                                    'image_size'       => (string) WordPress image size name
     *                                    'file_name'       => (string) Optimized image filename
     *                                    'converted_to_jpg' => (bool) Whether converted to JPG
     *                                ]
     * @return void
     */
    public function mark_as_optimized($attachment_id, $optimization_data)
    {
        //error_log('Initial Optimization Data for Attachment ID ' . $attachment_id . ': ' . print_r(get_post_meta($attachment_id, '_awp_io_optimization_data', true), true));

        update_post_meta($attachment_id, '_awp_io_optimized', true);
        $existing_data = get_post_meta($attachment_id, '_awp_io_optimization_data', true);

        if (empty($existing_data)) {
            update_post_meta($attachment_id, '_awp_io_optimization_data', $optimization_data);
            //error_log('First-time Optimization Data for Attachment ID ' . $attachment_id . ': ' . print_r($optimization_data, true));
            return;
        }

        $updated_data = $existing_data;

        foreach ($optimization_data as $new_item) {
            $existing_item_key = null;
            foreach ($updated_data as $key => $existing_item) {
                if ($existing_item['image_size'] === $new_item['image_size']) {
                    $updated_data[$key]['total_saved'] = $existing_item['total_saved'] + $new_item['total_saved'];
                    $updated_data[$key]['percent_saved'] =
                        round(($updated_data[$key]['total_saved'] / $existing_item['total_original']) * 100, 2);
                    $existing_item_key = $key;
                    break;
                }
            }

            if ($existing_item_key === null) {
                $updated_data[] = $new_item;
            }
        }

        update_post_meta($attachment_id, '_awp_io_optimization_data', $updated_data);
        //error_log('Updated Optimization Data for Attachment ID ' . $attachment_id . ': ' . print_r($updated_data, true));
    }

    /**
     * Clears all existing optimization-related meta data for a given attachment.
     *
     * This method deletes the following meta keys associated with the attachment:
     * - '_awp_io_optimized'
     * - '_awp_io_optimization_data'
     * - '_awp_io_optimization_failed_data'
     *
     * This is useful for resetting or cleaning up optimization data, such as when
     * retrying a failed optimization or removing outdated meta information.
     *
     * @param int $attachment_id The ID of the attachment whose optimization meta data should be cleared.
     * @since 1.1.2
     * @return void
     */
    public function clear_existing_optimization_data($attachment_id)
    {
        delete_post_meta($attachment_id, '_awp_io_optimized');
        delete_post_meta($attachment_id, '_awp_io_optimization_data');
        delete_post_meta($attachment_id, '_awp_io_optimization_failed_data');
    }

    /**
     * Determines whether an attachment should be restored based on its optimization data.
     *
     * This method retrieves the optimization data from the '_awp_io_optimization_data' meta key
     * for the given attachment ID. It checks if the optimization data contains a "success" element.
     * If found, it returns `true` (indicating that restoration is needed). Otherwise, it returns `false`.
     *
     * @param int $attachment_id The ID of the attachment to check.
     * @since 1.1.2
     * @return bool True if the attachment should be restored, false otherwise.
     */
    public function should_restore($attachment_id)
    {
        // Get the optimization data from the post meta
        $optimization_data = get_post_meta($attachment_id, '_awp_io_optimization_data', true);

        if (is_array($optimization_data) && !empty($optimization_data)) {
            foreach ($optimization_data as $data) {
                if (isset($data['success'])) {
                    error_log('should_restore() : found');
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Mark an image as failed.
     *
     * Updates post meta to indicate that an image has been failed to optimize
     * and stores the failure data.
     *
     * @since 1.0.0
     * @param int   $attachment_id    The ID of the attachment
     * @param array $failed_data Data with failed reason
     */
    public function mark_as_failed($attachment_id, $failed_data)
    {
        update_post_meta($attachment_id, '_awp_io_optimization_failed_data', $failed_data);
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
        $setting_backup = get_optimizer_settings('backup');
        if ($setting_backup === 'no') {
            return;
        }
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
        $current_path = wp_get_original_image_path($attachment_id);

        if (is_file($backup_path)) {
            // Restore the original image
            copy($backup_path, $current_path);

            // Delete backup image.
            wp_delete_file($backup_path);

            // Clean up optimization data, files, and meta
            $this->cleanup_optimization_data($attachment_id);

            // Regenerate thumbnails
            $metadata = wp_generate_attachment_metadata($attachment_id, $current_path);
            wp_update_attachment_metadata($attachment_id, $metadata);

            return true;
        }
        // Attempt to restore, this will allow to not to include this attachment repeatedly in query while doing the bulk restore.
        update_post_meta($attachment_id, '_awp_io_restore_attempt', '1');
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
        /*if (empty($relative_backup_path)) {
            return;
        }*/

        // Get uploads directory information
        $uploads_dir = wp_upload_dir();

        // Convert relative path to full path
        $backup_path = $uploads_dir['basedir'] . '/' . $relative_backup_path;

        // Delete the backup file if it exists
        if (is_file($backup_path)) {
            wp_delete_file($backup_path);
        }

        // Clean up optimization data, files, and meta
        $this->cleanup_optimization_data($attachment_id);
    }

    /**
     * Clean up optimization data, files, and meta for an attachment.
     *
     * @param int $attachment_id The ID of the attachment
     */
    private function cleanup_optimization_data($attachment_id)
    {
        // Delete .webp and converted .jpg files
        $this->delete_optimized_files($attachment_id);

        // Clean up post meta
        delete_post_meta($attachment_id, '_awp_io_optimized');
        delete_post_meta($attachment_id, '_awp_io_optimization_data');
        delete_post_meta($attachment_id, '_awp_io_backup_path');

        // Clean up stats
        (OptimizationStatsManager::get_instance())->remove_stats_for_attachment($attachment_id);

        do_action('awp_image_after_optimization_cleanup', $attachment_id);
    }

    /**
     * Delete optimized files (.webp and converted .jpg) for an attachment.
     *
     * @param int $attachment_id The ID of the attachment
     */
    private function delete_optimized_files($attachment_id)
    {
        $optimization_data = get_post_meta($attachment_id, '_awp_io_optimization_data', true);

        if (!empty($optimization_data)) {
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'];

            // Get the partial path from _wp_attached_file
            $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);
            if ($attached_file) {
                // Extract the directory path from the partial path (e.g., '2023/09/')
                $file_directory = dirname($attached_file) . '/';
            } else {
                // Fallback: If _wp_attached_file is not available, assume no subdirectory
                $file_directory = '';
            }

            foreach ($optimization_data as $size_data) {
                // Delete .webp file if it exists
                if (isset($size_data['webp'])) {
                    $webp_path = $base_dir . '/' . $file_directory . $size_data['webp']['file_name'];
                    if (is_file($webp_path)) {
                        wp_delete_file($webp_path);
                    }
                }

                // Delete converted .jpg file if it exists
                if (isset($size_data['jpg_path']) && $size_data['converted_to_jpg']) {
                    $jpg_path = $base_dir . '/' . $file_directory . $size_data['jpg_path'];
                    if (is_file($jpg_path)) {
                        wp_delete_file($jpg_path);
                    }
                }
            }
        }
    }

    /**
     * Track a processed image for reoptimization by inserting its ID into the database.
     *
     * @param int $image_id The ID of the processed image to track for reoptimization.
     */
    public function track_processed_image_for_reoptimization($image_id)
    {

        $this->db->insert(
            $this->db->prefix . Schema::REOPTIMIZATION_TABLE_NAME,
            ['image_id' => $image_id],
            ['%d']
        );
    }

    /**
     * Check if a backup file exists for a given attachment ID.
     *
     * @param int $attachment_id The ID of the attachment to check for backup.
     * @return bool True if backup file exists, false otherwise.
     */
    public function backup_exists($attachment_id)
    {
        $relative_backup_path = get_post_meta($attachment_id, '_awp_io_backup_path', true);
        $uploads_dir = wp_upload_dir();
        $backup_path = $uploads_dir['basedir'] . '/' . $relative_backup_path;

        if (!empty($relative_backup_path) && is_file($backup_path)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Remove the '_awp_io_restore_attempt' meta data for a specific attachment.
     *
     * This function is used to remove the '_awp_io_restore_attempt' meta data associated with a specific attachment ID.
     * This allows for a new restore attempt to be made again.
     *
     * @param int $attachment_id The ID of the attachment to remove the meta data from.
     * @param array $optimization_data Additional data related to the optimization process (not used in this function).
     */
    public function remove_restore_attempt_meta($attachment_id, $optimization_data)
    {
        // If meta exist while doing optimization remove it, so, restore attempt can be made again.
        delete_post_meta($attachment_id, '_awp_io_restore_attempt');
    }
}
