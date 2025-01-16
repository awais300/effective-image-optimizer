<?php

namespace AWP\IO\Admin;

defined('ABSPATH') || exit;

use AWP\IO\TemplateLoader;
use AWP\IO\ImageFetcher;
use AWP\IO\ImageSender;
use AWP\IO\ImageTracker;
use AWP\IO\OptimizationManager;
use AWP\IO\Singleton;
use AWP\IO\Stats\OptimizationStatsManager;
use AWP\IO\Schema;

defined('ABSPATH') || exit;

/**
 * Handles the admin settings page and options management for the Image Optimizer plugin.
 *
 * This class manages all functionality related to the plugin's settings page in the WordPress admin area,
 * including saving and retrieving options, handling AJAX optimization requests, and rendering the settings interface.
 *
 * @package AWP\IO\Admin
 * @since 1.0.0
 */
class ImageOptimizerOptions extends Singleton
{

    /**
     * The template loader instance.
     *
     * @var TemplateLoader
     * @since 1.0.0
     */
    private $loader = null;

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
     * Option name for storing plugin settings in WordPress options table.
     *
     * @var string
     * @since 1.0.0
     */
    private const IMAGE_OPTIMIZER_SETTINGS = 'wpeio_awp_settings';

    /**
     * Default plugin settings.
     *
     * @var array
     * @since 1.0.0
     */
    private $default_settings = array(
        'api_key' => '',
        'thumbnail_compression' => 'no',
        'backup' => 'no',
        'remove_exif' => 'no',
        'resize_large_images' => array(
            'width' => 0,
            'height' => 0,
            'option' => 'cover',
        ),
        'next_gen_images' => 'no',
        'deliver_next_gen_images' => 'no',
        'optimize_media_upload' => 'no',
        'convert_png_to_jpeg' => 'no',
        'exclude_thumbnail_sizes' => array(),
    );

    /**
     * Constructor. Initializes template loader and required service instances.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->loader = TemplateLoader::get_instance();

        $this->fetcher = ImageFetcher::get_instance();
        $this->sender = ImageSender::get_instance();
        $this->tracker = ImageTracker::get_instance();
    }

    /**
     * Initializes WordPress hooks and filters for the admin interface.
     *
     * @since 1.0.0
     * @return void
     */
    public function initialize_hooks()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_save_image_optimizer_options', array($this, 'save_settings'));
        add_action('admin_init', array($this, 'initialize_settings'));
        add_action('wp_ajax_start_optimization', array($this, 'start_optimization'));
    }

    /**
     * Retrieves optimizer settings or a specific setting by name.
     *
     * @since 1.0.0
     * @param string|null $name Optional. Name of the specific setting to retrieve.
     * @return mixed The requested setting value, all settings if no name provided, or WP_Error if setting is invalid.
     */
    public function get_optimizer_settings($name = null)
    {
        $settings = get_option(self::IMAGE_OPTIMIZER_SETTINGS, $this->default_settings);

        if (!is_array($settings)) {
            $settings = $this->default_settings;
        }

        if ($name === null) {
            return $settings;
        }

        $optimizer_settings = $settings[$name] ?? $this->default_settings[$name] ?? new \WP_Error('invalid_setting', sprintf('Invalid setting name: %s', $name));

        if (is_wp_error($optimizer_settings)) {
            wp_die($optimizer_settings->get_error_message());
            exit();
        } else {
            return $optimizer_settings;
        }
    }

    /**
     * Returns the default optimizer settings.
     *
     * @since 1.0.0
     * @return array Array of default settings
     */
    public function get_default_optimizer_settings()
    {
        return $this->default_settings;
    }

    /**
     * Handles the AJAX request to start the optimization process.
     * 
     * Performs security checks, initializes optimization manager, and processes images in batches.
     * Returns JSON response with progress information.
     *
     * @since 1.0.0
     * @return void Sends JSON response and exits
     */
    public function start_optimization()
    {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'start_optimization_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        // Check if re-optimization is enabled
        $re_optimize = (bool) intval($_POST['is_re_optimize']);

        // Get total count based on re-optimization mode
        $total_unoptimized = $this->fetcher->get_total_unoptimized_count($re_optimize);

        if ($total_unoptimized === 0) {
            wp_send_json_success([
                'progress' => 100,
                'message' => 'No images need optimization.',
                'results' => [],
                'is_complete' => true
            ]);
            return;
        }

        $this->optimization_manager = OptimizationManager::get_instance($this->fetcher, $this->sender, $this->tracker);
        
        // Process current batch
        $results = $this->optimization_manager->optimize_batch($re_optimize);
        $processed_count = $this->optimization_manager->get_processed_count();

        // Recalculate total as it may have changed
        $remaining_unoptimized = $this->fetcher->get_total_unoptimized_count($re_optimize);
        $total_processed = $total_unoptimized - $remaining_unoptimized;

        // Calculate progress
        $progress = min(100, round(($total_processed / $total_unoptimized) * 100));

        // Determine if we're done
        $is_complete = $remaining_unoptimized === 0;


        // Clear processed IDs if re-optimization is complete
        if ($re_optimize && $is_complete) {
            error_log('TRUNCATing new');
            global $wpdb;
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}" . Schema::REOPTIMIZATION_TABLE_NAME);
        }

        wp_send_json_success([
            'progress' => $progress,
            're_optimize_mode' => $re_optimize,
            'message' => $is_complete ? 'Optimization complete!' : "Optimizing images...",
            'results' => $results,
            'is_complete' => $is_complete,
            'total_unoptimized' => $remaining_unoptimized,
            'processed_count' => $processed_count
        ]);
    }

    /**
     * Initializes plugin settings with defaults if not already set.
     *
     * @since 1.0.0
     * @return void
     */
    function initialize_settings()
    {
        if (false === get_option(self::IMAGE_OPTIMIZER_SETTINGS)) {
            update_option(self::IMAGE_OPTIMIZER_SETTINGS, $this->default_settings);
        }
    }

    /**
     * Registers the plugin settings page in WordPress admin menu.
     *
     * @since 1.0.0
     * @return void
     */
    function admin_menu()
    {
        add_options_page(
            __('Image Optimizer Settings', 'text-domain'),
            __('Image Optimizer', 'text-domain'),
            'manage_options',
            'image-optimizer',
            array($this, 'settings_page')
        );
    }

    /**
     * Renders the settings page template with current settings data.
     *
     * @since 1.0.0
     * @return void
     */
    function settings_page()
    {
        $settings = get_option(self::IMAGE_OPTIMIZER_SETTINGS);
        $data     = array(
            'settings' => $settings,
            'has_unoptimized_images' => $this->fetcher->has_unoptimized_images(),
            'stats' => (OptimizationStatsManager::get_instance())->get_total_stats()
        );

        $this->loader->get_template(
            'image-optimizer-settings.php',
            $data,
            EIP_CUST_PLUGIN_DIR_PATH . '/templates/admin/',
            true
        );
    }

    /**
     * Processes and saves the submitted settings form data.
     * 
     * Validates nonce, user capabilities, and sanitizes all input values before saving.
     *
     * @since 1.0.0
     * @return void
     */
    function save_settings()
    {
        if (!isset($_POST['image_optimizer_nonce']) || !wp_verify_nonce($_POST['image_optimizer_nonce'], 'save_image_optimizer_options')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = array(
            'api_key'                   => sanitize_text_field($_POST['api_key']),
            'thumbnail_compression'     => $this->sanitize_yes_no($_POST['thumbnail_compression']),
            'backup'                    => $this->sanitize_yes_no($_POST['backup']),
            'remove_exif'               => $this->sanitize_yes_no($_POST['remove_exif']),
            'resize_large_images'       => array(
                'width'  => intval($_POST['resize_large_images_width']),
                'height' => intval($_POST['resize_large_images_height']),
                'option' => $this->sanitize_cover_contain($_POST['resize_large_images_option']),
            ),
            'next_gen_images'           => $this->sanitize_yes_no($_POST['next_gen_images']),
            'deliver_next_gen_images'   => $this->sanitize_yes_no($_POST['deliver_next_gen_images']),
            'optimize_media_upload'     => $this->sanitize_yes_no($_POST['optimize_media_upload']),
            'convert_png_to_jpeg'       => $this->sanitize_yes_no($_POST['convert_png_to_jpeg']),
            'exclude_thumbnail_sizes'   => array_map('sanitize_text_field', $_POST['exclude_thumbnail_sizes'] ?? array()),
        );

        update_option(self::IMAGE_OPTIMIZER_SETTINGS, $settings);

        wp_redirect(add_query_arg('page', 'image-optimizer', admin_url('options-general.php')));
        exit;
    }

    /**
     * Sanitizes yes/no input values.
     *
     * @since 1.0.0
     * @param string $input Input value to sanitize
     * @return string 'yes' if input is 'yes', 'no' otherwise
     */
    function sanitize_yes_no($input)
    {
        return $input === 'yes' ? 'yes' : 'no';
    }

    /**
     * Sanitizes cover/contain resize option values.
     *
     * @since 1.0.0
     * @param string $input Input value to sanitize
     * @return string 'cover' or 'contain' based on input, defaults to 'cover'
     */
    function sanitize_cover_contain($input)
    {
        return in_array($input, array('cover', 'contain'), true) ? $input : 'cover';
    }
}
