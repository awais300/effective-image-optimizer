<?php

/**
 * Helper functions for the Effective Image Optimizer plugin.
 * 
 * This file contains utility functions that provide easy access to
 * plugin settings and debugging capabilities.
 *
 * @package AWP\IO
 * @since 1.0.0
 */

use AWP\IO\Admin\ImageOptimizerOptions;

/**
 * Retrieves optimizer settings.
 * 
 * Gets either all optimizer settings or a specific setting by name.
 *
 * @since 1.0.0
 * @param string|null $name Optional. The specific setting name to retrieve.
 * @return mixed The setting value if name is provided, all settings otherwise.
 */
function get_optimizer_settings($name = null)
{
    $optimizer = ImageOptimizerOptions::get_instance();
    return $optimizer->get_optimizer_settings($name);
}

/**
 * Gets the default optimizer settings.
 * 
 * Retrieves the default configuration values for the image optimizer.
 *
 * @since 1.0.0
 * @return array Array of default optimizer settings.
 */
function get_default_optimizer_settings()
{
    $optimizer = ImageOptimizerOptions::get_instance();
    return $optimizer->get_default_optimizer_settings();
}

/**
 * Debug function to print variables.
 * 
 * Outputs variable content in a readable format, either to the
 * screen or to the error log.
 *
 * @since 1.0.0
 * @param mixed $mix The variable to debug
 * @param bool $log Whether to write to error log instead of screen output
 */
if (!function_exists('dd')) {
    function dd($mix, $log = 0)
    {
        if ($log == false) {
            echo "<pre>";
            print_r($mix);
            echo "</pre>";
        }

        if ($log == true) {
            error_log('WriteLog:');
            error_log(print_r($mix, 1));
        }
    }
}