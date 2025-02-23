<?php

namespace AWP\IO;

/**
 * Manages the image optimization process for WordPress media library images.
 *
 * This class coordinates the optimization workflow between image fetching,
 * optimization processing, and tracking. It handles both batch and single image
 * optimization processes, including WebP conversion and image resizing.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class OptimizationManager extends Singleton
{
    /**
     * Image fetcher service instance.
     *
     * @var ImageFetcher
     * @since 1.0.0
     */
    private $fetcher;

    /**
     * Image sender service instance.
     *
     * @var ImageSender
     * @since 1.0.0
     */
    private $sender;

    /**
     * Image tracker service instance.
     *
     * @var ImageTracker
     * @since 1.0.0
     */
    private $tracker;

    /**
     * Counter for processed images in current batch.
     *
     * @var int
     * @since 1.0.0
     */
    private $processed_count = 0;

    /**
     * Constructor. Initializes required service instances.
     *
     * @since 1.0.0
     * @param ImageFetcher $fetcher Image fetcher service instance
     * @param ImageSender $sender Image sender service instance
     * @param ImageTracker $tracker Image tracker service instance
     */
    public function __construct(ImageFetcher $fetcher, ImageSender $sender, ImageTracker $tracker)
    {
        $this->fetcher = $fetcher;
        $this->sender = $sender;
        $this->tracker = $tracker;
    }

    /**
     * Gets the count of processed images in current batch.
     *
     * @since 1.0.0
     * @return int Number of processed images
     */
    public function get_processed_count()
    {
        return $this->processed_count;
    }

    /**
     * Optimizes a batch of unoptimized images from the media library.
     *
     * Processes multiple images in a single batch, handling optimization,
     * WebP conversion, and metadata updates for each image and its thumbnails.
     *
     * @since 1.0.0
     * @return array Array of optimization results for each processed image
     */
    public function optimize_batch($re_optimize = false)
    {
        $attachment_ids = $this->fetcher->get_unoptimized_images($re_optimize);
        $results = [];

        if (!empty($attachment_ids)) {
            foreach ($attachment_ids as $attachment_id) {
                try {
                    $images = $this->fetcher->get_attachment_images($attachment_id);
                    $optimization_results = $this->sender->send_images($attachment_id, $images);

                    if (isset($optimization_results[0]['error'])) {
                        //error_log("Image optimization failed for ID {$attachment_id}: " . $optimization_results[0]['error']);
                        /*return [
                            'id' => $attachment_id,
                            'status' => 'error',
                            'message' => $optimization_results[0]['error']
                        ];*/

                        //throw new \Exception("Image optimization failed for ID {$attachment_id}: " . $optimization_results[0]['error']);
                    }

                    // If backup already exist thene skip backup creation during re-optimization
                    if ($re_optimize && $this->tracker->backup_exists($attachment_id) === false) {
                        $this->tracker->create_backup($attachment_id);
                    }

                    if (!$re_optimize) {
                        $this->tracker->create_backup($attachment_id);
                    }

                    $this->process_optimization_results($attachment_id, $optimization_results);
                    $this->processed_count++;

                    // Track processed image during re-optimization
                    if ($re_optimize) {
                        $this->tracker->track_processed_image_for_reoptimization($attachment_id);
                    }

                    $results[] = [
                        'id' => $attachment_id,
                        'status' => 'success',
                        'message' => 'Images optimized successfully',
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'id' => $attachment_id,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    error_log("Image optimization failed for ID {$attachment_id}: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Resets optimization data for images that previously failed optimization.
     *
     * Retrieves a list of attachment IDs for images that failed optimization,
     * and clears their existing optimization data to allow for re-attempting
     * the optimization process.
     *
     * @since 1.1.2
     * @return void This method does not return any value.
     */
    public function reset_failed_optimiztions_data()
    {
        $attachment_ids = $this->fetcher->get_failed_optimized_images();
        if (!empty($attachment_ids)) {
            foreach ($attachment_ids as $attachment_id) {
                if ($this->tracker->should_restore($attachment_id) === true && $this->tracker->backup_exists($attachment_id) === true) {
                    $this->tracker->restore_image($attachment_id);
                    error_log('reset_failed_optimiztions_data() : restore_image called');
                }
                $this->tracker->clear_existing_optimization_data($attachment_id);
            }
        }
    }

    /**
     * Optimizes a single image from the media library.
     *
     * Processes a specific image attachment, handling optimization,
     * WebP conversion, and metadata updates for the image and its thumbnails.
     *
     * @since 1.0.0
     * @param int $attachment_id WordPress attachment ID to optimize
     * @param int $reoptimize Whether attempt to re-optimize image or not 
     * @return array Optimization result containing status and message
     */
    public function optimize_single_image($attachment_id, $re_optimize = false)
    {
        try {
            $images = $this->fetcher->get_attachment_images($attachment_id);

            // If backup already exist thene skip backup creation during re-optimization
            if ($re_optimize && $this->tracker->backup_exists($attachment_id) === false) {
                $this->tracker->create_backup($attachment_id);
            }

            if (!$re_optimize) {
                $this->tracker->create_backup($attachment_id);
            }

            $optimization_results = $this->sender->send_images($attachment_id, $images);

            if (isset($optimization_results[0]['error'])) {
                /*error_log("Image optimization failed for ID {$attachment_id}: " . $optimization_results[0]['error']);
                return [
                    'id' => $attachment_id,
                    'status' => 'error',
                    'message' => $optimization_results[0]['error']
                ];*/
            }

            $this->process_optimization_results($attachment_id, $optimization_results);
            return [
                'id' => $attachment_id,
                'status' => 'success',
                'message' => 'Image optimized successfully',
            ];
        } catch (\Exception $e) {
            error_log("Image optimization failed for ID {$attachment_id}: " . $e->getMessage());
            return [
                'id' => $attachment_id,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Processes optimization results and updates image files and metadata.
     *
     * Handles the optimization results for an image and its thumbnails, including:
     * - Saving optimized images
     * - Creating WebP versions
     * - Updating image dimensions
     * - Converting PNG to JPG if requested
     * - Updating WordPress attachment metadata
     *
     * @since 1.0.0
     * @param int $attachment_id WordPress attachment ID
     * @param array $results Array of optimization results for each image size
     * @return void
     */
    private function process_optimization_results($attachment_id, $results)
    {
        $optimization_data = [];
        $base_path = get_attached_file($attachment_id);
        $upload_dir = dirname($base_path);
        $metadata = wp_get_attachment_metadata($attachment_id);

        if ($metadata === false) {
            $metadata = [];
        }

        if (!isset($metadata['sizes'])) {
            $metadata['sizes'] = [];
        }

        $has_error = false;
        $failed_results = [];

        foreach ($results as $result) {
            $temp_result = $result;
            
            unset($temp_result['optimized_content']);
            unset($temp_result['webp']['content']);
            $failed_results[] = $temp_result;

            if (isset($result['error'])) {
                $has_error = true;
                continue;
            }

            // Calculate stats for this specific image size
            $size_data = [
                'total_saved' => $result['bytes_saved'],
                'total_original' => $result['original_size'],
                'percent_saved' => $result['percent_saved'],
                'image_size' => $result['image_size'],
                'file_name' => $result['file_name'],
                'converted_to_jpg' => $result['converted_to_jpg'] ?? false
            ];

            // Update dimensions if they were changed during resize
            if (isset($result['dimensions'])) {
                $size_data['width'] = $result['dimensions']['width'];
                $size_data['height'] = $result['dimensions']['height'];

                // Update metadata dimensions based on image type
                //if ($result['image_type'] === 'full' || $result['image_type'] === 'original') {
                if ($result['image_type'] === 'full') {
                    // Update main image dimensions & filesize
                    $metadata['width'] = $result['dimensions']['width'];
                    $metadata['height'] = $result['dimensions']['height'];
                    $metadata['filesize'] = $result['optimized_size'];
                } else if ($result['image_type'] === 'original') {;
                } else if (isset($metadata['sizes'][$result['image_size']])) {
                    // Handle thumbnails
                    $metadata['sizes'][$result['image_size']]['width'] = $result['dimensions']['width'];
                    $metadata['sizes'][$result['image_size']]['height'] = $result['dimensions']['height'];
                    $metadata['sizes'][$result['image_size']]['filesize'] = $result['optimized_size'];
                }
            }

            // Handle WebP version if it exists
            if (isset($result['webp'])) {
                $size_data['webp'] = [
                    'file_name' => pathinfo($result['file_name'], PATHINFO_FILENAME) . '.webp',
                    'bytes_saved' => $result['webp']['bytes_saved'],
                    'percent_saved' => $result['webp']['percent_saved'],
                    'size' => $result['webp']['size']
                ];
                // Save WebP file
                $webp_path = $upload_dir . '/' . $size_data['webp']['file_name'];
                file_put_contents($webp_path, base64_decode($result['webp']['content']));
            }

            // Determine paths and save both versions when converted
            if ($result['image_type'] === 'full' || $result['image_type'] === 'original') {
                $file_path = $base_path;

                // For original images, we might need to save to a different path
                if ($result['image_type'] === 'original') {
                    $file_path = $upload_dir . '/' . $result['file_name'];
                }

                if ($result['converted_to_jpg']) {
                    // Save JPG version alongside PNG
                    $jpg_path = preg_replace('/\.(png|PNG)$/', '.jpg', $file_path);
                    file_put_contents($jpg_path, base64_decode($result['optimized_content']));
                    $size_data['jpg_path'] = str_replace(wp_get_upload_dir()['basedir'] . '/', '', $jpg_path);

                    // Update metadata file type if converted to jpg
                    /*if ($result['image_type'] === 'full') {
                        $metadata['file'] = basename($jpg_path);
                        $metadata['mime_type'] = 'image/jpeg';
                    }*/
                } else {
                    // Save optimized original
                    file_put_contents($file_path, base64_decode($result['optimized_content']));
                }
            } else {
                // Handle thumbnails
                $file_path = $upload_dir . '/' . $result['file_name'];
                if ($result['converted_to_jpg']) {
                    // Save JPG version alongside PNG thumbnail
                    $jpg_path = $upload_dir . '/' . preg_replace('/\.(png|PNG)$/', '.jpg', $result['file_name']);
                    file_put_contents($jpg_path, base64_decode($result['optimized_content']));
                    $size_data['jpg_path'] = str_replace(wp_get_upload_dir()['basedir'] . '/', '', $jpg_path);


                    // Update metadata for thumbnail if converted to jpg
                    /*if (isset($metadata['sizes'][$result['image_size']])) {
                        $metadata['sizes'][$result['image_size']]['file'] = basename($jpg_path);
                        $metadata['sizes'][$result['image_size']]['mime_type'] = 'image/jpeg';
                    }*/
                } else {
                    // Save optimized original thumbnail
                    file_put_contents($file_path, base64_decode($result['optimized_content']));
                }
            }

            $optimization_data[] = $size_data;
        }

        // Store optimization data in DB
        $this->tracker->mark_as_optimized($attachment_id, $optimization_data);

        if ($has_error) {
            $this->tracker->mark_as_failed($attachment_id, $failed_results);
        }

        // Update file size in metadata
        //$metadata['filesize'] = filesize($base_path);

        // Add WebP information to metadata if exists
        if (isset($optimization_data[0]['webp'])) {
            $metadata['webp_enabled'] = true;
        }

        // Update the attachment metadata
        wp_update_attachment_metadata($attachment_id, $metadata);

        // Action Hook
        do_action('awp_image_optimization_completed', $attachment_id, $optimization_data);
    }
}
