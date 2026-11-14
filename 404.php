<?php
/**
 * 404 页面模板
 *
 * @package Echo
 */

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<div class="echo-404">
			<div class="echo-404__code">404</div>
			<p class="echo-404__text"><?php esc_html_e( '抱歉，你访问的页面不存在或已被移除。', 'echo' ); ?></p>
			<div class="echo-404__actions">
				<a class="echo-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'echo' ); ?></a>
				<a class="echo-btn echo-btn--outline" href="javascript:history.back()"><?php esc_html_e( '返回上一页', 'echo' ); ?></a>
			</div>
		</div>
	</div>
</main>

<?php
get_footer();
