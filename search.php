<?php
/**
 * 搜索结果模板
 *
 * @package Echo
 */

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<header class="echo-page-header">
			<h1 class="echo-page-header__title">
				<?php
				/* translators: %s: 搜索关键词 */
				printf( esc_html__( '搜索：%s', 'echo' ), '<span>' . get_search_query() . '</span>' );
				?>
			</h1>
		</header>

		<div class="echo-layout">
			<div class="echo-content">
				<?php if ( have_posts() ) : ?>
					<p class="echo-page-header__desc">
						<?php
						/* translators: %s: 结果数量 */
						printf( esc_html__( '找到 %s 条相关结果。', 'echo' ), esc_html( $wp_query->found_posts ) );
						?>
					</p>

					<div class="<?php echo esc_attr( echo_post_list_class() ); ?>">
						<?php
						while ( have_posts() ) :
							the_post();
							get_template_part( 'template-parts/content', 'search' );
						endwhile;
						?>
					</div>

					<?php echo_pagination(); ?>
				<?php else : ?>
					<?php get_template_part( 'template-parts/content', 'none' ); ?>
				<?php endif; ?>
			</div>

			<?php get_sidebar(); ?>
		</div>
	</div>
</main>

<?php
get_footer();
