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
        // Direct hook into WordPress's file handling
        add_filter('wp_check_filetype_and_ext', array($this, 'intercept_file_checks'), 10, 5);
    }

    /**
     * Intercept file type checks to modify files before they're processed
     *
     * @param array $wp_check_filetype_and_ext File data including type information
     * @param string $file Full path to the file
     * @param string $filename The name of the file
     * @param array $mimes Array of allowed mime types
     * @param string $real_mime Real mime type of the file
     * @since 1.0.0
     * @return array Modified file data
     */
    public function intercept_file_checks($wp_check_filetype_and_ext, $file, $filename, $mimes, $real_mime = null)
    {
        // Only process supported image types
        if (!in_array($real_mime, $this->supported_types)) {
            return $wp_check_filetype_and_ext;
        }

        // Skip if already webp
        if ($real_mime === 'image/webp') {
            return $wp_check_filetype_and_ext;
        }

        // Create a temporary WebP version
        $webp_temp = $file . '.webp';

        if ($this->convert_to_webp($file, $webp_temp)) {
            // Replace the original file with the WebP version
            if (copy($webp_temp, $file)) {
                @unlink($webp_temp);

                // Update file information
                $wp_check_filetype_and_ext['type'] = 'image/webp';
                $wp_check_filetype_and_ext['ext'] = 'webp';
                $wp_check_filetype_and_ext['proper_filename'] = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
            }
        }

        return $wp_check_filetype_and_ext;
    }

    /**
     * Convert an image to WebP format using Imagick or GD as a fallback.
     *
     * This method handles the actual conversion of the image to WebP format. It first attempts to use
     * the Imagick extension if available. If Imagick is not available, it falls back to the GD library.
     *
     * @param string $source_path The path to the source image.
     * @param string $dest_path The path to save the converted WebP image.
     * @since 1.0.0
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
