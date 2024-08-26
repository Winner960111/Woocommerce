<?php
class Rapid_URL_Indexer_Cron {
    public static function init() {
        if (!wp_next_scheduled('rui_daily_stats_update')) {
            $timestamp = strtotime('tomorrow 00:05:00 UTC');
            wp_schedule_event($timestamp, 'daily', 'rui_daily_stats_update');
        }
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
