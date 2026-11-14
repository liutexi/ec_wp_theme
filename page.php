<?php
/**
 * 独立页面模板
 *
 * @package Echo
 */

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<div class="echo-layout">
			<div class="echo-content">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'echo-single' ); ?>>
						<header class="echo-single__header">
							<h1 class="echo-single__title"><?php the_title(); ?></h1>
						</header>

						<div class="echo-entry-content">
							<?php
							the_content();
							wp_link_pages(
								array(
									'before' => '<div class="echo-pagination">' . esc_html__( '分页：', 'echo' ),
									'after'  => '</div>',
								)
							);
							?>
						</div>
					</article>

					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
					?>
				<?php endwhile; ?>
			</div>

			<?php get_sidebar(); ?>
		</div>
	</div>
</main>

<?php
get_footer();
