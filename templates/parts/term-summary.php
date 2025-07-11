<?php

/**
 * @global WP_Term $term
 */

$term_url = get_term_link( $term );
$is_singular_term = is_tax( 'class_type' );

echo '<div class="sfe-term-summary">';

// Display term title
if ( $is_singular_term ) {
	echo '<h1 class="entry-title main_title">';
} else {
	echo '<h2 class="entry-title">';
	// echo '<a href="'. esc_url( $term_url ) .'">';
}

echo esc_html( $term->name );

if ( $is_singular_term ) {
	echo '</h1>';
} else {
	// echo '</a>';
	echo '</h2>';
}

// Display term description
if ( !empty( $term->description ) ) {
	echo '<div class="class-category-description">' . wp_kses_post( $term->description ) . '</div>';
}

echo '</div>';