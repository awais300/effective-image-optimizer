<?php

namespace AWP\IO\Admin;

defined('ABSPATH') || exit;

use AWP\IO\TemplateLoader;
use AWP\IO\ImageFetcher;
use AWP\IO\ImageSender;
use AWP\IO\ImageTracker;
use AWP\IO\OptimizationManager;
use AWP\IO\Singleton;

defined('ABSPATH') || exit;

/**
 * Class ImageOptimizerOptions
 * @package AWP\IO\ImageOptimizer
 */
class ImageOptimizerOptions extends Singleton
{

    /**
     * The template loader.
     *
     * @var loader
     */
    private $loader = null;

    private $fetcher;
    private $sender;
    private $tracker;
    private $optimization_manager;

    /**
     * Contains settings.
     *
     * @var IMAGE_OPTIMIZER_SETTINGS
     */
    private const IMAGE_OPTIMIZER_SETTINGS = 'wpeio_awp_settings';

    /**
     * Default settings.
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
     * Construct the ImageOptimizerOptions class.
     */
    public function __construct()
    {
        $this->loader = TemplateLoader::get_instance();

        $this->fetcher = ImageFetcher::get_instance();
        $this->sender = ImageSender::get_instance();
        $this->tracker = ImageTracker::get_instance();
    }

    public function initialize_hooks()
    {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_save_image_optimizer_options', array($this, 'save_settings'));
        add_action('admin_init', array($this, 'initialize_settings'));
        add_action('wp_ajax_start_optimization', array($this, 'start_optimization'));
    }

    /**
     * Retrieve the optimizer settings or a specific setting by name.
     *
     * @param string|null $name Name of the specific setting to retrieve, or null for all settings.
     * @return mixed The requested setting, all settings if no name is provided, or WP_Error if the setting is missing.
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


    public function get_default_optimizer_settings() {
        return $this->default_settings;
    }

    /**
     * Reset settings to defaults.
     *
     * @return bool Whether the option was reset to defaults.
     */
    public function reset_optimizer_settings()
    {
        return update_option(self::IMAGE_OPTIMIZER_SETTINGS, $this->default_settings);
    }


    /**
     * Handle the AJAX request to start the optimization process.
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

        $manager = OptimizationManager::get_instance($this->fetcher, $this->sender, $this->tracker);


        $total_unoptimized = $this->fetcher->get_total_unoptimized_count();

        if ($total_unoptimized === 0) {
            wp_send_json_success([
                'progress' => 100,
                'message' => 'No images need optimization.',
                'results' => [],
                'is_complete' => true
            ]);
            return;
        }

        // Process current batch
        $results = $manager->optimize_batch();
        $processed_count = $manager->get_processed_count();

        // Recalculate total as it may have changed
        $remaining_unoptimized = $this->fetcher->get_total_unoptimized_count();
        $total_processed = $total_unoptimized - $remaining_unoptimized;

        // Calculate progress
        $progress = min(100, round(($total_processed / $total_unoptimized) * 100));

        // Determine if we're done
        $is_complete = $remaining_unoptimized === 0;

        wp_send_json_success([
            'progress' => $progress,
            'message' => $is_complete ? 'Optimization complete!' : "Optimizing images...",
            'results' => $results,
            'is_complete' => $is_complete,
            'total_unoptimized' => $remaining_unoptimized,
            'processed_count' => $processed_count
        ]);
    }

    /**
     * Initialize settings with defaults if not set.
     */
    function initialize_settings()
    {
        if (false === get_option(self::IMAGE_OPTIMIZER_SETTINGS)) {
            update_option(self::IMAGE_OPTIMIZER_SETTINGS, $this->default_settings);
        }
    }

    /**
     * Registers a new settings page under Settings.
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
     * Settings page display callback.
     */
    function settings_page()
    {
        $settings = get_option(self::IMAGE_OPTIMIZER_SETTINGS);
        $data     = array(
            'settings' => $settings,
            'has_unoptimized_images' => $this->fetcher->has_unoptimized_images(),
        );

        $this->loader->get_template(
            'image-optimizer-settings.php',
            $data,
            EIP_CUST_PLUGIN_DIR_PATH . '/templates/admin/',
            true
        );
    }

    /**
     * Save settings.
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

    function sanitize_yes_no($input)
    {
        return $input === 'yes' ? 'yes' : 'no';
    }

    function sanitize_cover_contain($input)
    {
        return in_array($input, array('cover', 'contain'), true) ? $input : 'cover';
    }
}




