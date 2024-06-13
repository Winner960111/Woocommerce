<?php
/**
 * My Account - My Projects
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

wc_get_template('myaccount/navigation.php');
?>

<div class="woocommerce-MyAccount-content">
    <h1 class="woocommerce-MyAccount-title"><?php esc_html_e('My Indexing Projects', 'rapid-url-indexer'); ?></h1>
    <?php include RUI_PLUGIN_DIR . 'templates/customer-projects.php'; ?>
</div>
