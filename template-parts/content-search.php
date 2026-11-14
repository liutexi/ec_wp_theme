<?php
/**
 * 搜索结果条目模板
 *
 * @package Echo
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'echo-card' ); ?>>
	<div class="echo-card__meta">
		<?php echo echo_get_categories(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<span class="echo-card__date"><?php echo esc_html( get_the_date() ); ?></span>
	</div>

	<h2 class="echo-card__title">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>

	<div class="echo-card__excerpt">
		<?php the_excerpt(); ?>
	</div>

	<a class="echo-card__readmore" href="<?php the_permalink(); ?>">
		<?php esc_html_e( '阅读全文 →', 'echo' ); ?>
	</a>
</article>
