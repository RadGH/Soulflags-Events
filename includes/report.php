<?php

class SFE_Report {
	
	public function __construct() {
		
		// Add a settings page for the report called "SoulFlags Events Report"
		add_action( 'admin_menu', array( $this, 'add_report_page' ), 8 );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	// Actions
	
	/**
	 * Adds a report page to the WordPress admin menu.
	 * @return void
	 */
	public function add_report_page() {
		add_submenu_page(
			'edit.php?post_type=tribe_events',
			'SoulFlags Events &ndash; Class Report',
			'Class Report',
			'manage_options',
			'soulflags-events-report',
			array( $this, 'render_report_page' ),
			100
		);
	}
	
	/**
	 * Renders the report page content.
	 * @return void
	 */
	public function render_report_page() {
		// Check if the user has permission to view this page
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.' ) );
		}
		
		// Start date: Most recent sunday
		$filters = $this->get_filters();
		
		$start_date = $filters['start_date'] ?? null; // Y-m-d
		$end_date = $filters['end_date'] ?? null; // Y-m-d
		
		$events = $this->get_events( $start_date, $end_date );
		
		if ( ! $events ) {
			echo '<p>No events found for the selected date range.</p>';
			return;
		}
		
		echo '<div class="wrap">';
		
		echo '<h1>SoulFlags Events Report</h1>';
		
		$this->display_filters( $filters );
		
		echo '<table class="widefat fixed striped sfe-class-report">';
		echo '<thead><tr>';
		echo '<th class="col col-title">Event Title</th>';
		echo '<th class="col col-start-date">Start Date</th>';
		echo '<th class="col col-end-date">End Date</th>';
		echo '<th class="col col-tickets">Tickets</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		foreach ( $events as $event ) {
			$event_post_id = $event->ID;
			
			$start_date = get_post_meta( $event->ID, '_EventStartDate', true );
			$end_date = get_post_meta( $event->ID, '_EventEndDate', true );
			
			// $orders = SFE_Registration::get_event_order_ids( $event_post_id );
			$registration_html = SFE_Events::get_registration_details_message_html( $event_post_id );
			
			echo '<tr>';
			echo '<td class="col col-title"><a href="' . get_edit_post_link( $event->ID ) . '">' . esc_html( $event->post_title ) . '</a></td>';
			echo '<td class="col col-start-date">' . esc_html( date('m/d/Y', strtotime($start_date)) ) . '</td>';
			echo '<td class="col col-end-date">' . esc_html( date('m/d/Y', strtotime($end_date)) ) . '</td>';
			echo '<td class="col col-tickets">' . $registration_html . '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}
	
	/**
	 * Displays filters for the report.
	 * @param array $filters Filters to display.
	 */
	public function display_filters( $filters ) {
		$start_date = isset($filters['start_date']) ? date('Y-m-d', strtotime($filters['start_date'])) : '';
		$end_date = isset($filters['end_date']) ? date('Y-m-d', strtotime($filters['end_date'])) : '';
		
		echo '<div class="sfe-report-filters">';
		echo '<form method="get" action="">';
		echo '<input type="hidden" name="post_type" value="tribe_events">';
		echo '<input type="hidden" name="page" value="soulflags-events-report">';
		echo '<p>';
		echo '<label for="start_date">Displaying events from start date:</label>';
		echo '<input type="date" name="sfe_report[start_date]" value="' . esc_attr($start_date) . '">';
		echo '<label for="end_date"> to end date:</label>';
		echo '<input type="date" name="sfe_report[end_date]" value="' . esc_attr($end_date) . '">';
		echo ' <button type="submit" class="button button-secondary">Update</button>';
		echo '</p>';
		echo '</form>';
		echo '</div>';
	}
	
	/**
	 * Retrieves filters from the user input.
	 * @return array Filters with default values.
	 */
	public function get_filters() {
		// Get filters from the user
		$filters = isset($_GET['sfe_report']) ? stripslashes_deep($_GET['sfe_report']) : array();
		
		// Default values
		$defaults = array(
			'start_date' => date('Y-m-d H:i:s', strtotime('last Sunday') ),
			'end_date' => date('Y-m-d H:i:s', strtotime('next Saturday') ),
		);
		
		return wp_parse_args( $filters, $defaults );
	}
	
	/**
	 * Retrieves events within a specified date range.
	 * @param int $start_date Unix timestamp for the start date.
	 * @param int $end_date Unix timestamp for the end date.
	 * @return array List of events.
	 */
	public function get_events( $start_date, $end_date ) {
		$args = array(
			'post_type' => 'tribe_events',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => 'sfe_registration_enabled',
					'value' => '1',
					'compare' => '=',
				),
				array(
					'key' => '_EventStartDate',
					'value' => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type' => 'DATETIME'
				),
			)
		);
		
		$query = new WP_Query( $args );
		
		return $query->posts;
	}
	
	
}

SFE_Report::get_instance();