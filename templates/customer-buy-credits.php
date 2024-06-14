<?php
$products = wc_get_products(array(
    'meta_key' => '_credits_amount',
    'meta_value' => '',
    'meta_compare' => '!=',
));
?>
<div class="rui-credits-display">
    <?php echo Rapid_URL_Indexer_Customer::credits_display(false); ?>
</div>
<?php if (!empty($products)): ?>
    <h2>Available Packages:</h2>
    <ul class="rui-credits-products">
        <?php foreach ($products as $product): ?>
            <li>
                <a href="<?php echo esc_url('?add-to-cart=' . $product->get_id()); ?>"><?php echo esc_html($product->get_name()); ?></a><span class="rui-credits-amount"> <i>(Guaranteed to get <?php echo esc_html(get_post_meta($product->get_id(), '_credits_amount', true)); ?> URLs indexed)</i></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No credit packages are currently available.</p>
<?php endif; ?>
