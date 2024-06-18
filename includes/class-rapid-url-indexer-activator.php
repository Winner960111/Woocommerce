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
            task_id varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            triggered_by varchar(255) DEFAULT NULL,
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

        // Create backlog table
        $table_name = $wpdb->prefix . 'rapid_url_indexer_backlog';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            urls longtext NOT NULL,
            notify tinyint(1) NOT NULL,
            retries int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Schedule hourly cron job to update project status
        if (!wp_next_scheduled('rui_cron_job')) {
            wp_schedule_event(time(), 'hourly', 'rui_cron_job');
        }
        // Schedule abuse check
        if (!wp_next_scheduled('rui_check_abuse')) {
            wp_schedule_event(time(), 'daily', 'rui_check_abuse');
        }

        // Schedule project status update
        if (!wp_next_scheduled('rui_update_project_status')) {
            wp_schedule_event(time(), 'hourly', 'rui_update_project_status');
        }

        // Schedule backlog processing
        if (!wp_next_scheduled('rui_process_backlog')) {
            wp_schedule_event(time(), 'hourly', 'rui_process_backlog');
        }

        // Schedule project purging
        if (!wp_next_scheduled('rui_purge_projects')) {
            wp_schedule_event(time(), 'daily', 'rui_purge_projects');
        }

        // Schedule log purging
        if (!wp_next_scheduled('rui_purge_logs')) {
            wp_schedule_event(time(), 'daily', 'rui_purge_logs');
        }

        // Schedule project age limit purging
        if (!wp_next_scheduled('rui_purge_old_projects')) {
            wp_schedule_event(time(), 'daily', 'rui_purge_old_projects');
        }
    }
}
