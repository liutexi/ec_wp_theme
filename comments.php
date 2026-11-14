<?php
/**
 * 评论模板
 *
 * @package Echo
 */

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="echo-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="echo-comments__title">
			<?php
			$echo_comment_count = get_comments_number();
			printf(
				/* translators: %s: 评论数量 */
				esc_html( _n( '%s 条评论', '%s 条评论', $echo_comment_count, 'echo' ) ),
				esc_html( number_format_i18n( $echo_comment_count ) )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 44,
				)
			);
			?>
		</ol>

		<?php
		the_comments_navigation();

		// 评论已关闭的提示
		if ( ! comments_open() ) {
			?>
			<p class="echo-comments__closed"><?php esc_html_e( '评论已关闭。', 'echo' ); ?></p>
			<?php
		}
		?>
	<?php endif; ?>

	<?php comment_form(); ?>
</div>
