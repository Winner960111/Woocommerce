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
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer-api.php';
require_once RUI_PLUGIN_DIR . 'includes/class-rapid-url-indexer.php';

// Activation/Deactivation hooks
register_activation_hook(__FILE__, array('Rapid_URL_Indexer_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Rapid_URL_Indexer_Deactivator', 'deactivate'));

add_action('plugins_loaded', array('Rapid_URL_Indexer', 'init'));
add_action('plugins_loaded', array('Rapid_URL_Indexer_Deactivator', 'init'));
Rapid_URL_Indexer_Admin::init();

add_action('init', array('Rapid_URL_Indexer_Customer', 'add_my_account_endpoints'));
add_action('woocommerce_account_rui-buy-credits_endpoint', array('Rapid_URL_Indexer_Customer', 'add_my_account_endpoint_content'));
