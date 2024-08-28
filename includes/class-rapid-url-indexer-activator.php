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
            project_name_hash varchar(64) NOT NULL,
            urls longtext NOT NULL,
            task_id varchar(255) DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            submitted_links int DEFAULT 0,
            indexed_links int DEFAULT 0,
            triggered_by varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            auto_refund_processed tinyint(1) DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            notify tinyint(1) DEFAULT 0,
            refunded_credits int DEFAULT 0,
            initial_report_sent tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Create logs table
        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            project_id mediumint(9) NOT NULL,
            triggered_by varchar(255) DEFAULT NULL,
            action varchar(255) NOT NULL,
            details longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $table_name = $wpdb->prefix . 'rapid_url_indexer_daily_stats';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            date date NOT NULL,
            indexed_count int NOT NULL,
            unindexed_count int NOT NULL,
            PRIMARY KEY  ~(id),
            UNIQUE KEY project_date (project_id, date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Check and add missing columns
        self::check_and_add_missing_columns();

        // Cron jobs are now scheduled in the main plugin file
    }
    private static function check_and_add_missing_columns() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rapid_url_indexer_logs';
        $column = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'triggered_by'");
        if (empty($column)) {
            $wpdb->query("ALTER TABLE $table_name ADD triggered_by varchar(255) DEFAULT NULL");
        }
    }
}
