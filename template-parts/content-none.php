<?php
/**
 * 无内容时的占位模板
 *
 * @package Echo
 */
?>

<section class="echo-card echo-none">
	<h2 class="echo-card__title"><?php esc_html_e( '没有找到内容', 'echo' ); ?></h2>
	<p class="echo-card__excerpt">
		<?php
		if ( is_search() ) {
			esc_html_e( '抱歉，没有匹配你搜索关键词的内容。请尝试其他关键词。', 'echo' );
		} else {
			esc_html_e( '这里暂时还没有内容，请稍后再来看看。', 'echo' );
		}
		?>
	</p>

	<?php if ( ! is_search() && current_user_can( 'publish_posts' ) ) : ?>
		<p>
			<a class="echo-btn" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">
				<?php esc_html_e( '发布第一篇文章', 'echo' ); ?>
			</a>
		</p>
	<?php endif; ?>
</section>
