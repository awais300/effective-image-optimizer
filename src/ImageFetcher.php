<?php

namespace AWP\IO;

/**
 * ImageFetcher Class
 *
 * Handles retrieval and management of images from the WordPress media library
 * for optimization purposes. Provides methods to fetch both optimized and
 * unoptimized images in batches.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class ImageFetcher extends Singleton
{
    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $db;

    /**
     * Number of images to process in each batch
     *
     * @var int
     */
    private $batch_size = 1;

    /**
     * Constructor.
     *
     * Initializes the database connection.
     */
    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Get the current batch size setting.
     *
     * @since 1.0.0
     * @return int Current batch size
     */
    public function get_batch_size()
    {
        return $this->batch_size;
    }

    /**
     * Retrieves the total count of unoptimized images that need re-optimization.
     *
     * This method queries the database to count the number of unoptimized images that require re-optimization.
     *
     * @return int The total count of unoptimized images needing re-optimization.
     */
    public function get_total_re_unoptimized_count()
    {
        return (int) $this->db->get_var("
            SELECT COUNT(p.ID)
            FROM {$this->db->posts} p
            INNER JOIN {$this->db->postmeta} opt ON p.ID = opt.post_id AND opt.meta_key = '_awp_io_optimized'
            LEFT JOIN {$this->db->prefix}" . Schema::REOPTIMIZATION_TABLE_NAME . " r ON p.ID = r.image_id
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND r.image_id IS NULL
        ");
    }

    /**
     * Get total count of unoptimized images.
     *
     * Queries the database to count all image attachments that haven't been optimized yet.
     *
     * @since 1.0.0
     * @return int Number of unoptimized images
     */
    public function get_total_unoptimized_count($re_optimize = false)
    {
        if ($re_optimize) {
            return $this->get_total_re_unoptimized_count();
        }

        return (int) $this->db->get_var("
            SELECT COUNT(p.ID)
            FROM {$this->db->posts} p
            LEFT JOIN {$this->db->postmeta} opt ON p.ID = opt.post_id AND opt.meta_key = '_awp_io_optimized'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND opt.meta_value IS NULL
        ");
    }

    /**
     * Retrieves a list of image IDs that need to be reoptimized.
     *
     * This method queries the database to find image IDs that require reoptimization based on certain criteria.
     *
     * @return array List of image IDs to be reoptimized.
     */
    public function get_reoptimize_images()
    {
        return $this->db->get_col($this->db->prepare(
            "SELECT p.ID
            FROM {$this->db->posts} p
            INNER JOIN {$this->db->postmeta} opt ON p.ID = opt.post_id AND opt.meta_key = '_awp_io_optimized'
            LEFT JOIN {$this->db->prefix}" . Schema::REOPTIMIZATION_TABLE_NAME . " r ON p.ID = r.image_id
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND r.image_id IS NULL
            ORDER BY p.ID ASC
            LIMIT %d",
            $this->batch_size
        ));
    }

    /**
     * Get next batch of unoptimized images.
     *
     * Retrieves a batch of image attachments that haven't been optimized,
     * limited by the batch size setting.
     *
     * @since 1.0.0
     * @return array Array of attachment IDs for unoptimized images
     */
    public function get_unoptimized_images($re_optimize = false)
    {
        if ($re_optimize) {
            return $this->get_reoptimize_images();
        }

        $query = $this->db->prepare(
            "SELECT p.ID 
            FROM {$this->db->posts} p 
            LEFT JOIN {$this->db->postmeta} opt 
                ON p.ID = opt.post_id 
                AND opt.meta_key = '_awp_io_optimized' 
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%' 
            AND opt.meta_value IS NULL 
            ORDER BY p.ID ASC
            LIMIT %d",
            $this->batch_size
        );

        return $this->db->get_col($query);
    }

    /**
     * Get next batch of optimized images.
     *
     * Retrieves a batch of image attachments that have been optimized,
     * limited by the batch size setting.
     *
     * @since 1.0.0
     * @return array Array of attachment IDs for optimized images
     */
    public function get_optimized_images()
    {
        $query = $this->db->prepare(
            "SELECT p.ID 
            FROM {$this->db->posts} p 
            INNER JOIN {$this->db->postmeta} opt 
                ON p.ID = opt.post_id 
                AND opt.meta_key = '_awp_io_optimized' 
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%' 
            AND opt.meta_value = '1'
            ORDER BY p.ID ASC
            LIMIT %d",
            $this->batch_size
        );

        return $this->db->get_col($query);
    }

    /**
     * Get total number of optimized images.
     *
     * Counts all image attachments that have been successfully optimized.
     *
     * @since 1.0.0
     * @return int Number of optimized images
     */
    public function get_total_optimized_images_count()
    {
        return (int) $this->db->get_var("
            SELECT COUNT(p.ID)
            FROM {$this->db->posts} p
            INNER JOIN {$this->db->postmeta} opt 
                ON p.ID = opt.post_id 
                AND opt.meta_key = '_awp_io_optimized'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
        ");
    }

    /**
     * Get next batch of optimized images for restore.
     *
     * Retrieves a batch of image attachments that have been optimized and do not have the _awp_io_restore_attempt meta key,
     * limited by the batch size setting.
     *
     * @since 1.0.0
     * @return array Array of attachment IDs for optimized images without _awp_io_restore_attempt
     */
    public function get_optimized_images_for_restore()
    {
        $query = $this->db->prepare(
            "SELECT p.ID 
            FROM {$this->db->posts} p 
            INNER JOIN {$this->db->postmeta} opt 
                ON p.ID = opt.post_id 
                AND opt.meta_key = '_awp_io_optimized' 
            LEFT JOIN {$this->db->postmeta} restore 
                ON p.ID = restore.post_id 
                AND restore.meta_key = '_awp_io_restore_attempt'
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%' 
            AND opt.meta_value = '1'
            AND restore.meta_id IS NULL
            ORDER BY p.ID ASC
            LIMIT %d",
            $this->batch_size
        );

        return $this->db->get_col($query);
    }

    /**
     * Get total number of optimized images that have not been attempted for restore.
     *
     * Counts all image attachments that have been successfully optimized and do not have the _awp_io_restore_attempt meta key.
     *
     * @since 1.0.0
     * @return int Number of optimized images without _awp_io_restore_attempt
     */
    public function get_total_optimized_images_count_for_restore()
    {
        return (int) $this->db->get_var("
            SELECT COUNT(p.ID)
            FROM {$this->db->posts} p
            INNER JOIN {$this->db->postmeta} opt 
                ON p.ID = opt.post_id 
                AND opt.meta_key = '_awp_io_optimized'
            LEFT JOIN {$this->db->postmeta} restore 
                ON p.ID = restore.post_id 
                AND restore.meta_key = '_awp_io_restore_attempt'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND opt.meta_value = '1'
            AND restore.meta_id IS NULL
        ");
    }

    /**
     * Check for existence of unoptimized images.
     *
     * Efficiently checks if there are any remaining unoptimized images
     * in the media library.
     *
     * @since 1.0.0
     * @return bool True if unoptimized images exist, false otherwise
     */
    public function has_unoptimized_images()
    {
        $count = (int) $this->db->get_var("
            SELECT COUNT(p.ID)
            FROM {$this->db->posts} p
            LEFT JOIN {$this->db->postmeta} opt 
                ON p.ID = opt.post_id 
                AND opt.meta_key = '_awp_io_optimized'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND opt.meta_value IS NULL
            LIMIT 1
        ");

        return $count > 0;
    }

    /**
     * Retrieves the total count of images that have failed optimization.
     *
     * Queries the database to find the total number of images that have
     * failed optimization by checking for the presence of the
     * `_awp_io_optimization_failed_data` meta key.
     *
     * @since 1.1.2
     * @return int The total count of images that failed optimization.
     */
    public function get_failed_optimized_images_count()
    {
        $query = $this->db->prepare(
            "SELECT COUNT(p.ID) 
            FROM {$this->db->posts} p 
            INNER JOIN {$this->db->postmeta} fail 
                ON p.ID = fail.post_id 
                AND fail.meta_key = '_awp_io_optimization_failed_data' 
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'"
        );

        return (int) $this->db->get_var($query);
    }

    /**
     * Retrieves the IDs of all image attachments that have failed optimization.
     *
     * This method queries the database for all image attachments where the meta key
     * '_awp_io_optimization_failed_data' exists, indicating that optimization failed.
     * 
     * @since 1.1.2
     * @return array An array of post IDs for image attachments that failed optimization.
     */
    public function get_failed_optimized_images()
    {
        $query = $this->db->prepare(
            "SELECT p.ID 
            FROM {$this->db->posts} p 
            INNER JOIN {$this->db->postmeta} fail 
                ON p.ID = fail.post_id 
                AND fail.meta_key = '_awp_io_optimization_failed_data' 
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%' 
            ORDER BY p.ID ASC"
        );

        return $this->db->get_col($query);
    }

    /**
     * Get all image sizes for a specific attachment.
     *
     * Retrieves paths to all available sizes of an image attachment,
     * including the original, full-size, and generated thumbnails.
     * Thumbnail sizes can be excluded via settings or the 'image_optimizer_excluded_thumbnails' filter.
     *
     * @since 1.0.0
     * @param int $attachment_id WordPress attachment ID
     * @return array Array of image information including paths and size types
     *
     * @filter awp_image_optimizer_excluded_thumbnails Filters the list of excluded thumbnail sizes
     *         @param array $excluded_sizes Array of thumbnail size names to exclude
     *         @param int   $attachment_id  The attachment ID being processed
     * @filter awp_image_optimizer_attachment_images Filters the final array of image data before returning
     *         @param array $images        Array of image data including paths and size types
     *         @param int   $attachment_id The attachment ID being processed
     */
    public function get_attachment_images($attachment_id)
    {
        $images = [];
        $attachment_meta = wp_get_attachment_metadata($attachment_id);
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/';

        // Get both full and original paths
        $full_path = get_attached_file($attachment_id);
        $original_path = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($attachment_id) : null;

        // Add the full-sized image
        $images[] = [
            'path' => $full_path,
            'type' => 'full'
        ];

        // Add original image only if it's different from the full-sized image
        if ($original_path && $original_path !== $full_path) {
            $images[] = [
                'path' => $original_path,
                'type' => 'original'
            ];
        }

        // Check if thumbnail compression is enabled
        $setting_thumbnail_compression = get_optimizer_settings('thumbnail_compression');
        if ($setting_thumbnail_compression === 'no') {
            return apply_filters('awp_image_optimizer_attachment_images', $images, $attachment_id);
        }

        // Get excluded thumbnail sizes from settings
        $excluded_sizes = get_optimizer_settings('exclude_thumbnail_sizes');
        if (!is_array($excluded_sizes)) {
            $excluded_sizes = [];
        }

        // Allow filtering of excluded thumbnail sizes
        $excluded_sizes = apply_filters('awp_image_optimizer_excluded_thumbnails', $excluded_sizes, $attachment_id);

        // Add thumbnails
        if (isset($attachment_meta['sizes'])) {
            foreach ($attachment_meta['sizes'] as $size => $size_info) {
                // Skip if this size is in the excluded list
                if (in_array($size, $excluded_sizes)) {
                    continue;
                }

                $file = $base_dir . dirname($attachment_meta['file']) . '/' . $size_info['file'];
                $images[] = [
                    'path' => $file,
                    'type' => 'thumbnail',
                    'size' => $size
                ];
            }
        }

        // Allow filtering of the final image array
        return apply_filters('awp_image_optimizer_attachment_images', $images, $attachment_id);
    }
}
