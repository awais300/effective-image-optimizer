<?php
/**
 * Admin settings page template for the Effective Image Optimizer plugin.
 * 
 * This template renders the admin settings interface with three main sections:
 * 1. General Settings - Basic configuration including API key and backup options
 * 2. Advanced Settings - Advanced features like WebP conversion
 * 3. Optimization Settings - Batch optimization controls and statistics
 *
 * @package AWP\IO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1><?php _e('Image Optimizer Settings', 'text-domain'); ?></h1>
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'text-domain'); ?></a>
        <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'text-domain'); ?></a>
        <a href="#optimization" class="nav-tab"><?php _e('Bulk Optimization', 'text-domain'); ?></a>
        <a href="#bulk-restore" class="nav-tab"><?php _e('Bulk Restore', 'text-domain'); ?></a>
    </h2>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="save_image_optimizer_options">
        <?php wp_nonce_field('save_image_optimizer_options', 'image_optimizer_nonce'); ?>

        <div id="general" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="api_key"><?php _e('API Key', 'text-domain'); ?></label></th>
                    <td><input type="text" name="api_key" id="api_key" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Thumbnail Compression', 'text-domain'); ?></th>
                    <td>
                        <label><input type="radio" name="thumbnail_compression" value="yes" <?php checked($settings['thumbnail_compression'], 'yes'); ?>> <?php _e('Yes', 'text-domain'); ?></label>
                        <label><input type="radio" name="thumbnail_compression" value="no" <?php checked($settings['thumbnail_compression'], 'no'); ?>> <?php _e('No', 'text-domain'); ?></label>
                        <p class="description"><?php _e('Compress thumbnail images.', 'text-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Backup', 'text-domain'); ?></th>
                    <td>
                        <label><input type="radio" name="backup" value="yes" <?php checked($settings['backup'], 'yes'); ?>> <?php _e('Yes', 'text-domain'); ?></label>
                        <label><input type="radio" name="backup" value="no" <?php checked($settings['backup'], 'no'); ?>> <?php _e('No', 'text-domain'); ?></label>

                        <p class="description"><?php _e('Create a backup of the original images, saved on your server in /wp-content/uploads/awp-io-backups/.', 'text-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Remove EXIF', 'text-domain'); ?></th>
                    <td>
                        <label><input type="radio" name="remove_exif" value="yes" <?php checked($settings['remove_exif'], 'yes'); ?>> <?php _e('Yes', 'text-domain'); ?></label>
                        <label><input type="radio" name="remove_exif" value="no" <?php checked($settings['remove_exif'], 'no'); ?>> <?php _e('No', 'text-domain'); ?></label>
                        <p class="description"><?php _e('Remove the EXIF tag of the image (recommended).', 'text-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Resize Large Images', 'text-domain'); ?></th>
                    <td>
                        <input type="number" name="resize_large_images_width" value="<?php echo esc_attr($settings['resize_large_images']['width']); ?>" class="small-text"> <?php _e('px Width', 'text-domain'); ?>
                        <input type="number" name="resize_large_images_height" value="<?php echo esc_attr($settings['resize_large_images']['height']); ?>" class="small-text"> <?php _e('px Height (preserves the original aspect ratio and doesn\'t crop the image)', 'text-domain'); ?>
                        <br />
                        <br />
                        <label><input type="radio" name="resize_large_images_option" value="cover" <?php checked($settings['resize_large_images']['option'], 'cover'); ?>> <?php _e('Cover', 'text-domain'); ?></label>
                        <label><input type="radio" name="resize_large_images_option" value="contain" <?php checked($settings['resize_large_images']['option'], 'contain'); ?>> <?php _e('Contain', 'text-domain'); ?></label>
                    </td>
                </tr>
            </table>
        </div>

        <div id="advanced" class="tab-content" style="display:none;">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Next Generation Images', 'text-domain'); ?></th>
                    <td>
                        <label><input type="radio" name="next_gen_images" value="yes" <?php checked($settings['next_gen_images'], 'yes'); ?>> <?php _e('Yes', 'text-domain'); ?></label>
                        <label><input type="radio" name="next_gen_images" value="no" <?php checked($settings['next_gen_images'], 'no'); ?>> <?php _e('No', 'text-domain'); ?></label>
                        <p class="description"><?php _e('Create WebP versions of the images.', 'text-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Deliver Next Generation Images', 'text-domain'); ?></th>
                    <td>
                        <label><input type="radio" name="deliver_next_gen_images" value="yes" <?php checked($settings['deliver_next_gen_images'], 'yes'); ?>> <?php _e('Yes', 'text-domain'); ?></label>
                        <label><input type="radio" name="deliver_next_gen_images" value="no" <?php checked($settings['deliver_next_gen_images'], 'no'); ?>> <?php _e('No', 'text-domain'); ?></label>
                        <p class="description"><?php _e('Deliver the next generation versions of the images in the front-end.', 'text-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Optimize Media on Upload', 'text-domain'); ?></th>
                    <td>
                        <label><input type="radio" name="optimize_media_upload" value="yes" <?php checked($settings['optimize_media_upload'], 'yes'); ?>> <?php _e('Yes', 'text-domain'); ?></label>
                        <label><input type="radio" name="optimize_media_upload" value="no" <?php checked($settings['optimize_media_upload'], 'no'); ?>> <?php _e('No', 'text-domain'); ?></label>
                        <p class="description"><?php _e('Automatically optimize images after they are uploaded (recommended).', 'text-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Convert PNG Images to JPEG', 'text-domain'); ?></th>
                    <td>
                        <label><input type="radio" name="convert_png_to_jpeg" value="yes" <?php checked($settings['convert_png_to_jpeg'], 'yes'); ?>> <?php _e('Yes', 'text-domain'); ?></label>
                        <label><input type="radio" name="convert_png_to_jpeg" value="no" <?php checked($settings['convert_png_to_jpeg'], 'no'); ?>> <?php _e('No', 'text-domain'); ?></label>
                        <p class="description"><?php _e('Automatically convert the PNG images to JPEG, if possible.', 'text-domain'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Exclude Thumbnail Sizes', 'text-domain'); ?></th>
                    <td>
                        <?php
                        $sizes = get_intermediate_image_sizes();
                        foreach ($sizes as $size) {
                            $checked = in_array($size, $settings['exclude_thumbnail_sizes'], true) ? 'checked' : '';
                            echo '<label><input type="checkbox" name="exclude_thumbnail_sizes[]" value="' . esc_attr($size) . '" ' . $checked . '> ' . esc_html($size) . '</label><br>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Optimization Tab Content -->
        <div id="optimization" class="tab-content" style="display: none;">
            <div class="optimization-container">
                <h3><?php _e('Start Optimization', 'text-domain'); ?></h3>
                <p>
                    <?php 
                    _e('Click the button below to start the optimization process.', 'text-domain'); 
                    ?>
                </p>
                <button id="start-optimization-button" class="button button-primary">
                    Start Optimization
                </button>

                <div id="progress-container" class="progress-section" style="display: none;">
                    <div class="progress-status">
                        <span class="spinner is-active"></span>
                        <span id="status-text">Optimizing images...</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="progress-bar" class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div id="progress-text" class="progress-text"></div>
                </div>

                <div id="optimization-results" class="results-container" style="display: none;"></div>
            </div>

            <div class="optimization-stats-container" style="display:none;">
                <button id="get-stats-button" class="button button-secondary">
                    <?php _e('See Latest Stats', 'text-domain'); ?>
                </button>
                
                <!-- Progress Section -->
                <div id="stats-progress-container" style="display: none;">
                    <div class="progress-status">
                        <span class="spinner is-active"></span>
                        <span id="stats-progress"></span>
                    </div>
                    <div class="stat-progress-bar-container">
                        <div id="stat-progress-bar" class="stat-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Unoptimized Images Notice -->
                <?php if($has_unoptimized_images): ?>
                    <div id="unoptimized-notice" class="notice notice-warning">
                        <?php _e('We found some unoptimized images in your media library. Click "Start Optimization" to optimize them.', 'text-domain'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Display -->
                <div id="stats-display" class="stats-card" style="display: none;">
                    <h3><?php _e('Optimization Statistics', 'text-domain'); ?></h3>
                    
                    <div class="stats-grid">
                        <!-- Total Savings Section -->
                        <div class="stats-section">
                            <h4><?php _e('Total Savings', 'text-domain'); ?></h4>
                            <div class="stat-item">
                                <span class="stat-label"><?php _e('WebP Savings:', 'text-domain'); ?></span>
                                <span id="webp-savings" class="stat-value">-</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php _e('Normal Savings:', 'text-domain'); ?></span>
                                <span id="normal-savings" class="stat-value">-</span>
                            </div>
                        </div>

                        <!-- Conversion Stats Section -->
                        <div class="stats-section">
                            <h4><?php _e('Conversions', 'text-domain'); ?></h4>
                            <div class="stat-item">
                                <span class="stat-label"><?php _e('WebP Images:', 'text-domain'); ?></span>
                                <span id="webp-conversions" class="stat-value">-</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php _e('PNG to JPG:', 'text-domain'); ?></span>
                                <span id="png-jpg-conversions" class="stat-value">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Last Updated -->
                    <div class="stats-footer">
                        <span id="last-updated" class="text-muted"></span>
                    </div>
                </div>
            </div>

        </div>
        <!-- Bulk Restore Tab -->
        <div id="bulk-restore" class="tab-content" style="display: none;">
            <div class="optimization-container">
                <h3><?php _e('Bulk Restore Images', 'text-domain'); ?></h3>
                <p><?php _e('Click the button below to restore all optimized images to their original versions.', 'text-domain'); ?></p>
                
                <button id="start-restore-button" class="button button-primary">
                    <?php _e('Start Restore', 'text-domain'); ?>
                </button>

                <div id="restore-progress-container" class="progress-section" style="display: none;">
                    <div class="progress-status">
                        <span class="spinner is-active"></span>
                        <span id="restore-status-text">Restoring images...</span>
                    </div>
                    <div class="progress-bar-container">
                        <div id="restore-progress-bar" class="progress-bar" role="progressbar" 
                             aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div id="restore-progress-text" class="progress-text"></div>
                </div>

                <div id="restore-results" class="results-container" style="display: none;"></div>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
