<?php

// Add body class to make page full width: et_full_width_page
add_filter( 'body_class', function( $classes ) {
	$classes[] = 'et_full_width_page';
	return $classes;
} );

global $wp_query;

// Variables used in templates
$term = get_queried_object();
$posts = $wp_query->posts;

get_header();
?>
	
	<div id="main-content">
		<div class="container">
			<div id="content-area" class="clearfix">
				<div id="left-area">
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'term-class_type' ); ?>>
						
						<?php
						include( SFE_PATH . '/templates/parts/term-image.php' );
						?>
						
						<?php
						include( SFE_PATH . '/templates/parts/term-summary.php' );
						?>
						
						<div class="entry-content">
							<?php
							include( SFE_PATH . '/templates/parts/event-list.php' );
							?>
						</div>
						
						<div class="sfe-back-to-events">
							<?php
							// Link back to the main events page
							$back_label = __( '&larr; Back to Events', 'soulflags-events' );
							$back_url = get_post_type_archive_link( 'tribe_events' );
							
							echo '<a href="' . esc_url( $back_url ) . '" class="button sfe-back-to-events-link">' . $back_label . '</a>';
							?>
						</div>
						
					</article>
				</div>
			</div>
		</div>
	</div>

<?php
get_footer();