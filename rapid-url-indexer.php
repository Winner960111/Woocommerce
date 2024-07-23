<?php
/**
 * Plugin Name: Rapid URL Indexer
 * Description: WooCommerce extension for selling and managing URL indexing credits.
 * Version: 1.0
 * Author: RapidURLIndexer.com
 * Text Domain: rapid-url-indexer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Define constants
define('RUI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RUI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-activator.php';
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-deactivator.php';
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-admin.php';
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-customer.php';
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-cron.php';
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-api.php';

// Initialize the cron job
Rapid_URL_Indexer_Cron::init();
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, array('Rapid_URL_Indexer_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Rapid_URL_Indexer_Deactivator', 'deactivate'));

function rapid_url_indexer_init() {
    Rapid_URL_Indexer::init();
    Rapid_URL_Indexer_Customer::init();
    Rapid_URL_Indexer_Cron::init();
    Rapid_URL_Indexer_Admin::init();
}
add_action('plugins_loaded', 'rapid_url_indexer_init');

register_activation_hook(__FILE__, 'rui_flush_rewrite_rules');
function rui_flush_rewrite_rules() {
    Rapid_URL_Indexer_Customer::add_my_account_endpoints();
    flush_rewrite_rules();
}

add_action('admin_init', array('Rapid_URL_Indexer_Admin', 'register_settings'));

// Ensure cron jobs are scheduled on plugin activation
// Add custom cron schedule
add_filter('cron_schedules', 'rui_add_cron_interval');
function rui_add_cron_interval($schedules) {
    $schedules['six_hourly'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => esc_html__('Every 6 hours'),
    );
    return $schedules;
}

register_activation_hook(__FILE__, 'rui_schedule_cron_jobs');
function rui_schedule_cron_jobs() {
    if (!wp_next_scheduled('rui_process_backlog')) {
        wp_schedule_event(time(), 'six_hourly', 'rui_process_backlog');
    }
}

// Hook the process_backlog method to the rui_process_backlog action
add_action('rui_process_backlog', array('Rapid_URL_Indexer', 'process_backlog'));

// Set up dedicated cron job for processing backlog
function rui_setup_backlog_cron() {
    if (!wp_next_scheduled('rui_process_backlog')) {
        wp_schedule_event(time(), 'six_hourly', 'rui_process_backlog');
    }
}
add_action('init', 'rui_setup_backlog_cron');

// Hook the process_backlog method to the rui_process_backlog action
add_action('rui_process_backlog', array('Rapid_URL_Indexer', 'process_backlog'));
