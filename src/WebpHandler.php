<?php

namespace AWP\IO;

/**
 * Handles WebP image conversion and delivery in WordPress.
 * 
 * This class manages WebP image support, including browser detection,
 * image conversion, and dynamic serving of WebP images when supported.
 * It hooks into WordPress's image handling system to provide seamless
 * WebP support.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class WebpHandler
{
    /**
     * Initialize WebP handling functionality.
     * 
     * Sets up hooks for content filtering, image URL modification,
     * and WebP support in WordPress.
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        // Hook into HTML output to replace image URLs
        add_filter('the_content', [$this, 'replace_images_with_webp'], 9999);
        add_filter('wp_get_attachment_image_src', [$this, 'maybe_get_webp_url'], 10, 15);
        add_filter('wp_calculate_image_srcset', [$this, 'modify_image_srcset'], 10, 20);

        // Add WebP support to allowed mime types
        add_filter('upload_mimes', [$this, 'add_webp_mime_type']);

        // Add WebP headers
        add_action('init', [$this, 'add_webp_headers']);
    }

    /**
     * Check if the current browser supports WebP images.
     * 
     * Examines the HTTP_ACCEPT header to determine if the client
     * browser supports WebP image format.
     * 
     * @since 1.0.0
     * @access private
     * @return bool True if browser supports WebP, false otherwise
     */
    private function browser_supports_webp()
    {
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            if (strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Legacy method to check for WebP version of an image.
     * 
     * @deprecated since 1.0.0 Use get_webp_version() instead
     * @access private
     * @param string $image_url URL of the original image
     * @return string|false WebP image URL if exists, false otherwise
     */
    private function get_webp_version_old($image_url)
    {
        // Convert URL to file path
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);

        // Generate WebP path
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image_path);

        if (file_exists($webp_path)) {
            return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $webp_path);
        }

        return false;
    }

    /**
     * Check if a WebP version exists for a given image URL.
     * 
     * First checks the attachment metadata for WebP support flag,
     * then falls back to filesystem check if necessary.
     * 
     * @since 1.0.0
     * @access private
     * @param string $image_url URL of the original image
     * @return string|false WebP image URL if exists, false otherwise
     */
    private function get_webp_version($image_url)
    {
        // First try to get attachment ID from URL
        $attachment_id = attachment_url_to_postid($image_url);

        if ($attachment_id) {
            // Check metadata first
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (empty($metadata['webp_enabled'])) {
                return false; // No WebP version available
            }
        }

        // Proceed with existing filesystem check if metadata check passes
        // or if we couldn't get attachment ID
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image_path);

        if (file_exists($webp_path)) {
            return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $webp_path);
        }

        return false;
    }

    /**
     * Replace image URLs in content with WebP versions.
     * 
     * Searches content for image tags and replaces them with picture
     * elements containing both WebP and original sources when WebP
     * versions are available.
     * 
     * @since 1.0.0
     * @param string $content The post content to process
     * @return string Modified content with WebP images
     */
    public function replace_images_with_webp($content)
    {
        if (!$this->browser_supports_webp()) {
            return $content;
        }

        // Regular expression to find image tags
        $pattern = '/<img[^>]+src=([\'"])?([^\'"\s>]+)([\'"]\s)?[^>]*>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $img_tag = $matches[0];
            $src = $matches[2];

            // Check if WebP version exists
            $webp_url = $this->get_webp_version($src);
            if ($webp_url) {
                // Replace src with WebP version
                $img_tag = str_replace($src, $webp_url, $img_tag);

                // Add picture tag for fallback
                return sprintf(
                    '<picture>
                        <source srcset="%s" type="image/webp">
                        %s
                    </picture>',
                    esc_attr($webp_url),
                    $img_tag
                );
            }

            return $img_tag;
        }, $content);
    }

    /**
     * Modify image source sets to include WebP versions.
     * 
     * Updates the srcset attribute of images to use WebP versions
     * when available.
     * 
     * @since 1.0.0
     * @param array  $sources      Array of image sources with descriptors
     * @param array  $size_array   Array of width and height values
     * @param string $image_src    The 'src' of the image
     * @param array  $image_meta   The image meta data
     * @param int    $attachment_id The image attachment ID
     * @return array Modified array of image sources
     */
    public function modify_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!$this->browser_supports_webp()) {
            return $sources;
        }

        // Return early if sources is not an array
        if (!is_array($sources)) {
            return $sources;
        }

        foreach ($sources as $width => $source) {
            $webp_url = $this->get_webp_version($source['url']);
            if ($webp_url) {
                $sources[$width]['url'] = $webp_url;
            }
        }

        return $sources;
    }

    /**
     * Conditionally return WebP URL for attachment images.
     * 
     * Checks if a WebP version exists and returns it instead of
     * the original image URL when appropriate.
     * 
     * @since 1.0.0
     * @param array|false $image         Array of image data, or false
     * @param int         $attachment_id Attachment ID
     * @param string|array $size        Requested image size
     * @param bool        $icon         Whether the image should be treated as an icon
     * @return array|false Modified image data array or false
     */
    public function maybe_get_webp_url($image, $attachment_id, $size, $icon)
    {
        if (!$this->browser_supports_webp() || !is_array($image)) {
            return $image;
        }

        $webp_url = $this->get_webp_version($image[0]);
        if ($webp_url) {
            $image[0] = $webp_url;
        }

        return $image;
    }

    /**
     * Add WebP to WordPress's allowed mime types.
     * 
     * @since 1.0.0
     * @param array $mimes Array of allowed mime types
     * @return array Modified array of mime types
     */
    public function add_webp_mime_type($mimes)
    {
        $mimes['webp'] = 'image/webp';
        return $mimes;
    }

    /**
     * Add WebP-related HTTP headers.
     * 
     * Adds headers to indicate WebP support and vary cache based on
     * Accept header.
     * 
     * @since 1.0.0
     */
    public function add_webp_headers()
    {
        if (!headers_sent()) {
            header('Accept: image/webp');
            header('Vary: Accept');
        }
    }
}
