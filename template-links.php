<?php
/**
 * Template Name: 友情链接
 *
 * 显示页面正文 + 全部友情链接（分组展示）。
 *
 * @package Echo
 */

get_header();

$echo_links_sidebar = (bool) echo_links_config( 'links_show_sidebar', false );
?>
<main id="primary" class="echo-main">
	<div class="echo-container">
		<div class="echo-layout<?php echo $echo_links_sidebar ? '' : ' echo-layout--full'; ?>">
			<div class="echo-content">
				<article class="echo-single echo-single--links">
					<?php
					while ( have_posts() ) :
						the_post();
						?>
						<header class="echo-single__header">
							<h1 class="echo-single__title"><?php the_title(); ?></h1>
						</header>

						<div class="echo-entry-content">
							<?php
							the_content();
							echo echo_links_shortcode( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					<?php endwhile; ?>
				</article>
			</div>

			<?php
			if ( $echo_links_sidebar ) {
				get_sidebar();
			}
			?>
		</div>
	</div>
</main>
<?php
get_footer();
