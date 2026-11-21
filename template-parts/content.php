<?php
/**
 * 文章卡片模板
 *
 * @package Echo
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'echo-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="echo-thumbnail" href="<?php the_permalink(); ?>">
			<?php the_post_thumbnail( 'post-thumbnail' ); ?>
		</a>
	<?php endif; ?>

	<div class="echo-card__meta">
		<?php echo echo_get_categories(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<span class="echo-card__author">
			<?php echo get_avatar( get_the_author_meta( 'ID' ), 22 ); ?>
			<a href="<?php echo esc_url( echo_user_author_link( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a>
		</span>
		<span class="echo-card__date"><?php echo esc_html( get_the_date() ); ?></span>
		<span class="echo-card__comments">
			<?php comments_number( __( '0 条评论', 'echo' ), __( '1 条评论', 'echo' ), __( '% 条评论', 'echo' ) ); ?>
		</span>
		<?php
		if ( function_exists( 'echo_eng_card_meta' ) ) {
			echo_eng_card_meta();
		}
		?>
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
