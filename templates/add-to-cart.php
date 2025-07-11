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

$is_available = SFE_Events::is_available_for_purchase( $event_post_id );
$stock_remaining = SFE_Events::get_event_stock_html( $event_post_id );
$stock_total = SFE_Events::get_stock_total( $event_post_id );
?>

<div class="sfe-product-cart-button product-id-<?php echo esc_attr( $product_id ); ?>">
	<div class="product-details">
		<div class="product-price">
			<?php echo $product->get_price_html(); ?>
		</div>
		<?php
		if ( $stock_remaining !== null ) {
			?>
			<div class="product-sep">
				&ndash;
			</div>
			<div class="product-stock">
				<?php echo $stock_remaining; ?>
			</div>
			<?php
		}
		?>
	</div>
	<?php
	if ( $is_available ) {
		
		// Based on: woocommerce_simple_add_to_cart();
		?>
		<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
			<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
			
			<?php
			do_action( 'woocommerce_before_add_to_cart_quantity' );
			
			$min = 1;
			$max = $stock_total === null ? '' : $stock_total;
			
			woocommerce_quantity_input(
				array(
					'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $min, $product ),
					'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $max, $product ),
					'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $min, // WPCS: CSRF ok, input var ok.
				)
			);
			
			do_action( 'woocommerce_after_add_to_cart_quantity' );
			?>
			
			<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
			
			<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
		</form>
		<?php
		
	}else{
		?>
		<div class="out-of-stock-button">
			<a href="#" class="button disabled" onclick="return false;" disabled>Out of Stock</a>
		</div>
		<?php
	}
	?>
</div>