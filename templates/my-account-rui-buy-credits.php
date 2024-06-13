<?php
/**
 * My Account - Buy Credits
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

wc_get_template('myaccount/navigation.php');
?>

<div class="woocommerce-MyAccount-content">
    <h1 class="woocommerce-MyAccount-title"><?php esc_html_e('Buy Indexing Credits', 'rapid-url-indexer'); ?></h1>
    <?php include RUI_PLUGIN_DIR . 'templates/customer-buy-credits.php'; ?>
</div>
