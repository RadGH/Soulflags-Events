<?php

class SFE_Cart {
	
	public function __construct() {
		
		// On the cart and checkout pages, below each product, add a button to edit the ticket details
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_edit_entries_cart_link' ), 20, 2 );
		
		// When visiting the checkout page, redirect to the ticket details form if any event products haven't been registered yet
		add_action( 'template_redirect', array( $this, 'checkout_redirect_to_ticket_form' ) );
		
		// Displays the ticket registration form when accessed by the url arg ?sfe_edit_tickets
		add_action( 'template_redirect', array( $this, 'display_ticket_form_page' ), 100 );
		
		// Save the ticket data when the form is submitted
		add_action( 'template_redirect', array( $this, 'save_ticket_entries_to_cart_meta' ) );
		
		// Load the ticket details with the cart session
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'load_ticket_entries_from_session' ), 10, 2 );
		
		// Add the event ID to the order item metadata.
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'add_ticket_entries_to_order_item_meta' ), 10, 3 );
		
		// Display the event ID in the admin order item details.
		add_filter( 'woocommerce_display_item_meta', array( $this, 'display_ticket_entries_on_order' ), 20, 3 );
		
		// Format the order item meta on the admin page
		add_action( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_admin_ticket_entries_meta' ), 20, 2 );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		
		return self::$instance;
	}
	
	// Utilities
	/**
	 * Get the URL for an event registration form for a given event product in the cart.
	 *
	 * @param string $cart_item_key
	 *
	 * @return string
	 */
	public static function get_ticket_url( $cart_item_key ) {
		$event_product = SFE_Orders::get_event_product_from_cart_by_key( $cart_item_key );
		if ( ! $event_product ) {
			wp_die( __( 'Invalid event product provided, cannot get ticket registration form url.', 'soulflags-events' ) );
			exit;
		}
		
		$url = site_url('/membership-classes/'); // @TODO: Add to settings page
		$url = add_query_arg( 'sfe_edit_tickets', $cart_item_key, $url );
		
		return $url;
	}
	
	/**
	 * Displays the event registration form for an event product in the cart.
	 *
	 * @param array $event_product {
	 *     @type string $cart_item_key
	 *     @type array $cart_item
	 *     @type int $product_id
	 *     @type int $event_post_id
	 * }
	 *
	 * @return void
	 */
	public static function display_event_registration_form( $event_product ) {
		$cart_item_key = $event_product['cart_item_key'];
		$cart_item = $event_product['cart_item'];
		$product_id = $event_product['product_id'];
		$event_post_id = $event_product['event_post_id'];
		
		include( SFE_PATH . '/templates/event-registration.php' );
	}
	
	/**
	 * Gets an array of the form entry for a given event product in the cart. Returns false if the form has not been submitted.
	 *
	 * @param string $cart_item_key
	 *
	 * @return array[]|false {
	 *     @type string $name
	 *     @type string $age
	 * }
	 */
	public static function get_ticket_entries( $cart_item_key ) {
		$cart_item = WC()->cart->cart_contents[ $cart_item_key ];
		
		return $cart_item['sfe_tickets'] ?? array();
	}
	
	// Actions
	
	/**
	 * On the cart and checkout pages, below each product, add a button to edit the ticket details
	 *
	 * @param array $item_data The item data.
	 * @param array $cart_item The cart item.
	 *
	 * @return array
	 */
	public function display_edit_entries_cart_link( $item_data, $cart_item ) {
		$product_id = $cart_item['product_id'] ?? false;
		if ( ! SFE_Orders::is_event_product( $product_id ) ) return $item_data;
		
		$event_post_id = (int) $cart_item['sfe_event_id'];
		if ( ! $event_post_id ) return $item_data;
		
		// Get ticket details
		$ticket_url = self::get_ticket_url( $cart_item['key'] );
		$entries = self::get_ticket_entries( $cart_item['key'] );
		
		// Display link to edit tickets (Except on checkout)
		if ( ! is_checkout()  ) {
			$button_label = $entries ? 'Register Tickets' : 'Edit Tickets';
			
			$item_data[] = array(
				'name'  => __( 'Tickets', 'soulflags-events' ),
				'value' => '<a href="' . esc_url( $ticket_url ) . '">' . $button_label . '</a>',
			);
		}
		
		// Everywhere: Display a list of tickets
		if ( ! empty($entries) ) foreach( $entries as $ticket_index => $entry ) {
			$name = $entry['name'] ?? '';
			$age = $entry['age'] ?? '';
			
			$display_label = trim( $name . ($age ? ' (age ' . $age . ')' : '') ); // Bob (age 30), John Smith (age 25)
			
			$item_data[] = array(
				'name'  => sprintf( __( 'Ticket #%d', 'soulflags-events' ), $ticket_index + 1 ),
				'value' => $display_label,
			);
		}
		
		return $item_data;
	}
	
	
	/**
	 * Display a form to collect ticket details before checking out
	 */
	public function checkout_redirect_to_ticket_form() {
		if ( ! is_checkout() ) return;

		// Get the event product from the cart
		$event_products = SFE_Orders::get_event_products_from_cart();
		
		if ( $event_products ) foreach( $event_products as $event_product ) {
			$tickets = self::get_ticket_entries( $event_product['cart_item_key'] );
			$ticket_count = count($tickets );
			$quantity = $event_product['quantity'] ?? 1;
			
			if ( $ticket_count < $quantity ) {
				// If the ticket count is less than the quantity, redirect to the ticket form
				$url = self::get_ticket_url( $event_product['cart_item_key'] );
				wp_redirect( $url );
				exit;
			}
		}
	}
	
	/**
	 * Displays the ticket registration form when accessed by the url arg ?sfe_edit_tickets
	 *
	 * @return void
	 */
	public function display_ticket_form_page() {
		$cart_item_key = isset( $_GET['sfe_edit_tickets'] ) ? sanitize_text_field( $_GET['sfe_edit_tickets'] ) : false;
		if ( ! $cart_item_key ) return;
		
		$event_product = SFE_Orders::get_event_product_from_cart_by_key( $cart_item_key );
		if ( ! $event_product ) {
			wp_die( __( 'Invalid event product selected. Cannot display ticket registration form.', 'soulflags-events' ) );
			exit;
		}
		
		// Display the event registration form
		self::display_event_registration_form( $event_product );
	}
	
	/**
	 * Save the ticket entries when the form is submitted
	 *
	 * @return void
	 */
	public function save_ticket_entries_to_cart_meta() {
		$action = $_POST['sfe_action'] ?? null;
		if ( $action != 'sfe_save_tickets' ) return;
		
		$nonce = $_POST['sfe_nonce'] ?? null;
		if ( ! wp_verify_nonce( $nonce, 'save-ticket' ) ) {
			wp_die( __( 'Session expired. Reload the page and try again.', 'soulflags-events' ) );
			exit;
		}
		
		// Check the item is in the cart
		$cart_item_key = $_POST['sfe_cart_item_key'] ?? null;
		$cart_item = &WC()->cart->cart_contents[ $cart_item_key ];
		if ( ! isset( $cart_item ) ) {
			wp_die( __( 'Event product was not found in cart. Cannot save ticket registration form.', 'soulflags-events' ) );
			exit;
		}
		
		$quantity = $cart_item['quantity'];
		$raw_tickets = $_POST['sfe_tickets'] ?? null;
		$tickets = array();
		
		for( $ticket_index = 0; $ticket_index < $quantity; $ticket_index++ ) {
			$ticket_name = isset( $raw_tickets[$ticket_index]['name'] ) ? sanitize_text_field( $raw_tickets[$ticket_index]['name'] ) : '';
			$ticket_age = isset( $raw_tickets[$ticket_index]['age'] ) ? sanitize_text_field( $raw_tickets[$ticket_index]['age'] ) : '';
			
			if ( ! empty($ticket_name) ) {
				$tickets[ $ticket_index ] = array(
					'name' => $ticket_name,
					'age'  => $ticket_age,
				);
			}
		}
		
		// Store ticket entries in the cart item data
		$cart_item['sfe_tickets'] = $tickets;
		
		// Save the cart session data
		WC()->cart->set_session();
		
		// Redirect to the checkout page
		$checkout_url = wc_get_checkout_url();
		$checkout_url = add_query_arg( 'sfe_edited_tickets', $cart_item_key, $checkout_url );
		wp_safe_redirect( $checkout_url );
		exit;
	}
	
	/**
	 * Load the event ID from session cart item data. Without this, the event ID would not persist when the cart is reloaded.
	 *
	 * @param array $cart_item The cart item data being restored.
	 * @param array $values    The original values stored in the session.
	 * @return array Modified cart item data with event ID if set.
	 */
	public function load_ticket_entries_from_session( $cart_item, $values ) {
		if ( isset( $values['sfe_tickets'] ) ) {
			$cart_item['sfe_tickets'] = $values['sfe_tickets'];
		}
		return $cart_item;
	}
	
	/**
	 * Add the event ID to the order item metadata.
	 *
	 * @param int    $item_id        Order item ID.
	 * @param array  $values         Cart item values.
	 * @param string $cart_item_key  Cart item key.
	 */
	public function add_ticket_entries_to_order_item_meta( $item_id, $values, $cart_item_key ) {
		if ( isset( $values['sfe_tickets'] ) ) {
			wc_add_order_item_meta( $item_id, '_sfe_tickets', $values['sfe_tickets'] );
		}
	}
	
	/**
	 * Display the event ID in the admin order item details.
	 *
	 * @param string       $html Displayed HTML.
	 * @param WC_Order_Item $item Order item object.
	 * @param array        $args Display args.
	 * @return string Modified HTML with event ID shown.
	 */
	public function display_ticket_entries_on_order( $html, $item, $args ) {
		$tickets = $item->get_meta( '_sfe_tickets' ) ?? false;
		
		if ( $tickets ) {
			foreach( $tickets as $ticket_index => $ticket ) {
				$name = isset( $ticket['name'] ) ? esc_html( $ticket['name'] ) : '';
				$age = isset( $ticket['age'] ) ? esc_html( $ticket['age'] ) : '';
				
				$display_label = trim( $name . ($age ? ' (age ' . $age . ')' : '') ); // Bob (age 30), John Smith (age 25)
				
				$html .= '<div><strong>Ticket #' . ( $ticket_index + 1 ) . ':</strong> ' . $display_label . '</div>';
			}
		}
		
		return $html;
	}
	
	/**
	 * Format the order item meta on the admin page
	 *
	 * @param array $formatted_meta The formatted meta data.
	 * @param WC_Order_Item $order_item The order item object.
	 *
	 * @return array
	 */
	public function format_admin_ticket_entries_meta( $formatted_meta, $order_item ) {
		if ( ! is_admin() ) return $formatted_meta;
		
		$product_id = $order_item->get_product_id();
		if ( ! SFE_Orders::is_event_product( $product_id ) ) return $formatted_meta;
		
		$tickets = $order_item->get_meta( '_sfe_tickets' );
		
		if ( ! $tickets ) {
			$formatted_meta[] = (object)array(
				'key'           => '_sfe_tickets',
				'value'         => '',
				'display_key'   => __( 'Tickets', 'soulflags-events' ),
				'display_value' => __( 'No tickets registered.', 'soulflags-events' ),
			);
		}else{
			foreach( $tickets as $ticket_index => $ticket ) {
				$name = isset( $ticket['name'] ) ? esc_html( $ticket['name'] ) : '';
				$age = isset( $ticket['age'] ) ? esc_html( $ticket['age'] ) : '';
				
				$display_key = sprintf( __( 'Ticket #%d', 'soulflags-events' ), $ticket_index + 1 );
				$display_label = trim( $name . ($age ? ' (age ' . $age . ')' : '') ); // Bob (age 30), John Smith (age 25)
				
				$formatted_meta[] = (object) array(
					'key'           => '_sfe_ticket_' . $ticket_index,
					'value'         => '',
					'display_key'   => $display_key,
					'display_value' => $display_label,
				);
			}
		}
		
		
		return $formatted_meta;
	}
	
}

SFE_Cart::get_instance();