<?php
/**
 * Plugin Name: Effective Image Optimizer
 * Plugin URI: https://awaiswp.is-a-fullstack.dev/
 * Description: Optimize and compress images in WordPress media library efficiently.
 * Author: AWP - Muhammad Awais
 * Author URI: https://awaiswp.is-a-fullstack.dev/contact/
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package AWP\IO
 * @author Muhammad Awais
 * @version 1.0.0
 */

namespace AWP\IO;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
if ( ! defined( 'EIP_CUST_PLUGIN_FILE' ) ) {
    define( 'EIP_CUST_PLUGIN_FILE', __FILE__ );
}

// Autoload plugin classes
require_once 'vendor/autoload.php';

// Initialize the plugin
Bootstrap::get_instance();


/**
 * Activate the plugin.
 */
function effective_image_optimizer_on_activate()
{
	$schema = Schema::get_instance();

    $schema->create_optimization_stats_table();
    $schema->create_reoptimization_table();
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\effective_image_optimizer_on_activate');


/**
 * Deactivation hook.
 */
function effective_image_optimizer_on_deactivate()
{
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\effective_image_optimizer_on_deactivate');


/*delete_transient('bulk_restore_total_images');
exit;*/