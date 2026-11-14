<?php
/**
 * 主模板：博客首页 / 文章列表
 *
 * @package Echo
 */

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<?php if ( is_home() && ! is_front_page() ) : ?>
			<div class="echo-page-header">
				<h1 class="echo-page-header__title"><?php single_post_title(); ?></h1>
			</div>
		<?php endif; ?>

		<div class="echo-layout">
			<div class="echo-content">
				<?php if ( have_posts() ) : ?>
					<div class="echo-post-list">
						<?php
						while ( have_posts() ) :
							the_post();
							get_template_part( 'template-parts/content', get_post_type() );
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
