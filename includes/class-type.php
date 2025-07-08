<?php

class SFE_Class_Type {
	
	public function __construct() {
		
		// Add a custom Class Type category associated with The Event Calendar events
		add_action( 'init', array( $this, 'register_class_category_taxonomy' ) );
		
		// List classes by category
		add_shortcode( 'souflags_list_classes', array( $this, 'list_classes_shortcode' ) );
		
		// Locate a different template when displaying Class Type taxonomy term page
		add_filter( 'taxonomy_template', array( $this, 'replace_template' ) );
		
		// Add a body class to make the Class Type taxonomy full width
		add_filter( 'body_class', array( $this, 'add_class_type_body_class' ) );
		
		// When displaying a single term page, order the posts by the event start date
		add_action( 'pre_get_posts', array( $this, 'order_posts_by_event_date' ) );
		
		// Use the content from the associated Class Type if the content is empty for an event
		add_filter( 'the_content', array( $this, 'use_class_type_content' ), 10, 2 );
		
		// Use the featured image from the Class Type or settings page if the event has none
		add_filter( 'post_thumbnail_id', array( $this, 'use_class_type_image' ), 10, 2 );
		
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
	 * Register a custom taxonomy for class categories
	 */
	public function register_class_category_taxonomy() {
		register_taxonomy( 'class_type', 'tribe_events', array(
			'label'        => __( 'Class Types', 'soulflags-events' ),
			'rewrite'      => array( 'slug' => 'class-type' ),
			'hierarchical' => true,
			'show_admin_column' => true,
		) );
	}
	
	/**
	 * List classes by category
	 *
	 * @param array $atts
	 * @param string $content
	 * @param string $shortcode_name
	 *
	 * @return string|false
	 */
	public function list_classes_shortcode( $atts, $content = '', $shortcode_name = 'souflags_list_classes' ) {
		// Extract shortcode attributes
		$atts = shortcode_atts( array(
		), $atts, $shortcode_name );
		
		ob_start();
		
		// Get all class categories
		$terms = get_terms( array(
			'taxonomy'   => 'class_type',
			'hide_empty' => true,
		) );
		
		$date_cutoff = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ); // current_time( 'mysql' )
		
		// Get events for each term sorted by the event start date
		foreach( $terms as $i => $term ) {
			$args = array(
				'post_type'      => 'tribe_events',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'class_type',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
				
				'meta_query'     => array(
					array(
						'key'     => '_EventStartDate',
						'value'   => $date_cutoff,
						'compare' => '>=',
					),
				),
				
				'orderby' => 'meta_value',
				'order'   => 'ASC',
			);
			
			$query = new WP_Query( $args );
			
			// Store the posts and the earliest start date for each term
			if ( $query->have_posts() ) {
				$date = get_post_meta( $query->posts[0]->ID, '_EventStartDate', true );
				$term->start_date = $date ? strtotime( $date ) : null;
				$term->posts = $query->posts;
			} else {
				unset( $terms[$i] ); // Remove empty terms
			}
		}
		
		// Order terms by the soonest event date
		usort( $terms, function( $a, $b ) {
			if ( $a->start_date === null && $b->start_date === null ) return 0;
			if ( $a->start_date === null ) return 1;
			if ( $b->start_date === null ) return -1;
			return $a->start_date <=> $b->start_date;
		} );
		
		// Display results
		echo '<div class="sfe-available-classes '. (empty( $terms ) ? 'no-terms' : 'has-terms') .'">';
		
		if ( empty( $terms ) ) {
			
			echo '<p>' . esc_html__( 'No classes available at this time.', 'soulflags-events' ) . '</p>';
			
		}else{
			
			foreach( $terms as $term ) {
				
				echo '<div class="class-category term-id-' . esc_attr( $term->term_id ) . '">';
				
				// Display event title and description
				include( SFE_PATH . '/templates/parts/term-summary.php' );
				
				// Display events list assigned to this term
				$posts = $term->posts;
				include( SFE_PATH . '/templates/parts/event-list.php' );
				
				echo '</ul>';
				
				echo '</div>';
			}
		}
		
		echo '</div>';
		
		return ob_get_clean();
	}
	
	/**
	 * Replace the taxonomy template for class_type
	 *
	 * @param string $template
	 * @return string
	 */
	public function replace_template( $template ) {
		if ( is_tax( 'class_type' ) ) {
			return SFE_PATH . '/templates/term-class-type.php';
		}
		
		return $template;
	}
	
	/**
	 * Add a body class to make the Class Type taxonomy full width
	 * @param array $classes
	 * @return array
	 */
	public function add_class_type_body_class( $classes ) {
		if ( is_tax( 'class_type' ) ) {
			$classes[] = 'et_full_width_page';
		}
		
		return $classes;
	}
	
	/**
	 * Order posts by the event start date when displaying a single term page
	 *
	 * @param WP_Query $query
	 */
	public function order_posts_by_event_date( $query ) {
		if ( is_admin() ) return;
		if ( ! $query->is_main_query() ) return;
		if ( ! $query->is_tax( 'class_type' ) ) return;
		
		// Exclude posts if the event start date is too long ago
		$date_cutoff = date( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
		$query->set( 'meta_query', array(
			array(
				'key'     => '_EventStartDate',
				'value'   => $date_cutoff,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		) );
		
		// Modify the query to order by the event start date
		$query->set( 'meta_key', '_EventStartDate' );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'ASC' );
		
		// Ensure we only get published events
		$query->set( 'post_status', 'publish' );
	}
	
	/**
	 * Use the content from the associated Class Type if the content is empty for an event
	 *
	 * @param string $content
	 * @param WP_Post|null $post
	 * @return string
	 */
	public function use_class_type_content( $content, $post = null ) {
		if ( $post === null ) $post = get_post();
		
		if ( ! is_singular( 'tribe_events' ) ) return $content;
		
		// Only apply to events with registrations enabled
		if ( ! SFE_Registration::is_registration_enabled( $post->ID ) ) return $content;
		
		// If the event already has content, use that
		if ( ! empty( $content ) ) return $content;
		
		// If the event does not have content but it has a class type, use the class type description
		$class_types = get_the_terms( $post->ID, 'class_type' );
		if ( ! $class_types || is_wp_error( $class_types ) ) return $content;
		
		// Use the content from the first term
		$term = reset( $class_types );
		
		if ( ! empty( $term->description ) ) {
			// Temporarily remove the filter to avoid infinite loop
			remove_filter( 'the_content', array( $this, 'use_class_type_content' ), 10, 2 );
			
			$content = apply_filters( 'the_content', $term->description );
			
			add_filter( 'the_content', array( $this, 'use_class_type_content' ), 10, 2 );
		}
		
		return $content;
	}
	
	/**
	 * Use the featured image from the Class Type or settings page if the event has none
	 *
	 * @param int|false        $thumbnail_id Post thumbnail ID or false if the post does not exist.
	 * @param int|WP_Post|null $post         Post ID or WP_Post object. Default is global `$post`.
	 *
	 * @return int|false
	 */
	public function use_class_type_image( $thumbnail_id, $post = null ) {
		if ( $post === null ) return $thumbnail_id;
		
		// Only apply to events with registrations enabled
		if ( ! SFE_Registration::is_registration_enabled( $post->ID ) ) return $thumbnail_id;
		
		// Get the featured image from an assigned Class Type term
		$class_types = get_the_terms( $post->ID, 'class_type' );
		
		if ( $class_types && ! is_wp_error( $class_types ) ) foreach( $class_types as $class_type ) {
			// Get the default featured image ID from the class type term
			$default_featured_image_id = get_field( 'default_featured_image_id', 'class_type_' . $class_type->term_id );
			
			if ( $default_featured_image_id ) {
				// Set the featured image for the event
				return $default_featured_image_id;
			}
		}
		
		// If no class type has a featured image, use the default from the Soulflags Events settings page
		$default_featured_image_id = get_field( 'default_featured_image_id', 'sfe_settings' );
		
		if ( $default_featured_image_id ) {
			return $default_featured_image_id;
		}
	}
	
}

SFE_Class_Type::get_instance();