<?php

namespace AWP\IO;

/**
 * ImageSender Class
 *
 * Handles the communication with the remote optimization server.
 * Responsible for sending images and receiving optimization results.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class ImageSender extends Singleton
{
    /**
     * Remote optimization server URL
     *
     * @var string
     */
    private $remote_url;

    /**
     * Validate API key endpoint.
     *
     * @var string
     */
    private $remote_url_validate_api;

    /**
     * API key for authentication with the remote server
     *
     * @var string
     */
    private $api_key = null;

    /**
     * Constructor.
     *
     * Initializes the remote URL and API key for the optimization service.
     */
    public function __construct()
    {
        // Apply filters to allow modification of the remote URLs
        $this->remote_url = apply_filters('awp_io_remote_url', 'https://ioserver.is-cool.dev/wp-json/awp-io/v1/optimize');
        $this->remote_url_validate_api = apply_filters('awp_io_remote_url_validate_api', 'https://ioserver.is-cool.dev/wp-json/awp-io/v1/validate-api-key');
    }

    /**
     * Retrieves the API key for the optimization service.
     * 
     * Lazily loads the API key from settings when first needed
     * to avoid circular dependencies during initialization.
     * 
     * @since 1.0.0
     * @access private
     * @return string|null The API key if set, null otherwise
     */
    private function get_api_key()
    {
        if ($this->api_key === null) {
            $this->api_key = get_optimizer_settings('api_key');
        }
        return $this->api_key;
    }

    /**
     * Send multiple images for optimization.
     *
     * Processes a batch of images associated with a specific attachment,
     * sending each one to the remote optimization server.
     *
     * @since 1.0.0
     * @param int   $attachment_id WordPress attachment ID
     * @param array $images        Array of image data to process
     * @return array Array of optimization results or error messages
     */
    public function send_images($attachment_id, $images)
    {
        $results = [];
        foreach ($images as $image) {
            try {
                $result = $this->send_single_image($attachment_id, $image);
                $results[] = $result;
            } catch (\Exception $e) {
                $results[] = [
                    'error' => $e->getMessage(),
                    'image' => $image
                ];
            }
        }

        return $results;
    }

    /**
     * Send a single image for optimization.
     *
     * Handles the actual HTTP communication with the remote server for
     * a single image, including file reading and multipart form data creation.
     *
     * @since 1.0.0
     * @param int   $attachment_id WordPress attachment ID
     * @param array $image         Image data including path and type
     * @return array Optimization result from the server
     * @throws \Exception When file operations fail or server communication errors occur
     */
    private function send_single_image($attachment_id, $image)
    {
        if (!file_exists($image['path'])) {
            throw new \Exception("Image file not found: {$image['path']}");
        }

        // Get settings
        $default_settings = json_encode(get_default_optimizer_settings());
        $settings = json_encode(get_optimizer_settings());

        // Prepare file data
        $file_name = basename($image['path']);
        $file_content = file_get_contents($image['path']);
        if ($file_content === false) {
            throw new \Exception("Failed to read file content: {$image['path']}");
        }

        // Create boundary for multipart form data
        $boundary = wp_generate_password(24, false);

        // Prepare multipart form data
        $payload = '';

        // Add file content
        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file_content"; filename="' . $file_name . '"' . "\r\n";
        $payload .= 'Content-Type: ' . mime_content_type($image['path']) . "\r\n\r\n";
        $payload .= $file_content . "\r\n";

        // Add file metadata
        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file_name"' . "\r\n\r\n";
        $payload .= $file_name . "\r\n";

        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file_type"' . "\r\n\r\n";
        $payload .= mime_content_type($image['path']) . "\r\n";

        // Add other fields
        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="file_local_path"' . "\r\n\r\n";
        $payload .= $image['path'] . "\r\n";

        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="attachment_id"' . "\r\n\r\n";
        $payload .= $attachment_id . "\r\n";

        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="image_type"' . "\r\n\r\n";
        $payload .= $image['type'] . "\r\n";

        if (isset($image['size'])) {
            $payload .= "--" . $boundary . "\r\n";
            $payload .= 'Content-Disposition: form-data; name="image_size"' . "\r\n\r\n";
            $payload .= $image['size'] . "\r\n";
        }

        // Add default settings to payload
        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="default_settings"' . "\r\n\r\n";
        $payload .= $default_settings . "\r\n";

        // Add settings to payload
        $payload .= "--" . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="settings"' . "\r\n\r\n";
        $payload .= $settings . "\r\n";

        $payload .= "--" . $boundary . "--\r\n";

        // Prepare the request
        $args = [
            'timeout' => 60, // Increase timeout for large files
            'headers' => [
                'X-AWP-IO-API-Key' => $this->get_api_key(),
                'X-AWP-IO-Site-URL' => get_site_url(),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'Content-Length' => strlen($payload),
            ],
            'body' => $payload,
        ];

        // Send the request
        $response = wp_remote_post($this->remote_url, $args);

        /*dd($response);
        exit;*/

        if (is_wp_error($response)) {
            throw new \Exception('Request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        //error_log('inSendSingleImage:', 3 , '/home/yousellcomics/public_html/adebug.log');
        //error_log(print_r($result, true), 3 , '/home/yousellcomics/public_html/adebug.log');

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            // Handle server-side errors
            if (isset($result['code']) && isset($result['message'])) {
                // This is a WP_Error converted to JSON
                throw new \Exception('Request failed: ' . $result['message'] . ' (Code: ' . $result['code'] . ')');
            } else {
                // Handle generic HTTP errors
                throw new \Exception('Request failed with status: ' . $response_code);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from server');
        }

        return $result;
    }

    /**
     * Validate the API key by sending a test request to the remote server.
     *
     * @since 1.0.0
     * @return bool True if the API key is valid, false otherwise.
     */
    /**
     * Validate the API key by sending a test request to the remote server.
     *
     * @since 1.0.0
     * @return array An array containing the success status and a message.
     * @throws \Exception If the request fails or the API key is invalid.
     */
    public function validate_api_key()
    {
        // Prepare the request payload
        $payload = json_encode([
            'action' => 'validate_api_key',
            'api_key' => $this->get_api_key(),
        ]);

        // Prepare the request arguments
        $args = [
            'timeout' => 10,
            'headers' => [
                'X-AWP-IO-API-Key' => $this->get_api_key(),
                'X-AWP-IO-Site-URL' => get_site_url(),
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($payload),
            ],
            'body' => $payload,
        ];

        // Send the request to the remote server
        $response = wp_remote_post($this->remote_url_validate_api, $args);

        // Check for local WP_Error (e.g., network issues)
        if (is_wp_error($response)) {
            $error_message = 'API key validation failed: ' . $response->get_error_message();
            error_log($error_message);
            throw new \Exception($error_message);
        }

        // Retrieve the response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        // Handle server-side errors (non-200 status codes)
        if ($response_code !== 200) {
            if (isset($response_data['code']) && isset($response_data['message'])) {
                // This is a WP_Error converted to JSON
                throw new \Exception('Request failed: ' . $response_data['message'] . ' (Code: ' . $response_data['code'] . ')');
            } else {
                // Handle generic HTTP errors
                throw new \Exception('Request failed with status: ' . $response_code);
            }
        }

        // Check if the response indicates success
        if (isset($response_data['success']) && $response_data['success'] === true) {
            return ['success' => true, 'message' => $response_data['message'] ?? 'API key is valid'];
        }

        // Handle invalid API key or other issues
        throw new \Exception($response_data['message'] ?? 'Invalid API key or unexpected response from the server');
    }
}
