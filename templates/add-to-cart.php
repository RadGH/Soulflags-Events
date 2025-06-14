<?php

/**
 * This template displays the "Add to Cart" button for event products.
 *
 * @global int $event_post_id
 * @global int $product_id
 * @global WC_Product $product
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

if ( ! $product->is_purchasable() ) {
	return;
}

?>

<div class="sfe-product-cart-button product-id-<?php echo esc_attr( $product_id ); ?>">
	<div class="product-details">
		<div class="product-price">
			<?php echo $product->get_price_html(); ?>
		</div>
		<?php
		if ( $product->is_in_stock() && $product->managing_stock() ) {
			?>
			<div class="product-sep">
				&ndash;
			</div>
			<div class="product-stock">
				<?php echo wc_get_stock_html( $product ); ?>
			</div>
			<?php
		}
		?>
	</div>
	<?php
	if ( $product->is_in_stock() ) {
		woocommerce_simple_add_to_cart();
	}else{
		?>
		<div class="out-of-stock-button">
			<a href="#" class="button disabled" onclick="return false;" disabled>Out of Stock</a>
		</div>
		<?php
	}
	?>
</div>