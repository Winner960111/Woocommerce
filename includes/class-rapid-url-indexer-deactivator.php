<?php
class Rapid_URL_Indexer_Deactivator {
    public static function init() {
        self::deactivate();
        // Clear scheduled log purging
        $timestamp = wp_next_scheduled('rui_purge_logs');
        wp_unschedule_event($timestamp, 'rui_purge_logs');
    }
    
    public static function deactivate() {
        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('rui_cron_job');
        wp_unschedule_event($timestamp, 'rui_cron_job');
    }
}
