<?php
namespace AWP\IO\CLI;

use AWP\IO\ImageFetcher;
use AWP\IO\ImageSender;
use AWP\IO\ImageTracker;
use AWP\IO\OptimizationManager;
use WP_CLI;

/**
 * Initializes and registers WP-CLI commands for the Image Optimizer plugin.
 *
 * This class handles the registration of all WP-CLI commands provided by the plugin.
 * It ensures commands are only registered when WP-CLI is available and loaded.
 *
 * @package AWP\IO\CLI
 * @since 1.0.0
 */
class InitCLI
{
    /**
     * Initializes WP-CLI commands for the plugin.
     *
     * Checks for WP-CLI availability and registers all plugin commands
     * if WP-CLI is present and loaded.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init()
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }
        self::register_commands();
    }

    /**
     * Registers all CLI commands for the plugin.
     *
     * Currently registers the 'optimize' command which provides
     * functionality to optimize images in the media library.
     *
     * @since 1.0.0
     * @return void
     */
    private static function register_commands()
    {
        WP_CLI::add_command(
            'awp-io optimize',
            ['AWP\IO\CLI\ImageOptimizerCLI', 'optimize'],
            [
                'shortdesc' => 'Optimizes images in the media library',
                'when' => 'after_wp_load'
            ]
        );
    }
}