<?php

namespace AWP\IO;

use AWP\IO\Admin\ImageOptimizerOptions;
use AWP\IO\Stats\StatsHandler;

defined('ABSPATH') || exit;

/**
 * Class Bootstrap
 * @package AWP\IO
 */

class Bootstrap
{

	private $version = '1.0.0';

	/**
	 * Instance to call certain functions globally within the plugin.
	 *
	 * @var instance
	 */
	protected static $instance = null;

	/**
	 * Construct the plugin.
	 */
	public function __construct()
	{

		add_action('init', array($this, 'load_plugin'), 0);
		add_action('init', ['AWP\IO\CLI\InitCLI', 'init']);
	}

	/**
	 * Main Bootstrap instance.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @static
	 * @return self Main instance.
	 */
	public static function get_instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin()
	{
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Define WC Constants.
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
	 * Collection of hooks.
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
	 * Localisation.
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain('eip', false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}

	/**
	 * Initialize the plugin.
	 */
	public function init()
	{
		$optimizer = ImageOptimizerOptions::get_instance();
    	$optimizer->initialize_hooks();
		new MediaLibraryOptimizer();
		StatsHandler::get_instance();

		$setting_deliver_next_gen_images = get_optimizer_settings('deliver_next_gen_images');
		if($setting_deliver_next_gen_images === 'yes') {
			new WebpHandler();
		}
	}

	/**
	 * Enqueue all styles.
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style('eip-frontend', EIP_CUST_PLUGIN_DIR_URL . '/assets/css/eip-frontend.css', array(), null, 'all');
	}

	/**
	 * Enqueue all scripts.
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('eip-frontend', EIP_CUST_PLUGIN_DIR_URL . '/assets/js/eip-frontend.js', array('jquery'));
	}

	/**
	 * Enqueue all admin styles.
	 */
	public function admin_enqueue_styles()
	{
		wp_enqueue_style('eip-backend', EIP_CUST_PLUGIN_DIR_URL . '/assets/css/eip-backend.css', array(), null, 'all');
	}

	/**
	 * Enqueue all admin scripts.
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
	 * Define constant if not already set.
	 *
	 * @param  string $name
	 * @param  string|bool $value
	 */
	public function define($name, $value)
	{
		if (!defined($name)) {
			define($name, $value);
		}
	}
}
