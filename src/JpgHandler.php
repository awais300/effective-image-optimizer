<?php
namespace AWP\IO;

class JpgHandler 
{
    public function __construct()
    {
        // Hook into HTML output to replace image URLs
        add_filter('the_content', [$this, 'replace_images_with_jpg'], 999);
        add_filter('wp_get_attachment_image_src', [$this, 'maybe_get_jpg_url'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'modify_image_srcset'], 10, 5);
    }

    /**
     * Check if JPG version exists for a given image URL - Database
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
                if(isset($size_data['webp']) && get_optimizer_settings('deliver_next_gen_images') === 'yes') {
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
     * Replace image URLs in content with JPG versions
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
     * Modify image source sets to include JPG versions
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
     * Maybe return JPG URL for attachment image
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