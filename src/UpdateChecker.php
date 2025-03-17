<?php
namespace AWP\IO;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Handles plugin update checks and integrates with the Plugin Update Checker library.
 *
 * This class initializes the update checker, retrieves the API key, and adds necessary
 * query arguments (e.g., API key and domain name) to the update request.
 *
 * @package AWP\IO
 * @since 1.1.7
 */
class UpdateChecker {
    /**
     * The update checker instance.
     *
     * @var \YahnisElsts\PluginUpdateChecker\v5\Plugin\UpdateChecker
     * @since 1.1.6
     */
    private $updateChecker;

    /**
     * Constructor for the UpdateChecker class.
     *
     * Initializes the update checker, retrieves the API key, and sets up the query argument filter.
     *
     * @param string $plugin_file The full path to the plugin file.
     * @param string $metadata_url The URL to the metadata file.
     * @param string $slug The plugin slug.
     * @since 1.1.7
     */
    public function __construct($plugin_file, $metadata_url, $slug) {
        // Initialize the update checker
        $this->updateChecker = PucFactory::buildUpdateChecker(
            $metadata_url,
            $plugin_file,
            $slug
        );

        // Add query argument filter
        $this->add_query_arg_filter();
    }

    /**
     * Adds a filter to modify the query arguments used to check for updates.
     *
     * This method ensures the API key and client domain are included in the update request.
     *
     * @since 1.1.7
     */
    private function add_query_arg_filter() {
        $this->updateChecker->addQueryArgFilter(function ($query_args) {

            $api_key = get_optimizer_settings('api_key');
            // Ensure API key is added to query arguments
            if (!empty($api_key)) {
                $query_args['api_key'] = $api_key;
                $query_args['client_domain'] = $this->get_clean_domain();
            }

            return $query_args;
        });
    }

    /**
     * Retrieves the cleaned domain name (without protocol and "www").
     *
     * This method extracts the domain name from the site URL and removes the "www." prefix if present.
     *
     * @return string The cleaned domain name.
     * @since 1.1.7
     */
    private function get_clean_domain() {
        $site_url = get_site_url();
        $domain = parse_url($site_url, PHP_URL_HOST);

        // Remove "www." if present
        return preg_replace('/^www\./', '', $domain);
    }
}