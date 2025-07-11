<?php

/**
 * @global WP_Term $term
 */

$featured_image_id = get_field( 'default_featured_image_id', 'class_type_' . $term->term_id );
if ( ! $featured_image_id ) $featured_image_id = get_field( 'default_featured_image_id', 'sfe_settings' );

if ( $featured_image_id ) {
	echo '<div class="sfe-term-featured-image">';
	echo wp_get_attachment_image( $featured_image_id, 'large', false, array( 'class' => 'sfe-term-featured-image__img' ) );
	echo '</div>';
}