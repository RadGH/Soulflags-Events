<?php

class SFE_Class_Type {
	
	public function __construct() {
		
		// Add a custom Class Type category associated with The Event Calendar events
		add_action( 'init', array( $this, 'register_class_category_taxonomy' ) );
		
		// List classes by category
		add_shortcode( 'souflags_list_classes', array( $this, 'list_classes_shortcode' ) );
		
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
						'value'   => current_time( 'mysql' ),
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
		echo '<div class="sfe-classes-list '. (empty( $terms ) ? 'no-terms' : 'has-terms') .'">';
		
		if ( empty( $terms ) ) {
			
			echo '<p>' . esc_html__( 'No classes available at this time.', 'soulflags-events' ) . '</p>';
			
		}else{
			
			foreach( $terms as $term ) {
				$term_url = get_term_link( $term );
				
				echo '<div class="class-category term-id-' . esc_attr( $term->term_id ) . '">';
				
				// Display term
				echo '<h2><a href="'. esc_url( $term_url ) .'">' . esc_html( $term->name ) . '</a></h2>';
				
				// Display term description
				if ( !empty( $term->description ) ) {
					echo '<div class="class-category-description">' . wp_kses_post( $term->description ) . '</div>';
				}
				
				echo '<ul class="class-post-list">';
				
				// Display events within the term
				foreach( $term->posts as $post ) {
					$start_date = get_post_meta( $post->ID, '_EventStartDate', true );
					
					// Display as: Jul 19
					$start_date_monthday = $start_date ? date( 'M j', strtotime( $start_date ) ) : '';
					$start_date_year = $start_date ? date( 'Y', strtotime( $start_date ) ) : '';
					
					echo '<li class="class-event post-id-' . esc_attr( $post->ID ) . '">';
					echo '<div class="event-row">';
					echo '<div class="event-date">';
					echo '<div class="date">' . $start_date_monthday . '</div>';
					if ( date( 'Y' ) !== $start_date_year ) {
						echo '<div class="year">' . $start_date_year . '</div>';
					}
					echo '</div>';
					echo '<div class="sep">&ndash;</div>';
					echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html( get_the_title( $post->ID ) ) . '</a>';
					echo '</div>';
					echo '</li>';
				}
				
				echo '</ul>';
				
				echo '</div>';
			}
		}
		
		echo '</div>';
		
		return ob_get_clean();
	}
	
}

SFE_Class_Type::get_instance();