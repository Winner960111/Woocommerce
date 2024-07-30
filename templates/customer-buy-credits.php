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
        <?php foreach ($products as $product): 
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            $current_price = $sale_price ? $sale_price : $regular_price;
            $credits_amount = get_post_meta($product->get_id(), '_credits_amount', true);
            $cost_per_credit = $current_price / $credits_amount;
            
            if ($sale_price) {
                $savings_amount = $regular_price - $sale_price;
                $savings_percentage = ($savings_amount / $regular_price) * 100;
            }
        ?>
            <li>
                <a href="<?php echo esc_url('?add-to-cart=' . $product->get_id()); ?>"><?php echo esc_html($product->get_name()); ?></a>
                <span class="rui-credits-amount">
                    <i>(<?php echo esc_html($credits_amount); ?> credits)</i>
                </span>
                <?php if ($sale_price): ?>
                    <span class="rui-savings">
                        <strong><?php echo sprintf('%.1f%% off', $savings_percentage); ?></strong>
                        <span class="rui-savings-amount">(Save $<?php echo number_format($savings_amount, 2); ?>)</span>
                    </span>
                <?php endif; ?>
                <span class="rui-cost-per-credit">
                    $<?php echo number_format($cost_per_credit, 3); ?> per credit
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No credit packages are currently available.</p>
<?php endif; ?>
