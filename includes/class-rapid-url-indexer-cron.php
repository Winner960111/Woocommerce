<?php
class Rapid_URL_Indexer_Cron {
    public static function init() {
        add_action('rui_daily_stats_update', array(__CLASS__, 'update_daily_stats'));
        
        if (!wp_next_scheduled('rui_daily_stats_update')) {
            $timestamp = strtotime('tomorrow 00:05:00 UTC');
            wp_schedule_event($timestamp, 'daily', 'rui_daily_stats_update');
        }
    }

    public static function update_daily_stats() {
        self::log_cron_execution('Update Daily Stats Started');

        global $wpdb;
        $projects_table = $wpdb->prefix . 'rapid_url_indexer_projects';

        $projects = $wpdb->get_results(
            "SELECT id FROM $projects_table 
            WHERE (status NOT IN ('completed', 'failed', 'refunded') 
            OR (status IN ('completed', 'failed', 'refunded') AND created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)))"
        );

        foreach ($projects as $project) {
            Rapid_URL_Indexer::update_daily_stats($project->id);
        }

        self::log_cron_execution('Update Daily Stats Completed');
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
