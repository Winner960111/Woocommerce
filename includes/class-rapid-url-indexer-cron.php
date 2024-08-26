<?php
class Rapid_URL_Indexer_Cron {
    public static function init() {
        add_action('init', array(__CLASS__, 'remove_old_cron_job'));
    }

    public static function remove_old_cron_job() {
        wp_clear_scheduled_hook('rui_daily_stats_update');
    }

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
