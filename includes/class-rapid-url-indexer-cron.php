<?php
/**
 * Class responsible for managing cron jobs related to the Rapid URL Indexer plugin.
 * It includes methods to initialize and remove old cron jobs.
 */
class Rapid_URL_Indexer_Cron {
    /**
     * Initializes the cron management by setting up actions to remove old cron jobs.
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'remove_old_cron_job'));
    }

    /**
     * Removes old cron jobs that are no longer needed.
     */
    public static function remove_old_cron_job() {
        wp_clear_scheduled_hook('rui_daily_stats_update');
    }

    /**
     * Logs the execution of a cron job to the database.
     *
     * @param string $action The action performed by the cron job.
     */
    private static function log_cron_execution($action) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'rapid_url_indexer_logs', array(
            'user_id' => 0,
            'project_id' => 0,
            'triggered_by' => 'Cron',
            'action' => $action,
            'details' => '',
            'created_at' => current_time('mysql')
        ));
    }
}
