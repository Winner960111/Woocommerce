<?php
/**
 * Class responsible for deactivating the Rapid URL Indexer plugin.
 * It handles the cleanup of scheduled cron jobs upon deactivation.
 */
class Rapid_URL_Indexer_Deactivator {
    /**
     * Initializes the deactivation process by clearing scheduled cron jobs.
     */
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
    
    /**
     * Deactivates the plugin by unscheduling the main cron job.
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('rui_cron_job');
        wp_unschedule_event($timestamp, 'rui_cron_job');
    }
}
