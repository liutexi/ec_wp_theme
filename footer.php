<?php
/**
 * 页脚模板
 *
 * @package Echo
 */
?>

<footer class="echo-footer">
	<div class="echo-container echo-footer__inner">
		<div class="echo-footer__copyright">
			<?php
			$echo_footer_text = echo_get_setting( 'footer_text', '' );
			if ( '' !== trim( (string) $echo_footer_text ) ) {
				echo wp_kses_post( $echo_footer_text );
			} else {
				?>
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
				<?php if ( ! echo_get_setting( 'hide_tagline', false ) ) : ?>
					<span> · <?php bloginfo( 'description' ); ?></span>
				<?php endif; ?>
				<?php
			}
			?>
		</div>

		<nav class="echo-footer__nav" aria-label="<?php esc_attr_e( '页脚菜单', 'echo' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>

		<?php if ( echo_get_setting( 'footer_credit', true ) ) : ?>
			<div class="echo-footer__credit">
				<?php
				printf(
					/* translators: %s: 主题名 */
					esc_html__( '使用 %s 主题', 'echo' ),
					'<a href="https://github.com/liutexi">Echo</a>'
				);
				?>
			</div>
		<?php endif; ?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
