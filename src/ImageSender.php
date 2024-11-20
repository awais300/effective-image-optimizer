<?php

namespace AWP\IO;

class ImageSender extends Singleton
{
    private $remote_url;
    private $api_key;

    public function __construct()
    {
        /*$this->remote_url = $remote_url;
        $this->api_key = $api_key;*/

        $this->remote_url = 'https://ioserver.is-cool.dev/wp-json/awp-io/v1/optimize';
        $this->api_key = 'AFDAFUA*R#KRAFK';
    }

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
                'X-AWP-IO-API-Key' => $this->api_key,
                'X-AWP-IO-Site-URL' => get_site_url(),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'Content-Length' => strlen($payload),
            ],
            'body' => $payload,
        ];

        // Send the request
        $response = wp_remote_post($this->remote_url, $args);

        if (is_wp_error($response)) {
            throw new \Exception('Request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new \Exception('Request failed with status: ' . $response_code);
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from server');
        }

        return $result;
    }
}
