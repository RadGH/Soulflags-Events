<?php

/**
 * @global WP_Term $term
 * @global WP_Post[] $posts
 */

echo '<div class="sfe-classes-list '. (empty( $posts ) ? 'no-posts' : 'has-posts') .'">';

foreach( $posts as $post ) {
	$start_date = get_post_meta( $post->ID, '_EventStartDate', true );
	$end_date = get_post_meta( $post->ID, '_EventEndDate', true );
	
	// Display as: Jul 19
	$start_ts = strtotime( $start_date );
	$start_date_monthday = $start_date ? date( 'M j', $start_ts ) : '';
	$start_date_year = $start_date ? date( 'Y', $start_ts ) : '';
	
	$end_ts = strtotime( $end_date );
	$end_date_monthday = $end_date ? date( 'M j', $end_ts ) : '';
	$end_date_year = $end_date ? date( 'Y', $end_ts ) : '';
	
	$display_both_days = ($start_date_monthday . $start_date_year) !== ($end_date_monthday . $end_date_year);
	
	// Check if expired, meaning the start date is in the past
	$max_date = $end_ts ?: $start_ts;
	$is_expired = $max_date && $max_date < time();
	
	// Get the event title
	$event_title = get_the_title( $post->ID );
	
	// Get the event cost and stock, separated by en dash
	$cost = array(
		tribe_get_cost( $post->ID, true ),
		SFE_Events::get_event_stock_html( $post->ID )
	);
	$cost = array_map( 'wp_strip_all_tags', $cost );
	$cost = array_filter( $cost ); // Remove empty values
	$cost = implode(' &ndash; ', $cost);
	
	$classes = 'class-event';
	$classes.= ' post-id-' . esc_attr( $post->ID );
	$classes.= $is_expired ? ' expired' : '';
	
	echo '<li class="' . esc_attr( $classes ) . '">';
	
	echo '<div class="event-row '. ($display_both_days ? 'two-dates' : 'one-date') .'">';
	
	if ( $is_expired ) {
		echo '<span class="expired-label">' . esc_html__( 'Expired', 'soulflags-events' ) . '</span>';
		echo '<div class="sep expired-sep">&ndash;</div>';
	}
	
	echo '<div class="event-date start-date">';
	echo '<div class="date">' . $start_date_monthday . '</div>';
	if ( date( 'Y' ) !== $start_date_year ) {
		echo '<div class="year">' . $start_date_year . '</div>';
	}
	echo '</div>';
	
	if ( $display_both_days ) {
		echo '<div class="sep date-sep">&ndash;</div>';
		
		echo '<div class="event-date end">';
		echo '<div class="date">' . $end_date_monthday . '</div>';
		if ( date( 'Y' ) !== $end_date_year ) {
			echo '<div class="year">' . $end_date_year . '</div>';
		}
		echo '</div>';
	}
	
	echo '<div class="sep title-sep">&ndash;</div>';
	
	echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html( $event_title ) . '</a>';
	
	if ( $cost ) {
		echo '<div class="event-cost">(' . esc_html( $cost ) . ')</div>';
	}
	
	echo '</div>';
	
	echo '</li>';
}

echo '</div>';