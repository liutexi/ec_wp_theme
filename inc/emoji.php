<?php
/**
 * 表情模块：[.表情名] 短代码渲染 / 评论与前台写文章的表情选择器
 *
 * - 表情资源为纯 path 的 SVG（111 个），存放于 assets/emoji/，文件名已规范化为 eNNN.svg。
 * - 短代码与文件名的映射见 inc/emoji-map.php，避免把用户输入直接拼进资源路径。
 * - 渲染时先归一化零宽字符与变体选择符再查表，未命中则原样保留，杜绝任意文件读取。
 * - 全部能力受后台开关控制：正文 / 评论渲染、两处选择器按钮、显示尺寸。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 默认设置
 * ============================================================ */

/**
 * 表情模块默认设置。
 *
 * @return array
 */
function echo_emoji_defaults() {
	return array(
		'emoji_enable'        => true,  // 总开关：关闭后不渲染也不显示选择器
		'emoji_in_content'    => true,  // 文章正文渲染 [.xxx]
		'emoji_in_comment'    => true,  // 评论内容渲染 [.xxx]
		'emoji_picker_post'   => true,  // 前台写文章页显示表情按钮
		'emoji_picker_comment'=> true,  // 评论框显示表情按钮
		'emoji_size'          => 20,    // 正文/评论中表情显示尺寸（px）
	);
}

/**
 * 读取表情模块配置。
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_emoji_config( $key = null, $default = null ) {
	$settings = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_emoji_defaults();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 总开关是否开启。
 *
 * @return bool
 */
function echo_emoji_is_on() {
	return (bool) echo_emoji_config( 'emoji_enable', true );
}

/* ============================================================
 * 设置清洗
 * ============================================================ */

/**
 * 清洗表情模块设置（由 customizer 的 echo_sanitize_settings 委托调用）。
 *
 * @param array $input  原始输入。
 * @param array $output 清洗结果（引用）。
 * @return void
 */
function echo_sanitize_emoji_settings( $input, &$output ) {
	$output['emoji_enable']         = isset( $input['emoji_enable'] ) && '1' === (string) $input['emoji_enable'];
	$output['emoji_in_content']     = isset( $input['emoji_in_content'] ) && '1' === (string) $input['emoji_in_content'];
	$output['emoji_in_comment']     = isset( $input['emoji_in_comment'] ) && '1' === (string) $input['emoji_in_comment'];
	$output['emoji_picker_post']    = isset( $input['emoji_picker_post'] ) && '1' === (string) $input['emoji_picker_post'];
	$output['emoji_picker_comment'] = isset( $input['emoji_picker_comment'] ) && '1' === (string) $input['emoji_picker_comment'];

	$size                   = isset( $input['emoji_size'] ) ? absint( $input['emoji_size'] ) : 20;
	$output['emoji_size']   = max( 12, min( 48, $size ) );
}

/* ============================================================
 * 表情映射与渲染
 * ============================================================ */

/**
 * 归一化短代码：去除零宽字符与变体选择符，便于容错匹配。
 *
 * @param string $code 短代码，如 [.OK]。
 * @return string
 */
function echo_emoji_normalize( $code ) {
	$code = (string) $code;
	$code = str_replace( array( "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF" ), '', $code );
	$code = str_replace( "\xEF\xB8\x8F", '', $code ); // U+FE0F 变体选择符
	return $code;
}

/**
 * 加载并归一化表情映射（键 => array(name, file)），静态缓存。
 *
 * @return array
 */
function echo_emoji_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$path = get_template_directory() . '/inc/emoji-map.php';
	$raw  = file_exists( $path ) ? require $path : array();
	$map  = array();
	if ( is_array( $raw ) ) {
		foreach ( $raw as $code => $item ) {
			if ( ! is_array( $item ) || empty( $item['file'] ) ) {
				continue;
			}
			$key            = echo_emoji_normalize( $code );
			$map[ $key ]    = array(
				'name' => isset( $item['name'] ) ? $item['name'] : $code,
				'file' => sanitize_file_name( $item['file'] ),
			);
		}
	}
	return $map;
}

/**
 * 表情资源基础 URL。
 *
 * @return string
 */
function echo_emoji_base_url() {
	return get_template_directory_uri() . '/assets/emoji/';
}

/**
 * 把文本中的 [.xxx] 渲染为表情图片。
 *
 * @param string $text 原始文本。
 * @return string
 */
function echo_emoji_render( $text ) {
	if ( ! is_string( $text ) || '' === $text || false === strpos( $text, '[.' ) ) {
		return $text;
	}

	$map = echo_emoji_map();
	if ( empty( $map ) ) {
		return $text;
	}

	$base = echo_emoji_base_url();

	// 先把代码块 / 行内代码占位，避免其中的 [.xxx] 被误渲染；渲染后还原。
	$code_blocks = array();
	$text        = preg_replace_callback(
		'/(<pre\b[^>]*>.*?<\/pre>|<code\b[^>]*>.*?<\/code>)/is',
		function ( $m ) use ( &$code_blocks ) {
			$idx                  = count( $code_blocks );
			$code_blocks[ $idx ] = $m[1];
			return "\x00ECHOCODE" . $idx . "\x00";
		},
		$text
	);

	$text = preg_replace_callback(
		'/\[\.([^\]\[]{1,24})\]/u',
		function ( $m ) use ( $map, $base ) {
			$key = echo_emoji_normalize( '[.' . $m[1] . ']' );
			if ( ! isset( $map[ $key ] ) ) {
				return $m[0]; // 未命中：原样保留，绝不拼接任意路径
			}
			$item = $map[ $key ];
			$src  = esc_url( $base . $item['file'] );
			return '<img class="echo-emoji" src="' . $src . '" alt="' . esc_attr( $item['name'] )
				. '" title="' . esc_attr( $key ) . '" loading="lazy" decoding="async" />';
		},
		$text
	);

	// 还原代码块
	foreach ( $code_blocks as $idx => $html ) {
		$text = str_replace( "\x00ECHOCODE" . $idx . "\x00", $html, $text );
	}

	return $text;
}

/**
 * 正文渲染过滤（优先级 20，在 wpautop / shortcode 之后，避免 img 被段落包裹破坏）。
 *
 * @param string $content 正文。
 * @return string
 */
function echo_emoji_filter_content( $content ) {
	if ( ! echo_emoji_is_on() || ! echo_emoji_config( 'emoji_in_content', true ) ) {
		return $content;
	}
	return echo_emoji_render( $content );
}
add_filter( 'the_content', 'echo_emoji_filter_content', 20 );

/**
 * 评论渲染过滤。
 *
 * @param string $text 评论文本。
 * @return string
 */
function echo_emoji_filter_comment( $text ) {
	if ( ! echo_emoji_is_on() || ! echo_emoji_config( 'emoji_in_comment', true ) ) {
		return $text;
	}
	return echo_emoji_render( $text );
}
add_filter( 'comment_text', 'echo_emoji_filter_comment', 20 );

/* ============================================================
 * 选择器数据 / 按钮注入
 * ============================================================ */

/**
 * 供前端选择器使用的表情数据（短代码 / 名称 / 文件）。
 *
 * @return array
 */
function echo_emoji_picker_data() {
	$map  = echo_emoji_map();
	$data = array();
	foreach ( $map as $code => $item ) {
		$data[] = array( $code, $item['name'], $item['file'] );
	}
	return $data;
}

/**
 * 表情选择器按钮图标（内联 SVG，与主题风格一致）。
 *
 * @return string
 */
function echo_emoji_button_icon() {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. '<circle cx="12" cy="12" r="9" /><path d="M8 14s1.5 2 4 2 4-2 4-2" /><line x1="9" y1="9" x2="9.01" y2="9" /><line x1="15" y1="9" x2="15.01" y2="9" /></svg>';
}

/**
 * 输出一个表情按钮（data-echo-emoji-target 指明插入目标）。
 *
 * @param string $target 目标标识：comment / post。
 * @return void
 */
function echo_emoji_picker_button( $target ) {
	if ( ! echo_emoji_is_on() ) {
		return;
	}
	$key = 'comment' === $target ? 'emoji_picker_comment' : 'emoji_picker_post';
	if ( ! echo_emoji_config( $key, true ) ) {
		return;
	}
	?>
	<button type="button" class="echo-emoji-btn" data-echo-emoji-target="<?php echo esc_attr( $target ); ?>"
		title="<?php echo esc_attr__( '插入表情', 'echo' ); ?>" aria-label="<?php echo esc_attr__( '插入表情', 'echo' ); ?>">
		<?php echo echo_emoji_button_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<span><?php esc_html_e( '表情', 'echo' ); ?></span>
	</button>
	<?php
}

/**
 * 把表情按钮追加到评论输入框之后（comment_form 的 comment_field 过滤）。
 *
 * @param string $field 评论输入框 HTML。
 * @return string
 */
function echo_emoji_comment_field( $field ) {
	ob_start();
	echo_emoji_picker_button( 'comment' );
	$button = ob_get_clean();
	return $field . $button;
}
add_filter( 'comment_form_field_comment', 'echo_emoji_comment_field' );
