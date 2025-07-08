<?php

/**
 * This template displays the registration form for an event. You can provide one entry per quantity of product in your cart.
 *
 * Template is loaded by:
 * @see SFE_Cart::display_event_registration_form()
 *
 * @global string $cart_item_key
 * @global array $cart_item
 * @global int $product_id
 * @global int $event_post_id
 */

if ( ! isset($cart_item_key) || ! isset($cart_item) || ! isset($product_id) || ! isset($event_post_id) ) {
	wp_die( 'Required variables are missing from ' . basename(__FILE__) );
	exit;
}

// Prevent the page content from being loaded by Divi
global $wp_query, $post;

$wp_query->is_404 = false;
$wp_query->found_posts = 0;
$wp_query->posts = array();

// Get ticket index to modify
$price = isset( $cart_item['data'] ) ? $cart_item['data']->get_price() : null;
$quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;

// Get the current ticket details
if ( isset($_POST['sfe_tickets']) ) {
	// #1: Prefer submitted data if available
	$ticket_data = $_POST['sfe_tickets'];
}else if ( isset( $cart_item['sfe_tickets'] ) ) {
	// #2: Display cart data
	$ticket_data = $cart_item['sfe_tickets'];
}else{
	// #3: No data
	$ticket_data = array();
}

// Page Settings
$page_title = __( 'Event Registration Form', 'soulflags-events' );
$page_description = __( 'Please fill out the form below to register for the event.', 'soulflags-events' );
$submit_label = __( 'Save Tickets', 'soulflags-events' );

$event_title = get_the_title( $event_post_id ) ?: '(No event title set)';
$event_date = SFE_Events::get_event_date_range( $event_post_id ) ?: '(No event date set)';
$event_price = $price ?: '(No price set)';
$event_stock = SFE_Events::get_stock_html( $quantity, '%d ticket', '(No tickets in cart)' );

$event_url = get_permalink( $event_post_id );

// Add body classes
add_filter( 'body_class', function( $classes ) {
	$classes[] = 'sfe-event-registration-form';
	$classes[] = 'et_full_width_page'; // Divi: Full width page (no sidebar)
	return $classes;
} );

get_header();

$is_page_builder_used = false;

?>
	
	<div id="main-content">
		<div class="container">
			<div id="content-area" class="clearfix">
				<div id="left-area">
					
					<?php while ( have_posts() ) : the_post(); ?>
						
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							
								<h1 class="entry-title main_title"><?php echo $page_title; ?></h1>
								
								<?php
								$thumb = '';
								
								$width = (int) apply_filters( 'et_pb_index_blog_image_width', 1080 );
								
								$height = (int) apply_filters( 'et_pb_index_blog_image_height', 675 );
								$classtext = 'et_featured_image';
								$titletext = get_the_title();
								$alttext = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );
								$thumbnail = get_thumbnail( $width, $height, $classtext, $alttext, $titletext, false, 'Blogimage' );
								$thumb = $thumbnail["thumb"];
								
								if ( 'on' === et_get_option( 'divi_page_thumbnails', 'false' ) && '' !== $thumb )
									print_thumbnail( $thumb, $thumbnail["use_timthumb"], $alttext, $width, $height );
								?>
							
							<div class="entry-content">
								<?php
								echo wpautop($page_description);
								?>
							</div>
							
							<form class="sfe-registration-form" action="" method="POST">
								
								<input type="hidden" name="sfe_cart_item_key" value="<?php echo esc_attr( $cart_item_key ); ?>" />
								<input type="hidden" name="sfe_action" value="sfe_save_tickets" />
								<input type="hidden" name="sfe_nonce" value="<?php echo esc_attr( wp_create_nonce('save-ticket' ) ); ?>" />
								
								<div class="sfe-modal">
									<div class="sfe-modal-heading">
										<h2>Event Details</h2>
									</div>
									
									<div class="sfe-modal-content sfe-event-summary">
										<h3><a href="<?php echo esc_url($event_url); ?>"><?php echo $event_title; ?></a></h3>
										
										<div class="event-meta">
											<div class="meta-date"><?php echo $event_date; ?></div>
											<div class="sep">&bull;</div>
											<div class="meta-price"><?php echo wc_price($event_price); ?></div>
											<div class="sep">&bull;</div>
											<div class="meta-stock"><?php echo $event_stock; ?></div>
										</div>
									</div>
								</div>
								
								<div class="sfe-modal">
									<div class="sfe-modal-heading">
										<h2>Registration</h2>
									</div>
									
									<div class="sfe-ticket-list">
										<?php
										// Loop through each ticket and display fields
										for ( $ticket_index = 0; $ticket_index < $quantity; $ticket_index++ ) {
											$entry = $ticket_data[ $ticket_index ] ?? array();
											$name = $entry['name'] ?? '';
											$age = $entry['age'] ?? '';
											
											$name_id = 'name-' . $ticket_index;
											$age_id = 'age-' . $ticket_index;
											?>
											<div class="sfe-modal-content sfe-ticket-entry">
												
												<h3 class="ticket-entry-title"><?php echo 'Ticket #' . ( $ticket_index + 1 ); ?></h3>
												
												<div class="sfe-form-fields">
													<div class="field field-text">
														<label for="<?php echo $name_id; ?>">Name <span class="prereq required">(Required)</span></label>
														<input type="text" id="<?php echo $name_id; ?>" name="sfe_tickets[<?php echo $ticket_index; ?>][name]" value="<?php echo esc_attr( $name ); ?>" required />
													</div>
													
													<div class="field field-number">
														<label for="<?php echo $age_id; ?>">Age <span class="prereq optional">(Optional)</span></label>
														<input type="number" id="<?php echo $age_id; ?>" name="sfe_tickets[<?php echo $ticket_index; ?>][age]" value="<?php echo esc_attr( $age ); ?>" min="0" step="1" max="99" />
													</div>
												</div>
												
											</div>
											<?php
										}
										?>
									</div>
								</div>
								
								<div class="sfe-submit">
									<button type="submit" class="button button-primary"><?php echo $submit_label; ?></button>
									
									<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button button-secondary sfe-cancel-registration">Return to Cart</a>
								</div>
								
							</form>
						
						</article>
					
					<?php endwhile; ?>
					
				</div>
			</div>
		</div>
	</div>

<?php

get_footer();