<?php
/**
 * Template Name: 个人中心
 * Template Post Type: page
 *
 * 在后台新建一个页面，把别名设为设置里配置的别名（默认 my），
 * 并在「页面属性 → 模板」中选择本模板即可启用「我的」界面。
 *
 * @package Echo
 */

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<div class="echo-layout<?php echo echo_my_config( 'my_show_sidebar', false ) ? '' : ' echo-layout--full'; ?>">
			<div class="echo-content">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'echo-single echo-single--my' ); ?>>
						<header class="echo-single__header">
							<h1 class="echo-single__title"><?php the_title(); ?></h1>
						</header>

						<?php echo_my_render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
				endwhile;
				?>
			</div>

			<?php
			if ( echo_my_config( 'my_show_sidebar', false ) ) {
				get_sidebar();
			}
			?>
		</div>
	</div>
</main>

<?php
get_footer();
