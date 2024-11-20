<?php

namespace AWP\IO;

class OptimizationManager extends Singleton
{
    private $fetcher;
    private $sender;
    private $tracker;
    private $processed_count = 0;

    public function __construct(ImageFetcher $fetcher, ImageSender $sender, ImageTracker $tracker)
    {
        $this->fetcher = $fetcher;
        $this->sender = $sender;
        $this->tracker = $tracker;
    }

    public function get_processed_count()
    {
        return $this->processed_count;
    }

    // Modified to remove offset parameter
    public function optimize_batch()
    {
        $attachment_ids = $this->fetcher->get_unoptimized_images();
        $results = [];

        if (!empty($attachment_ids)) {
            foreach ($attachment_ids as $attachment_id) {
                try {
                    $images = $this->fetcher->get_attachment_images($attachment_id);
                    $this->tracker->create_backup($attachment_id);
                    $optimization_results = $this->sender->send_images($attachment_id, $images);

                    $this->process_optimization_results($attachment_id, $optimization_results);
                    $this->processed_count++;

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
     * Optimize a single image
     *
     * @param int $attachment_id
     * @return array
     */
    public function optimize_single_image($attachment_id)
    {
        try {
            $images = $this->fetcher->get_attachment_images($attachment_id);
            $this->tracker->create_backup($attachment_id);
            $optimization_results = $this->sender->send_images($attachment_id, $images);

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
            error_log("Image optimization failed for ID {$attachment_id}: " . $e->getMessage());
        }
    }

    private function process_optimization_results($attachment_id, $results)
    {
        $optimization_data = [];
        $base_path = get_attached_file($attachment_id);
        $upload_dir = dirname($base_path);
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        foreach ($results as $result) {
            if (isset($result['error'])) {
                continue;
            }
            
            // Calculate stats for this specific image size
            $size_data = [
                'total_saved' => $result['bytes_saved'],
                'total_original' => $result['original_size'],
                'percent_saved' => round($result['percent_saved'], 2),
                'image_size' => $result['image_size'],
                'file_name' => $result['file_name'],
                'converted_to_jpg' => $result['converted_to_jpg'] ?? false
            ];

            // Update dimensions if they were changed during resize
            if (isset($result['dimensions'])) {
                $size_data['width'] = $result['dimensions']['width'];
                $size_data['height'] = $result['dimensions']['height'];
                
                // Update metadata dimensions based on image type
                if ($result['image_type'] === 'full' || $result['image_type'] === 'original') {
                    // Update main image dimensions
                    $metadata['width'] = $result['dimensions']['width'];
                    $metadata['height'] = $result['dimensions']['height'];
                } else {
                    // Update thumbnail dimensions
                    if (isset($metadata['sizes'][$result['image_size']])) {
                        $metadata['sizes'][$result['image_size']]['width'] = $result['dimensions']['width'];
                        $metadata['sizes'][$result['image_size']]['height'] = $result['dimensions']['height'];
                    }
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

        // Update file size in metadata
        $metadata['filesize'] = filesize($base_path);

        // Add WebP information to metadata if exists
        if (isset($optimization_data[0]['webp'])) {
            $metadata['webp_enabled'] = true;
        }

        // Update the attachment metadata
        wp_update_attachment_metadata($attachment_id, $metadata);
    }
}
