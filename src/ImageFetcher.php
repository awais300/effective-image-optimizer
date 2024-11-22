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
     * Get total count of unoptimized images.
     *
     * Queries the database to count all image attachments that haven't been optimized yet.
     *
     * @since 1.0.0
     * @return int Number of unoptimized images
     */
    public function get_total_unoptimized_count()
    {
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
     * Get next batch of unoptimized images.
     *
     * Retrieves a batch of image attachments that haven't been optimized,
     * limited by the batch size setting.
     *
     * @since 1.0.0
     * @return array Array of attachment IDs for unoptimized images
     */
    public function get_unoptimized_images()
    {
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
     * Get all image sizes for a specific attachment.
     *
     * Retrieves paths to all available sizes of an image attachment,
     * including the original, full-size, and generated thumbnails.
     *
     * @since 1.0.0
     * @param int $attachment_id WordPress attachment ID
     * @return array Array of image information including paths and size types
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
            return $images;
        }

        // Add thumbnails
        if (isset($attachment_meta['sizes'])) {
            foreach ($attachment_meta['sizes'] as $size => $size_info) {
                // Skip if this is the scaled size and we already have the original
                /*if ($size === 'scaled' && $original_path) {
                    continue;
                }*/

                $file = $base_dir . dirname($attachment_meta['file']) . '/' . $size_info['file'];
                $images[] = [
                    'path' => $file,
                    'type' => 'thumbnail',
                    'size' => $size
                ];
            }
        }

        return $images;
    }
}
