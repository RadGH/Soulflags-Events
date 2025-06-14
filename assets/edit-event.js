(function() {

	// Get settings object, see settings.php -> enqueue_admin_assets()
	const sfeEditEventData = window.sfeEditEventData || {};

	// Disable debug mode if console.log is not available
	if ( typeof console !== 'object' || typeof console.log !== 'function' ) {
		sfeEditEventData.debug_mode = false;
	}

	// Get individual settings
	const nonce = sfeEditEventData.nonce || '';
	const ajax_url = sfeEditEventData.ajax_url || '';
	const debug_mode = sfeEditEventData.debug_mode || false;
	const event_post_id = sfeEditEventData.event_post_id || false;
	const event_price = sfeEditEventData.event_price || false;
	const product_id = sfeEditEventData.product_id || false;
	const product_title = sfeEditEventData.product_title || false;
	const product_url = sfeEditEventData.product_url || false;
	const edit_product_url = sfeEditEventData.edit_product_url || false;

	// Console log functions (only outputs if debug mode is enabled)
	const console_log = function() {
		if ( debug_mode && typeof console.log === 'function' ) {
			console.log.apply(console, arguments);
		}
	}

	const console_warn = function() {
		if ( debug_mode && typeof console.warn === 'function' ) {
			console.warn.apply(console, arguments);
			console.trace();
		}
	}

	const console_error = function() {
		if ( debug_mode && typeof console.error === 'function' ) {
			console.error.apply(console, arguments);
			console.trace();
		}
	}

	/**
	 * Initialize the script after the DOM is ready.
	 */
	const init = function() {

		if ( typeof pagenow === 'undefined' ) {
			console_warn( 'pagenow is not defined. This script may not be running in the correct context.' );
			return;
		}

		if ( ! ajax_url || ! nonce ) {
			console_warn( 'sfeEditEventData is not properly set up. This script may not function correctly.' );
			return;
		}

		// Check if editing a single event
		if ( pagenow !== 'tribe_events' ) {
			console_warn( 'This script is only intended for the Events edit page. Current page:', pagenow );
			return;
		}

		// Add a link to edit the product
		insert_product_link();

		// Fetch and update the registration details field that appears in the Event Registration meta box (ACF)
		update_registration_details();

		// Replace the Event Cost field with a message explaining that it will be based on the product price instead
		replace_event_cost_with_product_price();

	};

	const insert_product_link = function() {
		if ( ! product_id ) return;

		let product_field = document.querySelector('.acf-field-684a24424c8f9');
		if ( ! product_field ) return;

		let product_input = product_field.querySelector('.acf-input');

		let para = document.createElement('p');
		para.className = 'description';
		para.innerHTML = 'Edit product: <a href="'+ edit_product_url +'" target="_blank">' + product_title + '</a>';

		// Append paragraph to product_input
		product_input.appendChild(para);

		// If product changes, remove the link
		let product_select = document.querySelector('#acf-field_684a24424c8f9'); // select

		const remove_para_after_change = function() {
			if ( parseInt(product_select.value) !== parseInt(product_id) ) {
				para.remove();
				jQuery(product_select).off('change', remove_para_after_change);
				console_log('Removing product link because the product changed');
			}
		};

		jQuery(product_select).on('change', remove_para_after_change);
	};

	/**
	 * Fetch and update the registration details field that appears in the Event Registration meta box (ACF)
	 */
	const update_registration_details = function() {
		let $details = document.querySelector('.acf-field.acf-field-684a24d697f08 .acf-input');

		// Do an ajax request to get the registration details
		if ( $details ) {
			fetch(ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded'
				},
				body: new URLSearchParams({
					action: 'sfe_get_registration_details_message_html',
					nonce: nonce,
					event_post_id: event_post_id
				}).toString()
			})
				.then(response => response.json())
				.then(data => {
					if ( data.success ) {
						$details.innerHTML = data.data;
						console_log('Event details fetched', data);
					} else {
						console_error('Failed to fetch registration details:', data);
					}
				})
				.catch(error => function () {
					console_error('Error fetching registration details:', error);
				});
		} else {
			console_warn('Registration Details field not found on the page.');
		}
	};

	/**
	 * Replace the Event Cost field with a message explaining that it will be based on the product price instead
	 */
	const replace_event_cost_with_product_price = function() {
		let product_select = document.querySelector('#acf-field_684a24424c8f9'); // select
		let event_cost = document.querySelector('#EventCost'); // input[type="text"]

		// Create paragraph below Event Cost field
		let para = document.createElement('p');
		para.className = 'description';
		para.innerHTML = 'This event\'s price is based on the product in the Event Registration section.';
		para.style.display = 'none';

		// Insert after the Event Cost input
		event_cost.parentNode.insertBefore(para, event_cost.nextSibling);

		const refresh_elements = function() {
			console_log('Refreshing event cost elements based on product selection:', product_select.value);

			event_cost.style.display = product_select.value ? 'none' : 'inline';
			para.style.display = product_select.value ? 'block' : 'none';
		};

		// When the product select changes, update the event cost field
		jQuery(product_select).on('change', refresh_elements);

		refresh_elements();

	};

	document.addEventListener('DOMContentLoaded', function() {
		// Init after ~1 frame after the DOM is ready
		setTimeout( init, 75 );
	});

})();