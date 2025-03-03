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
class MediaLibraryOptimizer extends Singleton
{
    /**
     * Image fetcher instance for retrieving images.
     *
     * @var ImageFetcher
     * @since 1.0.0
     */
    private $fetcher;

    /**
     * Image sender instance for processing images.
     *
     * @var ImageSender
     * @since 1.0.0
     */
    private $sender;

    /**
     * Image tracker instance for monitoring optimization status.
     *
     * @var ImageTracker
     * @since 1.0.0
     */
    private $tracker;

    /**
     * Optimization manager instance.
     *
     * @var OptimizationManager
     * @since 1.0.0
     */
    private $optimization_manager;

    /**
     * Constructor. Sets up WordPress hooks for media library integration.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->fetcher = ImageFetcher::get_instance();
        $this->sender = ImageSender::get_instance();
        $this->tracker = ImageTracker::get_instance();

        add_action('delete_attachment', [$this, 'delete_backup_image']);

        add_filter('manage_media_columns', [$this, 'add_optimization_column']);
        add_action('manage_media_custom_column', [$this, 'render_optimization_column'], 10, 2);

        add_action('wp_ajax_optimize_single_image', [$this, 'handle_single_image_optimization']);
        add_action('wp_ajax_restore_single_image', [$this, 'handle_image_restore']);

        // Add media modal integration
        add_filter('attachment_fields_to_edit', [$this, 'add_optimization_fields'], 10, 2);
        add_filter('wp_generate_attachment_metadata', [$this, 'optimize_on_new_upload'], 10, 3);
    }

    /**
     * Automatically optimizes newly uploaded images if enabled in settings.
     *
     * @since 1.0.0
     * @param int $attachment_id The ID of the newly uploaded attachment
     * @return array
     */
    public function optimize_on_new_upload($metadata, $attachment_id, $context)
    {
        // Skip optimization if the image is being restored
        if (ImageTracker::is_restoring_image()) {
            return $metadata;
        }

        $setting_optimize_media_upload = get_optimizer_settings('optimize_media_upload');

        if ($setting_optimize_media_upload === 'no') {
            return $metadata;
        }
        // Check if it's an image
        if (!wp_attachment_is_image($attachment_id)) {
            return $metadata;
        }

        $this->optimization_manager = OptimizationManager::get_instance();
        $this->optimization_manager->initialize($this->fetcher, $this->sender, $this->tracker);

        // You could add this to a queue instead of processing immediately
        $result = $this->optimization_manager->optimize_single_image($attachment_id);

        if ($result['status'] === 'error') {
            // Handle error (maybe add to failed queue, notify admin, etc.)
            error_log("Auto-optimization failed for ID {$attachment_id}: " . $result['message']);
        }

        return $metadata;
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
            if ($this->tracker->backup_exists($post->ID)) {
                $html .= '<button type="button" class="button restore-image">' .
                    esc_html__('Restore Original', 'awp-io') .
                    '<span class="spinner"></span>' .
                    '</button>&nbsp;';
            }

            $html .= '<button type="button" class="button reoptimize-image">' .
                esc_html__('ReOptimize Image', 'awp-io') .
                '<span class="spinner"></span>' .
                '</button>';

            // Display error if any.
            if ($failed_message = $this->failed_optimization_exist($post->ID)) {
                $html .= '<span class="tooltip">
                            <span class="tooltip-icon bg-color-orange">!</span>
                            <span class="tooltip-text">' . $failed_message . '</span>
                        </span>';
            }

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
        $this->tracker->delete_backup_image($attachment_id);
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

                <?php // Display error if any 
                ?>
                <?php if ($failed_message = $this->failed_optimization_exist($attachment_id)): ?>
                    <span class="tooltip">
                        <span class="tooltip-icon bg-color-orange">!</span>
                        <span class="tooltip-text"><?php echo $failed_message ?></span>
                    </span>
                <?php endif; ?>

                <?php if ($this->tracker->backup_exists($attachment_id)): ?>
                    <button class="button restore-image">
                        <?php _e('Restore Original', 'awp-io'); ?>
                        <span class="spinner"></span>
                    </button>
                <?php endif; ?>

                <button class="button reoptimize-image">
                    <?php _e('ReOptimize Image', 'awp-io'); ?>
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

        $thumbnails = [];
        $has_webp = false;
        $converted_to_jpg = false;
        $use_webp_savings = true;

        // Variables for first non-thumbnail image stats
        $main_image_stats = null;

        // First pass: check if all sizes have WebP versions
        foreach ($optimization_data as $data) {
            if (!isset($data['webp'])) {
                $use_webp_savings = false;
                break;
            }
        }

        foreach ($optimization_data as $data) {
            // Store stats for the first non-thumbnail image only
            if (empty($data['image_size']) && $main_image_stats === null) {
                $main_image_stats = [
                    'original' => isset($data['total_original']) ? $data['total_original'] : 0,
                    'saved' => $use_webp_savings && isset($data['webp'])
                        ? (isset($data['webp']['bytes_saved']) ? $data['webp']['bytes_saved'] : 0)
                        : (isset($data['total_saved']) ? $data['total_saved'] : 0),
                    'percent' => $use_webp_savings && isset($data['webp'])
                        ? (isset($data['webp']['percent_saved']) ? $data['webp']['percent_saved'] : 0)
                        : (isset($data['percent_saved']) ? $data['percent_saved'] : 0)
                ];
            }

            // Handle thumbnail stats
            if (!empty($data['image_size'])) {
                if ($use_webp_savings && isset($data['webp'])) {
                    $thumbnails[] = [
                        'size' => $data['image_size'],
                        'percent' => isset($data['webp']['percent_saved'])
                            ? $data['webp']['percent_saved']
                            : 0
                    ];
                } else {
                    $thumbnails[] = [
                        'size' => $data['image_size'],
                        'percent' => isset($data['percent_saved'])
                            ? $data['percent_saved']
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

        // Only show stats if we found a main image
        if ($main_image_stats !== null && $main_image_stats['original'] > 0) {
        ?>
            <div class="optimization-stats">
                <div class="reduction-percent">
                    <?php echo sprintf(
                        __('Reduced by %s%%', 'awp-io'),
                        round($main_image_stats['percent'], 2)
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

        try {
            $this->sender->validate_api_key();
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        $attachment_id = intval($_POST['attachment_id']);
        $re_optimize = (bool) intval($_POST['is_re_optimize']);

        // Verify this is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => __('Not a valid image', 'awp-io')]);
            return;
        }


        // Check if image is already optimized
        if ((!$re_optimize) && get_post_meta($attachment_id, '_awp_io_optimized', true)) {
            wp_send_json_error(['message' => __('Image is already optimized', 'awp-io')]);
            return;
        }

        $this->optimization_manager = OptimizationManager::get_instance();
        $this->optimization_manager->initialize($this->fetcher, $this->sender, $this->tracker);

        $result = $this->optimization_manager->optimize_single_image($attachment_id, $re_optimize);

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

        if ($this->tracker->restore_image($attachment_id)) {
            wp_send_json_success([
                'message' => __('Image restored successfully', 'awp-io')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to restore image', 'awp-io')
            ]);
        }
    }

    /**
     * Check if the optimization failed meta key exists and is not empty for a given attachment ID.
     *
     * @param int $attachment_id The attachment ID to check.
     * @since 1.1.3
     * @return bool True if the meta key exists and is not empty, false otherwise.
     */
    public function failed_optimization_exist($attachment_id)
    {
        // Get the meta value for the given meta key and attachment ID
        $meta_value = get_post_meta($attachment_id, '_awp_io_optimization_failed_data', true);

        // Check if the meta value exists and is not empty
        if (!empty($meta_value)) {
            return $this->handle_failed_optimization_data($meta_value);
        }

        return false; // Meta key does not exist or is empty
    }

    /**
     * Handle the failed optimization data and generate a formatted message.
     *
     * @param array $data The error data array.
     * @since 1.1.3
     * @return string
     */
    private function handle_failed_optimization_data($data)
    {
        $message = ''; // Initialize an empty message string

        foreach ($data as $item) {
            $error_message = $item['error']; // Get the error message
            $image_type = $item['image']['type']; // Get the image type

            // Concatenate the message with the desired format
            $message .= "Image Type: {$image_type} - {$error_message}<br/>";
        }

        return $message;
    }
}
