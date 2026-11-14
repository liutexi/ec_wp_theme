<?php
/**
 * 单篇文章模板
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
							<?php echo echo_get_categories(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

							<h1 class="echo-single__title"><?php the_title(); ?></h1>

							<div class="echo-single__meta">
								<span class="echo-single__author">
									<?php echo get_avatar( get_the_author_meta( 'ID' ), 28 ); ?>
									<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
										<?php the_author(); ?>
									</a>
								</span>
								<span class="echo-single__date">
									<?php echo esc_html( get_the_date() ); ?>
								</span>
								<?php if ( get_comments_number() ) : ?>
									<span class="echo-single__comments">
										<?php comments_number( __( '0 条评论', 'echo' ), __( '1 条评论', 'echo' ), __( '% 条评论', 'echo' ) ); ?>
									</span>
								<?php endif; ?>
							</div>
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

						<?php if ( has_tag() ) : ?>
							<div class="echo-tags">
								<span><?php esc_html_e( '标签：', 'echo' ); ?></span>
								<?php the_tags( '', '', '' ); ?>
							</div>
						<?php endif; ?>
					</article>

					<nav class="echo-post-nav" aria-label="<?php esc_attr_e( '文章导航', 'echo' ); ?>">
						<?php
						$prev = get_previous_post_link( '%link', '<span class="echo-post-nav__label">' . esc_html__( '上一篇', 'echo' ) . '</span>%title' );
						$next = get_next_post_link( '%link', '<span class="echo-post-nav__label">' . esc_html__( '下一篇', 'echo' ) . '</span>%title' );

						echo $prev ? wp_kses_post( $prev ) : '<span></span>';
						echo $next ? wp_kses_post( $next ) : '<span></span>';
						?>
					</nav>

					<?php
					// 评论
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
