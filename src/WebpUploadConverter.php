<?php

namespace AWP\IO;

use Imagick;
use Exception;

/**
 * Class WebpUploadConverter
 *
 * This class handles the conversion of uploaded images to WebP format in WordPress.
 */
class WebpUploadConverter
{
    /**
     * Supported image MIME types for WebP conversion.
     *
     * @var array
     */
    private $supported_types = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * WebP compression quality.
     */
    private const WEBP_QUALITY = 100;

    /**
     * Initialize the class by hooking into the WordPress upload process.
     */
    public function __construct()
    {
        add_filter('wp_handle_upload_prefilter', array($this, 'convert_to_webp_before_upload'));
    }

    /**
     * Convert the uploaded image to WebP format before it is saved to the server.
     *
     * This method is hooked into the `wp_handle_upload_prefilter` filter. It checks if the uploaded file
     * is an image and converts it to WebP format if it is not already in that format.
     *
     * @param array $file An array of file data.
     * @return array The modified file data.
     */
    public function convert_to_webp_before_upload($file)
    {
        // Skip if already a WebP image or unsupported type
        if ($file['type'] === 'image/webp' || !in_array($file['type'], $this->supported_types)) {
            return $file;
        }

        // Check if it's an image
        if (strpos($file['type'], 'image/') !== 0) {
            return $file;
        }

        // Get file information
        $path_info = pathinfo($file['name']);
        if (!isset($path_info['extension'])) {
            return $file;
        }

        // Create a copy of the temp file for conversion
        $temp_file = $file['tmp_name'];
        $webp_temp = $temp_file . '.webp';

        // Convert to WebP
        if ($this->convert_to_webp($temp_file, $webp_temp)) {
            // Update the temporary file
            if (!copy($webp_temp, $temp_file)) {
                error_log('Failed to copy WebP file: ' . $webp_temp);
                return $file;
            }
            if (!unlink($webp_temp)) {
                error_log('Failed to delete temporary WebP file: ' . $webp_temp);
            }

            // Update file information
            $file['type'] = 'image/webp';
            $file['name'] = $path_info['filename'] . '.webp';
        }

        return $file;
    }

    /**
     * Convert an image to WebP format using Imagick or GD as a fallback.
     *
     * This method handles the actual conversion of the image to WebP format. It first attempts to use
     * the Imagick extension if available. If Imagick is not available, it falls back to the GD library.
     *
     * @param string $source_path The path to the source image.
     * @param string $dest_path The path to save the converted WebP image.
     * @return bool True if the conversion was successful, false otherwise.
     */
    private function convert_to_webp($source_path, $dest_path)
    {
        // Check if the source path is a valid file
        if (!is_file($source_path)) {
            return false;
        }

        // Use Imagick if available
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick($source_path);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(self::WEBP_QUALITY);
                $success = $image->writeImage($dest_path);
                $image->clear();
                $image->destroy();
                return $success && is_file($dest_path);
            } catch (Exception $e) {
                error_log('Imagick WebP conversion failed: ' . $e->getMessage());
            }
        }

        // GD fallback
        if (function_exists('imagecreatefromjpeg')) {
            try {
                $image_type = exif_imagetype($source_path);
                if ($image_type === false) {
                    return false;
                }

                $source_image = null;
                switch ($image_type) {
                    case IMAGETYPE_JPEG:
                        $source_image = imagecreatefromjpeg($source_path);
                        break;
                    case IMAGETYPE_PNG:
                        $source_image = imagecreatefrompng($source_path);
                        if ($source_image) {
                            imagepalettetotruecolor($source_image);
                            imagealphablending($source_image, true);
                            imagesavealpha($source_image, true);
                        }
                        break;
                    case IMAGETYPE_GIF:
                        $source_image = imagecreatefromgif($source_path);
                        break;
                    default:
                        return false;
                }

                if ($source_image) {
                    $success = imagewebp($source_image, $dest_path, self::WEBP_QUALITY);
                    imagedestroy($source_image);
                    return $success && is_file($dest_path);
                }
            } catch (Exception $e) {
                error_log('GD WebP conversion failed: ' . $e->getMessage());
            }
        }

        return false;
    }
}