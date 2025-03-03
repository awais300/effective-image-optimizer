<?php

namespace AWP\IO;

use AWP\IO\Admin\ImageOptimizerOptions;
use AWP\IO\Stats\OptimizationStatsManager;

defined('ABSPATH') || exit;

/**
 * Bootstrap Class
 *
 * Main plugin initialization class. Handles plugin setup, hooks registration,
 * asset loading, and core functionality initialization.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class Bootstrap
{

    /**
     * Plugin version number
     *
     * @var string
     */
    private $version = '1.1.6';

    /**
     * Singleton instance of the plugin.
     *
     * @var Bootstrap
     */
    protected static $instance = null;

    /**
     * Constructor.
     *
     * Sets up plugin initialization hooks and CLI initialization.
     */
    public function __construct()
    {

        add_action('init', array($this, 'load_plugin'), 0);
        add_action('init', ['AWP\IO\CLI\InitCLI', 'init']);
    }

    /**
     * Main Bootstrap Instance.
     *
     * Ensures only one instance of Bootstrap is loaded or can be loaded.
     * 
     * @since 1.0.0
     * @static
     * @return Bootstrap Main instance of the plugin
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin loading process.
     *
     * Defines constants and initializes required hooks.
     * 
     * @since 1.0.0
     * @return void
     */
    public function load_plugin()
    {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Define Plugin Constants.
     *
     * Sets up plugin-specific constants for paths and URLs.
     * 
     * @since 1.0.0
     * @return void
     */
    private function define_constants()
    {
        // Path related defines
        $this->define('EIP_CUST_PLUGIN_FILE', EIP_CUST_PLUGIN_FILE);
        $this->define('EIP_CUST_PLUGIN_BASENAME', plugin_basename(EIP_CUST_PLUGIN_FILE));
        $this->define('EIP_CUST_PLUGIN_DIR_PATH', untrailingslashit(plugin_dir_path(EIP_CUST_PLUGIN_FILE)));
        $this->define('EIP_CUST_PLUGIN_DIR_URL', untrailingslashit(plugins_url('/', EIP_CUST_PLUGIN_FILE)));
    }

    /**
     * Initialize Plugin Hooks.
     *
     * Registers all required WordPress action hooks for the plugin.
     * 
     * @since 1.0.0
     * @return void
     */
    public function init_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'), 1);

        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    /**
     * Load Plugin Text Domain.
     *
     * Loads the translation files for internationalization support.
     * 
     * @since 1.0.0
     * @return void
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('eip', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Initialize Core Plugin Components.
     *
     * Sets up the image optimizer, media library optimizer, and other core features.
     * 
     * @since 1.0.0
     * @return void
     */
    public function init()
    {
        $optimizer = ImageOptimizerOptions::get_instance();
        $optimizer->initialize_hooks();

        CFCachePurger::get_instance();

        $setting_convert_to_webp_media_upload = $optimizer->get_optimizer_settings('convert_to_webp_media_upload');
        if($setting_convert_to_webp_media_upload === 'yes') {
            new WebpUploadConverter();
        }

        MediaLibraryOptimizer::get_instance();
        OptimizationStatsManager::get_instance();
        BulkRestore::get_instance();

        $setting_deliver_next_gen_images = $optimizer->get_optimizer_settings('deliver_next_gen_images');
        if ($setting_deliver_next_gen_images === 'yes') {
            new WebpHandler();
        }

        new JpgHandler();
    }

    /**
     * Enqueue Frontend Styles.
     *
     * Registers and enqueues CSS files for the frontend.
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_styles()
    {
        wp_enqueue_style('eip-frontend', EIP_CUST_PLUGIN_DIR_URL . '/assets/css/eip-frontend.css', array(), null, 'all');
    }

    /**
     * Enqueue Frontend Scripts.
     *
     * Registers and enqueues JavaScript files for the frontend.
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script('eip-frontend', EIP_CUST_PLUGIN_DIR_URL . '/assets/js/eip-frontend.js', array('jquery'));
    }

    /**
     * Enqueue Admin Styles.
     *
     * Registers and enqueues CSS files for the admin area.
     * 
     * @since 1.0.0
     * @return void
     */
    public function admin_enqueue_styles()
    {
        wp_enqueue_style('eip-backend', EIP_CUST_PLUGIN_DIR_URL . '/assets/css/eip-backend.css', array(), null, 'all');
    }

    /**
     * Enqueue Admin Scripts.
     *
     * Registers and enqueues JavaScript files for the admin area.
     * Also localizes scripts with necessary data.
     * 
     * @since 1.0.0
     * @param string $hook The current admin page hook
     * @return void
     */
    public function admin_enqueue_scripts($hook)
    {
        wp_enqueue_script('eip-backend', EIP_CUST_PLUGIN_DIR_URL . '/assets/js/eip-backend.js', array('jquery'));

        wp_localize_script('eip-backend', 'wpeio_data', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('start_optimization_nonce'),
            'screen' => get_current_screen(),
            'i18n' => [
                'error' => __('An error occurred', 'awp-io'),
                'networkError' => __('Network error occurred', 'awp-io')
            ]
        ]);
    }

    /**
     * Define a Constant.
     *
     * Helper method to safely define a constant if it's not already defined.
     * 
     * @since 1.0.0
     * @param string $name The constant name
     * @param mixed $value The constant value
     * @return void
     */
    public function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
