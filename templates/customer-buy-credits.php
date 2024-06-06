<?php
$products = wc_get_products(array(
    'meta_key' => '_credits_amount',
    'meta_value' => '',
    'meta_compare' => '!=',
));
?>

<h2>Buy Indexing Credits</h2>

<?php if (!empty($products)): ?>
    <ul class="rui-credits-products">
        <?php foreach ($products as $product): ?>
            <li>
                <a href="<?php echo esc_url($product->get_permalink()); ?>">
                    <?php echo esc_html($product->get_name()); ?>
                </a>
                <span class="rui-credits-amount"><?php echo esc_html(get_post_meta($product->get_id(), '_credits_amount', true)); ?> Credits</span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No credit packages are currently available.</p>
<?php endif; ?>
