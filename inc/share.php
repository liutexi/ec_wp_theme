<?php
/**
 * 分享按钮组：QQ / QQ空间 / 微博 / 复制链接
 *
 * 文章页展示，轻量实现（不依赖第三方 SDK，不含海报 canvas）。后台可开关与各平台显隐。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 默认设置。
 *
 * @return array
 */
function echo_sh_defaults() {
	return array(
		'sh_enable'   => true,
		'sh_qq'       => true,
		'sh_qzone'    => true,
		'sh_weibo'    => true,
		'sh_copy'     => true,
		'sh_position' => 'after', // after: 正文后；before: 正文前
	);
}

/**
 * 读取设置。
 *
 * @param string $key     键。
 * @param mixed  $default 默认。
 * @return mixed
 */
function echo_sh_config( $key, $default = null ) {
	$all = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_sh_defaults();
	return isset( $all[ $key ] ) ? $all[ $key ] : $default;
}

/**
 * 是否启用。
 *
 * @return bool
 */
function echo_sh_is_on() {
	return (bool) echo_sh_config( 'sh_enable', true );
}

/**
 * 清洗设置。
 *
 * @param array $input 原始。
 * @param array $out   输出。
 * @return void
 */
function echo_sanitize_sh_settings( $input, &$out ) {
	foreach ( array( 'sh_enable', 'sh_qq', 'sh_qzone', 'sh_weibo', 'sh_copy' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) && '1' === (string) $input[ $key ];
	}
	$pos           = isset( $input['sh_position'] ) ? sanitize_key( $input['sh_position'] ) : 'after';
	$out['sh_position'] = ( 'before' === $pos ) ? 'before' : 'after';
}

/**
 * 渲染分享按钮组。
 *
 * @param int $post_id 文章 ID，默认当前文章。
 * @return void
 */
function echo_sh_render( $post_id = 0 ) {
	if ( ! echo_sh_is_on() ) {
		return;
	}
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	$url   = rawurlencode( get_permalink( $post_id ) );
	$title = rawurlencode( get_the_title( $post_id ) );
	$desc  = rawurlencode( wp_strip_all_tags( get_the_excerpt( $post_id ) ) );
	$thumb = '';
	if ( has_post_thumbnail( $post_id ) ) {
		$thumb = rawurlencode( get_the_post_thumbnail_url( $post_id, 'medium' ) );
	}
	?>
	<div class="echo-share" role="group" aria-label="<?php esc_attr_e( '分享文章', 'echo' ); ?>">
		<span class="echo-share__label"><?php esc_html_e( '分享', 'echo' ); ?></span>
		<div class="echo-share__btns">
			<?php if ( echo_sh_config( 'sh_qq', true ) ) : ?>
				<a class="echo-share__btn echo-share__btn--qq" href="https://connect.qq.com/widget/shareqq/index.html?url=<?php echo esc_attr( $url ); ?>&title=<?php echo esc_attr( $title ); ?>&desc=<?php echo esc_attr( $desc ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( '分享到 QQ', 'echo' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 3C6.9 3 2.75 6.2 2.75 10.2c0 2.28 1.32 4.3 3.4 5.53l-.9 2.68c-.1.3.24.55.5.37l3.2-2.2c1.06.3 2.2.47 3.4.47 5.14 0 9.3-3.2 9.3-7.2S17.18 3 12.04 3z"/></svg>
					<span>QQ</span>
				</a>
			<?php endif; ?>
			<?php if ( echo_sh_config( 'sh_qzone', true ) ) : ?>
				<a class="echo-share__btn echo-share__btn--qzone" href="https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=<?php echo esc_attr( $url ); ?>&title=<?php echo esc_attr( $title ); ?>&summary=<?php echo esc_attr( $desc ); ?><?php echo $thumb ? '&pics=' . esc_attr( $thumb ) : ''; ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( '分享到 QQ 空间', 'echo' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.2 4.5 5 .7-3.6 3.5.85 4.95L12 13.7l-4.45 2.4.85-4.95L4.8 7.7l5-.7L12 2.5z"/></svg>
					<span><?php esc_html_e( '空间', 'echo' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( echo_sh_config( 'sh_weibo', true ) ) : ?>
				<a class="echo-share__btn echo-share__btn--weibo" href="https://service.weibo.com/share/share.php?url=<?php echo esc_attr( $url ); ?>&title=<?php echo esc_attr( $title ); ?><?php echo $thumb ? '&pic=' . esc_attr( $thumb ) : ''; ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( '分享到微博', 'echo' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.5 21c-4.4 0-8-2.5-8-5.6 0-1.7 1.1-3.6 3-4.8-.1-.4-.1-.8 0-1.2.1-1 1-2 2-2.6-.1-.2-.1-.4-.1-.6 0-1.7 2-3 4.5-3s4.5 1.3 4.5 3c0 1-.5 1.9-1.3 2.5 1.2.4 2.2 1.2 2.6 2.2.5-.1 1-.2 1.5-.2 2.5 0 4.5 1.6 4.5 3.6 0 2-2 3.6-4.5 3.6-1 0-1.9-.3-2.7-.7-.8 1-2.4 1.8-4.3 1.8z"/></svg>
					<span><?php esc_html_e( '微博', 'echo' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( echo_sh_config( 'sh_copy', true ) ) : ?>
				<button type="button" class="echo-share__btn echo-share__btn--copy" data-url="<?php echo esc_attr( urldecode( $url ) ); ?>" title="<?php esc_attr_e( '复制链接', 'echo' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
					<span><?php esc_html_e( '复制链接', 'echo' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * 自动注入到文章内容（按设置位置）。
 *
 * @param string $content 正文。
 * @return string
 */
function echo_sh_inject( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! echo_sh_is_on() ) {
		return $content;
	}
	ob_start();
	echo_sh_render( get_the_ID() );
	$box = ob_get_clean();
	return 'before' === echo_sh_config( 'sh_position', 'after' ) ? $box . $content : $content . $box;
}
add_filter( 'the_content', 'echo_sh_inject', 20 );
