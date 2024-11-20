<?php
namespace AWP\IO\CLI;

use AWP\IO\ImageFetcher;
use AWP\IO\ImageSender;
use AWP\IO\ImageTracker;
use AWP\IO\OptimizationManager;
use WP_CLI;

class InitCLI
{
    /**
     * Initialize WP-CLI commands
     *
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
     * Register all CLI commands
     *
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