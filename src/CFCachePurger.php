<?php

namespace AWP\IO;

use WP_Error;

/**
 * Class CFCachePurger
 *
 * Handles Cloudflare cache purging for optimized WordPress media attachments.
 *
 * @package AWP\IO
 * @since 1.1.2
 */
class CFCachePurger
{
    /**
     * API token for CDN authentication.
     *
     * @var string
     */
    private $api_token;

    /**
     * CDN zone identifier.
     *
     * @var string|null
     */
    private $zone_id;

    /**
     * Whether to enable logging.
     *
     * @var bool
     */
    private $log_enabled;

    /**
     * Initialize the CDN Cache Purger.
     *
     * @param bool $log_enabled Whether to enable logging.
     * @since 1.1.2
     */
    public function __construct($log_enabled = true)
    {
        $this->log_enabled = $log_enabled;
        $this->zone_id = null;
        $this->api_token = null;

        // Hook into image optimization completion
        add_action('awp_image_optimization_completed', array($this, 'handle_optimized_image'), 10, 2);
        add_action('awp_image_after_optimization_cleanup', array($this, 'handle_optimized_image'), 10, 1);
    }

    /**
     * Verify if the Cloudflare API token is valid.
     *
     * @return bool|WP_Error Returns true if the token is valid, WP_Error on failure.
     * @since 1.1.2
     */
    public function verify_api_token()
    {
        // Get API token from settings
        if (empty(trim($this->api_token))) {
            $this->api_token = get_optimizer_settings('cloudflare_api_token');
        }

        $this->api_token = trim($this->api_token);

        $args = array(
            'headers' => array(
                'Authorization' => sprintf('Bearer %s', $this->api_token),
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        $response = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', $args);

        if (is_wp_error($response)) {
            $this->log_error('Failed to verify API token: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['success']) || !$data['success']) {
            $error_message = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : 'Unknown error';
            $this->log_error('API error while verifying token: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        $this->log_info('Cloudflare API token is valid.');
        return true;
    }

    /**
     * Handle optimized image by purging its URL from CDN cache.
     *
     * @param int   $attachment_id     The attachment ID of the optimized image.
     * @param array $optimization_data Optimization data from the process.
     * @return void
     * @since 1.1.2
     */
    public function handle_optimized_image($attachment_id, $optimization_data = null)
    {
        // Get API token from settings
        $this->api_token = get_optimizer_settings('cloudflare_api_token');

        if (empty($this->api_token)) {
            $this->log_error('Cloudflare API token not configured');
            return;
        }

        // Verify the API token
        $token_valid = $this->verify_api_token();
        if (is_wp_error($token_valid)) {
            $this->log_error('Invalid Cloudflare API token: ' . $token_valid->get_error_message());
            return;
        }

        // Get the main image URL
        $main_image_url = wp_get_attachment_url($attachment_id);
        if ($main_image_url) {
            $this->purge_url($main_image_url);
        }

        // Get and purge all image sizes
        $image_sizes = $this->get_attachment_image_urls($attachment_id);
        foreach ($image_sizes as $size_url) {
            $this->purge_url($size_url);
        }
    }

    /**
     * Get all image size URLs for an attachment.
     *
     * @param int $attachment_id The attachment ID.
     * @return array Array of image URLs for all sizes.
     * @since 1.1.2
     */
    private function get_attachment_image_urls($attachment_id)
    {
        $urls = array();
        $sizes = get_intermediate_image_sizes();

        foreach ($sizes as $size) {
            $image = wp_get_attachment_image_src($attachment_id, $size);
            if ($image) {
                $urls[] = $image[0];
            }
        }

        return array_unique($urls);
    }

    /**
     * Get domain name without protocol and www.
     *
     * @return string The cleaned domain name.
     * @since 1.1.2
     */
    private function get_clean_domain()
    {
        $site_url = get_site_url();
        $domain = parse_url($site_url, PHP_URL_HOST);
        // Remove www. if present
        return preg_replace('/^www\./', '', $domain);
    }

    /**
     * Fetch and store the zone ID from the CDN API.
     *
     * @return bool|WP_Error Returns true on success, WP_Error on failure.
     * @since 1.1.2
     */
    private function fetch_zone_id()
    {
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        $response = wp_remote_get('https://api.cloudflare.com/client/v4/zones', $args);

        if (is_wp_error($response)) {
            $this->log_error('Failed to fetch zone ID: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['success']) || !$data['success']) {
            $error_message = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : 'Unknown error';
            $this->log_error('API error while fetching zone ID: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        if (empty($data['result'])) {
            $this->log_error('No zones found in account');
            return new WP_Error('no_zones', 'No zones found in account');
        }

        $domain = $this->get_clean_domain();

        // Find the Zone ID for the given domain
        foreach ($data['result'] as $zone) {
            if ($zone['name'] === $domain) {
                $this->zone_id = $zone['id'];
                $this->log_info('Found zone ID for domain: ' . $domain);
                return true;
            }
        }

        $this->log_error('Zone not found for domain: ' . $domain);
        return new WP_Error('zone_not_found', 'Zone not found for domain: ' . $domain);
    }

    /**
     * Purge specific URL from CDN cache.
     *
     * @param string $url The URL to purge from cache.
     * @return bool|WP_Error Returns true on success, WP_Error on failure.
     * @since 1.1.2
     */
    public function purge_url($url)
    {
        if (empty($this->zone_id)) {
            $result = $this->fetch_zone_id();
            if (is_wp_error($result)) {
                $this->log_error('Failed to get zone id: ' . $result->get_error_message());
                return $result;
            }
        }

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'files' => array($url)
            )),
            'method' => 'POST',
            'timeout' => 30,
        );

        $purge_url = 'https://api.cloudflare.com/client/v4/zones/' . $this->zone_id . '/purge_cache';
        $response = wp_remote_post($purge_url, $args);

        if (is_wp_error($response)) {
            $this->log_error('Failed to purge URL: ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['success']) || !$data['success']) {
            $error_message = isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : 'Unknown error';
            $this->log_error('API error while purging URL: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        $this->log_info('Successfully purged URL: ' . $url);
        return true;
    }

    /**
     * Log error message to WordPress error log.
     *
     * @param string $message The error message to log.
     * @return void
     * @since 1.1.2
     */
    private function log_error($message)
    {
        if ($this->log_enabled) {
            error_log('[CDN Cache Purger Error] ' . $message);
        }
    }

    /**
     * Log info message to WordPress error log.
     *
     * @param string $message The info message to log.
     * @return void
     * @since 1.1.2
     */
    private function log_info($message)
    {
        if ($this->log_enabled) {
            error_log('[CDN Cache Purger Info] ' . $message);
        }
    }
}