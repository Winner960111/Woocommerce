<?php
/**
 * Plugin Name: Rapid URL Indexer
 * Description: WooCommerce extension for purchasing and managing URL indexing credits.
 * Version: 1.0.0
 * Author: Your Name
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
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-api.php';
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, array('Rapid_URL_Indexer_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Rapid_URL_Indexer_Deactivator', 'deactivate'));

add_action('plugins_loaded', array('Rapid_URL_Indexer', 'init'));

// Schedule auto refund cron job
if (!wp_next_scheduled('rui_auto_refund')) {
    wp_schedule_event(time(), 'daily', 'rui_auto_refund');
}
add_action('rui_auto_refund', array('Rapid_URL_Indexer', 'auto_refund'));
