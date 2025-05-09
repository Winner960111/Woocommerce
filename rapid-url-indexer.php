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
define('RUI_PLUGIN_FILE', __FILE__);
define('RUI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RUI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RUI_DB_VERSION', '1.1');

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

// Add an update routine
function rui_update_routine() {
    $current_version = get_option('rui_version', '0');
    $new_version = '1.2'; // Update this with your new version number

    if (version_compare($current_version, $new_version, '<')) {
        // Clear the old cron job if it exists
        $timestamp = wp_next_scheduled('rui_daily_stats_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rui_daily_stats_update');
        }

        // Update the stored version number
        update_option('rui_version', $new_version);
    }
}
add_action('plugins_loaded', 'rui_update_routine');

// Ensure database is updated on plugin update
function rapid_url_indexer_update_db_check() {
    $current_db_version = get_option('rapid_url_indexer_db_version', '0');
    if (version_compare($current_db_version, RUI_DB_VERSION, '<')) {
        error_log('Updating Rapid URL Indexer database from version ' . $current_db_version . ' to ' . RUI_DB_VERSION);
        Rapid_URL_Indexer::update_database();
        update_option('rapid_url_indexer_db_version', RUI_DB_VERSION);
        error_log('Rapid URL Indexer database update completed');
    }
}
add_action('plugins_loaded', 'rapid_url_indexer_update_db_check');

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
    $schedules['twicedaily'] = array(
        'interval' => 12 * HOUR_IN_SECONDS,
        'display'  => esc_html__('Twice Daily'),
    );
    return $schedules;
}

register_activation_hook(__FILE__, 'rui_schedule_cron_jobs');

function rui_schedule_cron_jobs() {
    $cron_jobs = array(
        'rui_cron_job' => 'twicedaily',
        'rui_check_abuse' => 'daily',
        'rui_purge_logs' => 'daily',
        'rui_purge_projects' => 'daily'
    );

    foreach ($cron_jobs as $job => $recurrence) {
        if (!wp_next_scheduled($job)) {
            wp_schedule_event(time(), $recurrence, $job);
        }
    }

    // Ensure the old cron job is removed
    wp_clear_scheduled_hook('rui_daily_stats_update');
}

// Function to remove old cron job
function rui_remove_old_cron_job() {
    wp_clear_scheduled_hook('rui_daily_stats_update');
}

// Run this function on plugin activation and deactivation
register_activation_hook(__FILE__, 'rui_remove_old_cron_job');
register_deactivation_hook(__FILE__, 'rui_remove_old_cron_job');


// Reschedule cron jobs after plugin update
add_action('upgrader_process_complete', 'rui_reschedule_cron_jobs', 10, 2);

function rui_reschedule_cron_jobs($upgrader_object, $options) {
    if ($options['action'] == 'update' && $options['type'] == 'plugin' && isset($options['plugins'])) {
        foreach($options['plugins'] as $plugin) {
            if ($plugin == plugin_basename(__FILE__)) {
                rui_schedule_cron_jobs();
            }
        }
    }
}

// This section is no longer needed as it's handled in initialize_cron_jobs()
