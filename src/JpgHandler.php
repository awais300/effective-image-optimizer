<?php

namespace AWP\IO;

/**
 * Handles JPEG image conversion and delivery in WordPress.
 * 
 * This class manages the conversion of PNG images to JPEG format when appropriate,
 * providing automatic format switching and optimization. It hooks into WordPress's
 * image handling system to serve JPEG versions of PNG images when available.
 *
 * @package AWP\IO
 * @since 1.0.0
 */
class JpgHandler
{
    /**
     * Initialize JPEG handling functionality.
     * 
     * Sets up hooks for content filtering and image URL modification
     * to handle JPEG conversion and delivery.
     * 
     * @since 1.0.0
     */
    public function __construct()
    {
        // Hook into HTML output to replace image URLs
        add_filter('the_content', [$this, 'replace_images_with_jpg'], 999);
        add_filter('wp_get_attachment_image_src', [$this, 'maybe_get_jpg_url'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'modify_image_srcset'], 10, 5);
    }

    /**
     * Check if a JPEG version exists for a given image URL.
     * 
     * First checks the attachment metadata for conversion data,
     * then falls back to filesystem check if necessary. Also considers
     * WebP delivery settings to avoid conflicts.
     *
     * @since 1.0.0
     * @access private
     * @param string $image_url URL of the original image
     * @return string|false JPEG image URL if exists, false otherwise
     */
    private function get_jpg_version($image_url)
    {
        // First try to get attachment ID from URL
        $attachment_id = attachment_url_to_postid($image_url);
        if ($attachment_id) {
            // Check optimization data
            $optimization_data = get_post_meta($attachment_id, '_awp_io_optimization_data', true);
            if (empty($optimization_data)) {
                return false;
            }

            // Check if any size was converted to JPG
            $converted = false;
            foreach ($optimization_data as $size_data) {
                if (isset($size_data['webp']) && get_optimizer_settings('deliver_next_gen_images') === 'yes') {
                    return false;
                }

                if (!empty($size_data['converted_to_jpg'])) {
                    $converted = true;
                    break;
                }
            }

            if (!$converted) {
                return false;
            }
        }

        // Proceed with filesystem check if metadata check passes
        // or if we couldn't get attachment ID
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        $jpg_path = preg_replace('/\.(png|PNG)$/i', '.jpg', $image_path);

        if (file_exists($jpg_path)) {
            return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $jpg_path);
        }

        return false;
    }

    /**
     * Replace PNG image URLs in content with JPEG versions.
     * 
     * Searches content for PNG image tags and replaces them with
     * JPEG versions when available.
     *
     * @since 1.0.0
     * @param string $content The post content to process
     * @return string Modified content with JPEG images where applicable
     */
    public function replace_images_with_jpg($content)
    {
        // Regular expression to find image tags with PNG sources
        $pattern = '/<img[^>]+src=([\'"])?([^\'"\s>]+\.(?:png|PNG))([\'"]\s)?[^>]*>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $img_tag = $matches[0];
            $src = $matches[2];

            // Check if JPG version exists
            $jpg_url = $this->get_jpg_version($src);
            if ($jpg_url) {
                // Replace src with JPG version
                $img_tag = str_replace($src, $jpg_url, $img_tag);
            }

            return $img_tag;
        }, $content);
    }

    /**
     * Modify image source sets to include JPEG versions.
     * 
     * Updates the srcset attribute of PNG images to use JPEG versions
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
        foreach ($sources as $width => $source) {
            // Only process PNG images
            if (preg_match('/\.(png|PNG)$/i', $source['url'])) {
                $jpg_url = $this->get_jpg_version($source['url']);
                if ($jpg_url) {
                    $sources[$width]['url'] = $jpg_url;
                }
            }
        }

        return $sources;
    }

    /**
     * Conditionally return JPEG URL for attachment images.
     * 
     * Checks if a JPEG version exists for PNG images and returns it
     * instead of the original PNG URL when appropriate.
     *
     * @since 1.0.0
     * @param array|false $image         Array of image data, or false
     * @param int         $attachment_id Attachment ID
     * @param string|array $size        Requested image size
     * @param bool        $icon         Whether the image should be treated as an icon
     * @return array|false Modified image data array or false
     */
    public function maybe_get_jpg_url($image, $attachment_id, $size, $icon)
    {
        if (!is_array($image)) {
            return $image;
        }

        // Only process PNG images
        if (preg_match('/\.(png|PNG)$/i', $image[0])) {
            $jpg_url = $this->get_jpg_version($image[0]);
            if ($jpg_url) {
                $image[0] = $jpg_url;
            }
        }

        return $image;
    }
}
