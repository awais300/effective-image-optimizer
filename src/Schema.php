<?php

namespace AWP\IO;

/**
 * Class Schema
 *
 * This class is responsible for defining and managing the database schema for optimization statistics.
 * It extends the Singleton class to ensure only one instance of the schema manager is created.
 *
 * @package AWP\IO
 */
class Schema extends Singleton
{
    /**
     * The name of the optimization history table.
     *
     * @var string
     */
    public const HISTORY_TABLE_NAME = 'awp_optimization_history';

    /**
     * The name of the optimization history table.
     *
     * @var string
     */
    public const REOPTIMIZATION_TABLE_NAME = 'awp_reoptimize_processed';

    /**
     * Create the optimization stats table.
     *
     * This method defines the structure of the optimization history table and creates it if it doesn't already exist.
     * The table stores statistics related to image optimizations, such as savings and conversions.
     * @since 1.0.0
     * @return void
     */
    public function create_optimization_stats_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::HISTORY_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attachment_id BIGINT UNSIGNED NOT NULL,
            normal_savings BIGINT UNSIGNED NOT NULL DEFAULT 0,
            webp_savings BIGINT UNSIGNED NOT NULL DEFAULT 0,
            png_to_jpg_conversions INT UNSIGNED NOT NULL DEFAULT 0,
            webp_conversions INT UNSIGNED NOT NULL DEFAULT 0,
            optimized_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY attachment_id (attachment_id) -- Add this unique key to make $wpdb->replace to work properly.
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Creates a database table for reoptimization records if it does not already exist.
     * @since 1.0.0
     * @return void
     */
    public function create_reoptimization_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::REOPTIMIZATION_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            image_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY image_id (image_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }


    /**
     * Truncates the reoptimization table by deleting all rows.
     * @since 1.0.0
     */
    public function truncate_reoptimization_table()
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}" . self::REOPTIMIZATION_TABLE_NAME);
    }
}
