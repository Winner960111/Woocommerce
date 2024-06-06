<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

// Delete custom tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}rapid_url_indexer_credits");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}rapid_url_indexer_projects");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}rapid_url_indexer_logs");
?>
