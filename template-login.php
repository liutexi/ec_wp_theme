<?php
/**
 * Template Name: 登录
 * Template Post Type: page
 *
 * 新建页面、别名设为设置中的登录别名（默认 login），
 * 在「页面属性 → 模板」中选择「登录」即可。
 *
 * @package Echo
 */

// 表单提交需在输出前处理（成功会 302 跳转）。
echo_auth_handle_request( 'login' );

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<div class="echo-layout<?php echo echo_auth_config( 'auth_show_sidebar', false ) ? '' : ' echo-layout--full'; ?>">
			<div class="echo-content">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'echo-single echo-single--auth' ); ?>>
						<?php echo_auth_render( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
					<?php
				endwhile;
				?>
			</div>

			<?php
			if ( echo_auth_config( 'auth_show_sidebar', false ) ) {
				get_sidebar();
			}
			?>
		</div>
	</div>
</main>

<?php
get_footer();
