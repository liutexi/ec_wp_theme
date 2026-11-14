<?php
/**
 * 归档/分类/标签/作者/日期 模板
 *
 * @package Echo
 */

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<header class="echo-page-header">
			<?php
			the_archive_title( '<h1 class="echo-page-header__title">', '</h1>' );
			the_archive_description( '<p class="echo-page-header__desc">', '</p>' );
			?>
		</header>

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
