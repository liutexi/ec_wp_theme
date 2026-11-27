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
	// 不能用 get_the_excerpt()：无手动摘要的文章会触发 wp_trim_excerpt →
	// apply_filters('the_content') → 再次进入 echo_sh_inject → 无限递归耗尽内存。
	// 直接读 post 对象字段，绕过所有 the_content filter。
	$desc_raw = '';
	$post_obj = get_post( $post_id );
	if ( $post_obj && ! is_wp_error( $post_obj ) ) {
		$desc_raw = $post_obj->post_excerpt ? $post_obj->post_excerpt : $post_obj->post_content;
		$desc_raw = wp_strip_all_tags( strip_shortcodes( $desc_raw ) );
		$desc_raw = wp_trim_words( $desc_raw, 55, '' );
	}
	$desc  = rawurlencode( $desc_raw );
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
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.395 15.035a40 40 0 0 0-.803-2.264l-1.079-2.695c.001-.032.014-.562.014-.836C19.526 4.632 17.351 0 12 0S4.474 4.632 4.474 9.241c0 .274.013.804.014.836l-1.08 2.695a39 39 0 0 0-.802 2.264c-1.021 3.283-.69 4.643-.438 4.673.54.065 2.103-2.472 2.103-2.472 0 1.469.756 3.387 2.394 4.771-.612.188-1.363.479-1.845.835-.434.32-.379.646-.301.778.343.578 5.883.369 7.482.189 1.6.18 7.14.389 7.483-.189.078-.132.132-.458-.301-.778-.483-.356-1.233-.646-1.846-.836 1.637-1.384 2.393-3.302 2.393-4.771 0 0 1.563 2.537 2.103 2.472.251-.03.581-1.39-.438-4.673"/></svg>
					<span>QQ</span>
				</a>
			<?php endif; ?>
			<?php if ( echo_sh_config( 'sh_qzone', true ) ) : ?>
				<a class="echo-share__btn echo-share__btn--qzone" href="https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=<?php echo esc_attr( $url ); ?>&title=<?php echo esc_attr( $title ); ?>&summary=<?php echo esc_attr( $desc ); ?><?php echo $thumb ? '&pics=' . esc_attr( $thumb ) : ''; ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( '分享到 QQ 空间', 'echo' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.9868 9.2012c-.032-.099-.127-.223-.334-.258-.207-.036-7.352-1.4063-7.352-1.4063s-.105-.022-.198-.07c-.092-.047-.127-.167-.127-.167S12.4472.954 12.3491.7679c-.099-.187-.245-.238-.349-.238-.104 0-.251.051-.349.238C11.5531.954 8.0245 7.3 8.0245 7.3s-.035.12-.128.167c-.092.047-.197.07-.197.07S.5546 8.9071.3466 8.9421c-.208.036-.302.16-.333.258a.477.477 0 00.125.4491L5.5013 15.14s.072.08.119.172c.016.104.005.21.005.21s-1.1891 7.243-1.2201 7.451c-.031.208.075.369.159.4301.083.062.233.106.421.013.189-.093 6.813-3.2614 6.813-3.2614s.098-.044.201-.061c.103-.017.201.061.201.061s6.624 3.1684 6.813 3.2614c.188.094.338.049.421-.013a.463.463 0 00.159-.43c-.021-.14-.93-5.6778-.93-5.6778.876-.5401 1.4251-1.0392 1.8492-1.7473-2.5944.9692-6.0069 1.7173-9.4163 1.8663-.9152.041-2.4104.097-3.4735-.015-.6781-.071-1.1702-.144-1.2432-.438-.053-.2151.054-.4601.5451-.8312a2640.8625 2640.8625 0 012.8614-2.1553c1.2852-.9681 3.5595-2.4703 3.5595-2.7314 0-.285-2.1443-.781-4.0376-.781-1.9452 0-2.2753.132-2.8114.168-.488.034-.769.005-.804-.138-.06-.2481.183-.3891.588-.5682.7091-.314 1.8603-.594 1.9843-.626.194-.052 3.0824-.8051 5.6188-.5351 1.3181.14 3.2444.668 3.2444 1.2762 0 .342-1.7212 1.4942-3.2254 2.5973-1.1492.8431-2.2173 1.5612-2.2173 1.6883 0 .342 3.5334 1.2411 6.6899 1.01l.003-.022c.048-.092.119-.172.119-.172l5.3627-5.4907a.477.477 0 00.127-.449z"/></svg>
					<span><?php esc_html_e( '空间', 'echo' ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( echo_sh_config( 'sh_weibo', true ) ) : ?>
				<a class="echo-share__btn echo-share__btn--weibo" href="https://service.weibo.com/share/share.php?url=<?php echo esc_attr( $url ); ?>&title=<?php echo esc_attr( $title ); ?><?php echo $thumb ? '&pic=' . esc_attr( $thumb ) : ''; ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( '分享到微博', 'echo' ); ?>">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.737 5.439l-.002.004zM9.05 17.219c-.384.616-1.208.884-1.829.602-.612-.279-.793-.991-.406-1.593.379-.595 1.176-.861 1.793-.601.622.263.82.972.442 1.592zm1.27-1.627c-.141.237-.449.353-.689.253-.236-.09-.313-.361-.177-.586.138-.227.436-.346.672-.24.239.09.315.36.18.601l.014-.028zm.176-2.719c-1.893-.493-4.033.45-4.857 2.118-.836 1.704-.026 3.591 1.886 4.21 1.983.64 4.318-.341 5.132-2.179.8-1.793-.201-3.642-2.161-4.149zm7.563-1.224c-.346-.105-.57-.18-.405-.615.375-.977.42-1.804 0-2.404-.781-1.112-2.915-1.053-5.364-.03 0 0-.766.331-.571-.271.376-1.217.315-2.224-.27-2.809-1.338-1.337-4.869.045-7.888 3.08C1.309 10.87 0 13.273 0 15.348c0 3.981 5.099 6.395 10.086 6.395 6.536 0 10.888-3.801 10.888-6.82 0-1.822-1.547-2.854-2.915-3.284v.01zm1.908-5.092c-.766-.856-1.908-1.187-2.96-.962-.436.09-.706.511-.616.932.09.42.511.691.932.602.511-.105 1.067.044 1.442.465.376.421.466.977.316 1.473-.136.406.089.856.51.992.405.119.857-.105.992-.512.33-1.021.12-2.178-.646-3.035l.03.045zm2.418-2.195c-1.576-1.757-3.905-2.419-6.054-1.968-.496.104-.812.587-.706 1.081.104.496.586.813 1.082.707 1.532-.331 3.185.15 4.296 1.383 1.112 1.246 1.429 2.943.947 4.416-.165.48.106 1.007.586 1.157.479.165.991-.104 1.157-.586.675-2.088.241-4.478-1.338-6.235l.03.045z"/></svg>
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
	static $injecting = false;
	// 递归守卫：the_content filter 内间接再触发 the_content 时直接放行，
	// 避免任何潜在的无限递归（如摘要生成、其它插件 filter）。
	if ( $injecting ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! echo_sh_is_on() ) {
		return $content;
	}
	$injecting = true;
	ob_start();
	echo_sh_render( get_the_ID() );
	$box = ob_get_clean();
	$injecting = false;
	return 'before' === echo_sh_config( 'sh_position', 'after' ) ? $box . $content : $content . $box;
}
add_filter( 'the_content', 'echo_sh_inject', 20 );
