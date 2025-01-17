<?php

namespace AWP\IO;

defined('ABSPATH') || exit;

/**
 * Template Loading functionality for the plugin.
 * 
 * Provides methods to load and render template files with variable passing
 * capability. Extends the Singleton pattern to ensure only one instance
 * handles template loading.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class TemplateLoader extends Singleton
{

    /**
     * Loads and optionally outputs a template file.
     *
     * Loads a template file from the specified path, passes variables to it,
     * and either returns or echoes the output. Uses output buffering to
     * capture the template output.
     *
     * @since 1.0.0
     * @param string $template_name The name of the template file to load
     * @param array  $args         Variables to pass to the template
     * @param string $template_path The directory path where the template is located
     * @param bool   $echo         Whether to echo the output (true) or return it (false)
     * @return string|void The template output if $echo is false, void if true
     * @throws \Exception If the template file does not exist
     */
    public function get_template($template_name = '', $args = array(), $template_path = '', $echo = false)
    {
        $output = null;

        $template_path = $template_path . $template_name;

        if (file_exists($template_path)) {
            extract($args); // @codingStandardsIgnoreLine required for template.

            ob_start();
            include $template_path;
            $output = ob_get_clean();
        } else {
            throw new \Exception(__('Specified path does not exist', ''));
        }

        if ($echo) {
            print $output; // @codingStandardsIgnoreLine $output contains dynamic data and escping is being handled in the template file.
        } else {
            return $output;
        }
    }
}
