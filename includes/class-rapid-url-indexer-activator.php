<?php
class Rapid_URL_Indexer_Activator {
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create credits table
        $table_name = $wpdb->prefix . 'rapid_url_indexer_credits';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            credits int NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Create projects table
        $table_name = $wpdb->prefix . 'rapid_url_indexer_projects';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            project_name varchar(255) NOT NULL,
            urls longtext NOT NULL,
            status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            auto_refund_processed tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Create logs table
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            project_id mediumint(9) NOT NULL,
            action varchar(255) NOT NULL,
            details longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Schedule cron jobs
        if (!wp_next_scheduled('rui_cron_job')) {
            wp_schedule_event(time(), 'hourly', 'rui_cron_job');
        }
    }
}
