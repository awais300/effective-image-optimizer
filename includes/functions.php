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
