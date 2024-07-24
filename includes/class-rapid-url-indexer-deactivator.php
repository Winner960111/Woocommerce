<?php
class Rapid_URL_Indexer_Deactivator {
    public static function init() {
        self::deactivate();
        // Clear scheduled cron jobs
        $cron_hooks = [
            'rui_check_abuse',
            'rui_update_project_status',
            'rui_purge_logs',
            'rui_purge_projects'  
        ];

        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
    
    public static function deactivate() {
        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('rui_cron_job');
        wp_unschedule_event($timestamp, 'rui_cron_job');
    }
}
