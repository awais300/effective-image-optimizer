<?php

namespace AWP\IO;

/**
 * Manages image optimization functionality in the WordPress Media Library.
 *
 * This class handles the integration of image optimization features into the WordPress
 * Media Library interface, including:
 * - Adding optimization controls to media grid and modal views
 * - Handling AJAX requests for image optimization and restoration
 * - Displaying optimization statistics
 * - Auto-optimizing images on upload
 * - Managing backup images
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class MediaLibraryOptimizer
{

    /**
     * Constructor. Sets up WordPress hooks for media library integration.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('delete_attachment', [$this, 'delete_backup_image']);

        add_filter('manage_media_columns', [$this, 'add_optimization_column']);
        add_action('manage_media_custom_column', [$this, 'render_optimization_column'], 10, 2);

        add_action('wp_ajax_optimize_single_image', [$this, 'handle_single_image_optimization']);
        add_action('wp_ajax_restore_single_image', [$this, 'handle_image_restore']);

        // Add media modal integration
        add_filter('attachment_fields_to_edit', [$this, 'add_optimization_fields'], 10, 2);

        add_action('add_attachment', [$this, 'optimize_on_new_upload']);
    }

    /**
     * Automatically optimizes newly uploaded images if enabled in settings.
     *
     * @since 1.0.0
     * @param int $attachment_id The ID of the newly uploaded attachment
     * @return void
     */
    public function optimize_on_new_upload($attachment_id)
    {
        $setting_optimize_media_upload = get_optimizer_settings('optimize_media_upload');

        if ($setting_optimize_media_upload === 'no') {
            return;
        }
        // Check if it's an image
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }

        $fetcher = new ImageFetcher();
        $sender = new ImageSender();
        $tracker = new ImageTracker();
        $optimization_manager = new OptimizationManager($fetcher, $sender, $tracker);

        // You could add this to a queue instead of processing immediately
        $result = $optimization_manager->optimize_single_image($attachment_id);

        if ($result['status'] === 'error') {
            // Handle error (maybe add to failed queue, notify admin, etc.)
            error_log("Auto-optimization failed for ID {$attachment_id}: " . $result['message']);
        }
    }


    /**
     * Adds optimization fields to the media modal attachment details.
     *
     * Displays optimization controls and statistics in the media modal when
     * viewing image details. Includes buttons for optimization/restoration
     * and displays optimization statistics if the image is already optimized.
     *
     * @since 1.0.0
     * @param array $form_fields Array of form fields for attachment
     * @param WP_Post $post The attachment post object
     * @return array Modified form fields array
     */
    public function add_optimization_fields($form_fields, $post)
    {
        // Only add for images
        if (!wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }

        $is_optimized = get_post_meta($post->ID, '_awp_io_optimized', true);
        $optimization_data = get_post_meta($post->ID, '_awp_io_optimization_data', true);

        // Create the HTML for the optimization controls
        $html = '<div class="optimization-controls" data-id="' . esc_attr($post->ID) . '">';

        if ($is_optimized) {
            $html .= '<button type="button" class="button restore-image">' .
                esc_html__('Restore Original', 'awp-io') .
                '<span class="spinner"></span>' .
                '</button>';

            if ($optimization_data) {
                $html .= '<div class="optimization-stats">' .
                    $this->get_optimization_stats($optimization_data) .
                    '</div>';
            }
        } else {
            $html .= '<button type="button" class="button optimize-image">' .
                esc_html__('Optimize Image', 'awp-io') .
                '<span class="spinner"></span>' .
                '</button>';
        }

        $html .= '</div>';

        // Add custom styles for media modal
        $html .= '
        <style>
            .optimization-controls {
                margin-top: 10px;
            }
            .media-modal .optimization-controls .spinner {
                float: none;
                display: none;
                visibility: visible;
                margin: 0 0 0 5px;
                vertical-align: middle;
            }
            .media-modal .optimization-controls.processing .spinner {
                display: inline-block;
            }
            .media-modal .optimization-stats {
                margin-top: 5px;
                color: #666;
            }
        </style>';

        // Add our field to the form
        $form_fields['awp_io_optimize'] = [
            'label' => __('Image Optimization', 'awp-io'),
            'input' => 'html',
            'html' => $html
        ];

        return $form_fields;
    }

    /**
     * Deletes the backup copy of an image when the attachment is deleted.
     *
     * @since 1.0.0
     * @param int $attachment_id The ID of the attachment being deleted
     * @return void
     */
    public function delete_backup_image($attachment_id)
    {
        $tracker = new ImageTracker();
        $tracker->delete_backup_image($attachment_id);
    }

    /**
     * Adds an optimization status column to the media library grid view.
     *
     * @since 1.0.0
     * @param array $columns Array of column names
     * @return array Modified array of column names
     */
    public function add_optimization_column($columns)
    {
        $columns['image_optimization'] = __('Image Optimization', 'awp-io');
        return $columns;
    }

    /**
     * Renders the content for the optimization status column.
     *
     * Displays optimization controls and statistics for each image in the
     * media library grid view.
     *
     * @since 1.0.0
     * @param string $column_name Name of the current column
     * @param int $attachment_id ID of the current attachment
     * @return void
     */
    public function render_optimization_column($column_name, $attachment_id)
    {
        if ($column_name !== 'image_optimization') {
            return;
        }

        // Check if it's an image
        if (!wp_attachment_is_image($attachment_id)) {
            echo '—';
            return;
        }

        $is_optimized = get_post_meta($attachment_id, '_awp_io_optimized', true);
        $optimization_data = get_post_meta($attachment_id, '_awp_io_optimization_data', true);

?>
        <div class="optimization-controls" data-id="<?php echo esc_attr($attachment_id); ?>">
            <?php if ($is_optimized) : ?>
                <button class="button restore-image">
                    <?php _e('Restore Original', 'awp-io'); ?>
                    <span class="spinner"></span>
                </button>
                <?php if ($optimization_data) : ?>
                    <div class="optimization-stats">
                        <?php echo $this->get_optimization_stats($optimization_data); ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <button class="button optimize-image">
                    <?php _e('Optimize Image', 'awp-io'); ?>
                    <span class="spinner"></span>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Legacy method for getting optimization statistics.
     *
     * @deprecated 1.1.0 Use get_optimization_stats() instead
     * @since 1.0.0
     * @param array $optimization_data Array of optimization data
     * @return string HTML markup for optimization statistics
     */
    private function get_optimization_stats_old($optimization_data)
    {
        if (empty($optimization_data)) {
            return '';
        }

        $total_original = 0;
        $total_saved = 0;
        $thumbnails = [];

        foreach ($optimization_data as $data) {
            $original = isset($data['total_original']) ? $data['total_original'] : 0;
            $saved = isset($data['total_saved']) ? $data['total_saved'] : 0;

            $total_original += $original;
            $total_saved += $saved;

            if (!empty($data['image_size'])) {
                $thumbnails[] = [
                    'size' => $data['image_size'],
                    'percent' => isset($data['percent_saved']) ? round($data['percent_saved'], 2) : 0
                ];
            }
        }

        ob_start();

        // Calculate overall percentage
        if ($total_original > 0) {
            $percent = round(($total_saved / $total_original) * 100, 2);
        ?>
            <div class="optimization-stats">
                <div class="reduction-percent">
                    <?php echo sprintf(__('Reduced by %s%%', 'awp-io'), $percent); ?>
                </div>
                <?php if (!empty($thumbnails)) : ?>
                    <div class="thumbnail-stats" data-thumbnails='<?php echo esc_attr(json_encode($thumbnails)); ?>'>
                        +<?php echo count($thumbnails); ?> thumbnails optimized
                    </div>
                <?php endif; ?>
            </div>
        <?php
        }

        return ob_get_clean();
    }


    /**
     * Generates HTML markup for displaying optimization statistics.
     *
     * Calculates and displays:
     * - Overall size reduction percentage
     * - WebP conversion status and savings
     * - JPEG conversion status
     * - Thumbnail optimization statistics
     *
     * @since 1.1.0
     * @param array $optimization_data Array of optimization data for all image sizes
     * @return string HTML markup for optimization statistics
     */
    private function get_optimization_stats($optimization_data)
    {
        if (empty($optimization_data)) {
            return '';
        }

        $total_original = 0;
        $total_saved = 0;
        $thumbnails = [];
        $has_webp = false;
        $converted_to_jpg = false;
        $webp_total_saved = 0;
        $use_webp_savings = true;  // Flag to determine if we should use WebP savings

        // First pass: check if all sizes have WebP versions
        foreach ($optimization_data as $data) {
            if (!isset($data['webp'])) {
                $use_webp_savings = false;
                break;
            }
        }

        foreach ($optimization_data as $data) {
            $original = isset($data['total_original']) ? $data['total_original'] : 0;
            $total_original += $original;

            if ($use_webp_savings && isset($data['webp'])) {
                // Calculate savings based on WebP
                $webp_saved = isset($data['webp']['bytes_saved']) ? $data['webp']['bytes_saved'] : 0;
                $total_saved += $webp_saved;

                // Update thumbnail stats with WebP percentage
                if (!empty($data['image_size'])) {
                    $thumbnails[] = [
                        'size' => $data['image_size'],
                        'percent' => isset($data['webp']['percent_saved'])
                            ? round($data['webp']['percent_saved'], 2)
                            : 0
                    ];
                }
            } else {
                // Use normal savings
                $saved = isset($data['total_saved']) ? $data['total_saved'] : 0;
                $total_saved += $saved;

                if (!empty($data['image_size'])) {
                    $thumbnails[] = [
                        'size' => $data['image_size'],
                        'percent' => isset($data['percent_saved'])
                            ? round($data['percent_saved'], 2)
                            : 0
                    ];
                }
            }

            // Track if any size has WebP
            if (isset($data['webp'])) {
                $has_webp = true;
            }

            // Track if any size was converted to JPG
            if (!empty($data['converted_to_jpg'])) {
                $converted_to_jpg = true;
            }
        }

        ob_start();

        // Calculate overall percentage
        if ($total_original > 0) {
            $percent = round(($total_saved / $total_original) * 100, 2);
        ?>
            <div class="optimization-stats">
                <div class="reduction-percent">
                    <?php echo sprintf(
                        __('Reduced by %s%%', 'awp-io'),
                        $percent
                    ); ?>
                    <?php if ($use_webp_savings) : ?>
                        <span class="webp-savings">
                            <?php _e('(WebP savings)', 'awp-io'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($converted_to_jpg) : ?>
                    <div class="conversion-info">
                        <?php _e('Converted to JPEG', 'awp-io'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($has_webp) : ?>
                    <div class="webp-info">
                        <?php _e('WebP version created', 'awp-io'); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($thumbnails)) : ?>
                    <div class="thumbnail-stats" data-thumbnails='<?php echo esc_attr(json_encode($thumbnails)); ?>'>
                        +<?php echo count($thumbnails); ?> thumbnails optimized
                    </div>
                <?php endif; ?>
            </div>
<?php
        }

        return ob_get_clean();
    }


    /**
     * Handles AJAX request for single image optimization.
     *
     * Validates the request, checks permissions, and processes the optimization
     * request for a single image attachment.
     *
     * @since 1.0.0
     * @return void Sends JSON response and exits
     */
    public function handle_single_image_optimization()
    {
        check_ajax_referer('start_optimization_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'awp-io')]);
            return;
        }

        $attachment_id = intval($_POST['attachment_id']);

        // Verify this is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => __('Not a valid image', 'awp-io')]);
            return;
        }

        // Check if image is already optimized
        if (get_post_meta($attachment_id, '_awp_io_optimized', true)) {
            wp_send_json_error(['message' => __('Image is already optimized', 'awp-io')]);
            return;
        }

        $fetcher = new ImageFetcher();
        $sender = new ImageSender();
        $tracker = new ImageTracker();
        $optimization_manager = new OptimizationManager($fetcher, $sender, $tracker);


        // Use the new single image optimization method
        $result = $optimization_manager->optimize_single_image($attachment_id);

        if ($result['status'] === 'success') {
            $optimization_data = get_post_meta($attachment_id, '_awp_io_optimization_data', true);
            wp_send_json_success([
                'message' => __('Image optimized successfully', 'awp-io'),
                'stats' => $this->get_optimization_stats($optimization_data)
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message']
            ]);
        }
    }

    /**
     * Handles AJAX request for restoring an optimized image.
     *
     * Validates the request, checks permissions, and processes the restoration
     * of an image to its original, unoptimized state.
     *
     * @since 1.0.0
     * @return void Sends JSON response and exits
     */
    public function handle_image_restore()
    {
        check_ajax_referer('start_optimization_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'awp-io')]);
            return;
        }

        $attachment_id = intval($_POST['attachment_id']);

        $tracker = new ImageTracker();
        if ($tracker->restore_image($attachment_id)) {
            wp_send_json_success([
                'message' => __('Image restored successfully', 'awp-io')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to restore image', 'awp-io')
            ]);
        }
    }
}
