<?php
/**
 * 外链跳转提醒中转页
 *
 * 全站外链统一经 /go/?token=xxx 中转页提醒后再跳转。
 * - 目标地址存 transient，链接只含随机 token，防止开放重定向。
 * - 仅允许 http/https，禁止 javascript:/data: 等危险协议。
 * - 支持两种跳转方式：倒计时自动跳转 / 手动点击跳转（后台可控）。
 * - 白名单域名（含本站）不经过中转。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 默认设置与读取
 * ============================================================ */

/**
 * 默认设置
 *
 * @return array
 */
function echo_lgo_defaults() {
	return array(
		'lgo_enabled'   => true,
		'lgo_base'      => 'go',
		'lgo_mode'      => 'auto',     // auto=倒计时自动 / manual=手动点击
		'lgo_delay'     => 5,          // 自动跳转倒计时秒数
		'lgo_title'     => __( '即将离开本站', 'echo' ),
		'lgo_message'   => __( '你正在访问一个外部链接，请确认网址安全后再继续。', 'echo' ),
		'lgo_btn_text'  => __( '继续访问', 'echo' ),
		'lgo_new_tab'   => true,       // 目标链接是否新窗口打开
		'lgo_whitelist' => '',         // 白名单域名，每行一个
	);
}

/**
 * 读取单个设置
 *
 * @param string|null $key     键名。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_lgo_config( $key = null, $default = null ) {
	$settings = function_exists( 'echo_get_settings' ) ? echo_get_settings() : get_option( ECHO_OPTION, array() );
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 路由前缀
 *
 * @return string
 */
function echo_lgo_base() {
	$base = sanitize_title( echo_lgo_config( 'lgo_base', 'go' ) );
	return '' !== $base ? $base : 'go';
}

/* ============================================================
 * 路由与模板
 * ============================================================ */

/**
 * 注册重写规则与 query var
 *
 * @return void
 */
function echo_lgo_register_rewrite() {
	add_rewrite_tag( '%echo_lgo%', '1' );
	$base = preg_quote( echo_lgo_base(), '#' );
	add_rewrite_rule(
		'^' . $base . '/?$',
		'index.php?echo_lgo=1',
		'top'
	);
}
add_action( 'init', 'echo_lgo_register_rewrite' );

/**
 * 当前请求是否为中转页
 *
 * @return bool
 */
function echo_lgo_is_page() {
	return (bool) get_query_var( 'echo_lgo' );
}

/**
 * 载入中转页模板
 *
 * @param string $template 原模板。
 * @return string
 */
function echo_lgo_template_include( $template ) {
	if ( ! echo_lgo_is_page() ) {
		return $template;
	}
	$custom = locate_template( 'template-go.php' );
	return $custom ? $custom : $template;
}
add_filter( 'template_include', 'echo_lgo_template_include' );

/**
 * 功能关闭时返回 404
 *
 * @return void
 */
function echo_lgo_maybe_404() {
	if ( ! echo_lgo_is_page() ) {
		return;
	}
	if ( echo_lgo_config( 'lgo_enabled', true ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'echo_lgo_maybe_404' );

/**
 * 中转页不参与 canonical 重定向
 *
 * @param string|false $redirect 目标地址。
 * @return string|false
 */
function echo_lgo_disable_canonical( $redirect ) {
	if ( echo_lgo_is_page() ) {
		return false;
	}
	return $redirect;
}
add_filter( 'redirect_canonical', 'echo_lgo_disable_canonical' );

/**
 * 页面标题
 *
 * @param array $parts 标题片段。
 * @return array
 */
function echo_lgo_document_title( $parts ) {
	if ( echo_lgo_is_page() ) {
		$parts['title'] = echo_lgo_config( 'lgo_title', __( '即将离开本站', 'echo' ) );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'echo_lgo_document_title' );

/**
 * body class
 *
 * @param array $classes 已有 class。
 * @return array
 */
function echo_lgo_body_class( $classes ) {
	if ( echo_lgo_is_page() ) {
		$classes[] = 'echo-lgo-page';
	}
	return $classes;
}
add_filter( 'body_class', 'echo_lgo_body_class' );

/* ============================================================
 * Token 与目标地址（安全核心）
 * ============================================================ */

/**
 * 判断 URL 是否为允许中转的外部链接
 *
 * 只接受 http/https 的绝对地址；本站地址视为内部链接。
 *
 * @param string $url 待判断的地址。
 * @return bool
 */
function echo_lgo_is_external_url( $url ) {
	$url = is_string( $url ) ? trim( $url ) : '';
	if ( '' === $url ) {
		return false;
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}
	$scheme = strtolower( $parts['scheme'] );
	if ( 'http' !== $scheme && 'https' !== $scheme ) {
		return false;
	}

	$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$host      = strtolower( $parts['host'] );
	if ( $host === $home_host || 'www.' . $home_host === $host ) {
		return false;
	}

	// 白名单域名（支持 *.example.com 的二级匹配）
	$whitelist = echo_lgo_whitelist_domains();
	foreach ( $whitelist as $domain ) {
		if ( echo_lgo_host_matches( $host, $domain ) ) {
			return false;
		}
	}
	return true;
}

/**
 * 白名单域名数组
 *
 * @return array
 */
function echo_lgo_whitelist_domains() {
	$raw = (string) echo_lgo_config( 'lgo_whitelist', '' );
	$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );
	$out = array();
	foreach ( explode( "\n", $raw ) as $line ) {
		$line = strtolower( trim( $line ) );
		$line = preg_replace( '#^https?://#i', '', $line );
		$line = trim( (string) preg_replace( '#[/\\?\#].*$#', '', $line ) );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * 域名匹配（精确或子域）
 *
 * @param string $host   实际主机。
 * @param string $domain 白名单规则。
 * @return bool
 */
function echo_lgo_host_matches( $host, $domain ) {
	$host   = strtolower( $host );
	$domain = strtolower( trim( $domain ) );
	if ( '' === $domain ) {
		return false;
	}
	if ( 0 === strpos( $domain, '*.' ) ) {
		$root = substr( $domain, 2 );
		return $host === $root || ( strlen( $host ) > strlen( $root ) && '.' . $root === substr( $host, -strlen( $root ) - 1 ) );
	}
	return $host === $domain || ( strlen( $host ) > strlen( $domain ) && '.' . $domain === substr( $host, -strlen( $domain ) - 1 ) );
}

/**
 * 生成中转链接
 *
 * 目标地址存 transient，链接中只含随机 token。
 *
 * @param string $target 目标地址。
 * @return string 中转链接；无效目标返回空串。
 */
function echo_lgo_build_url( $target ) {
	if ( ! echo_lgo_is_external_url( $target ) ) {
		return '';
	}
	$token  = wp_generate_password( 32, false, false );
	$key    = 'echo_lgo_' . $token;
	set_transient( $key, $target, HOUR_IN_SECONDS );

	$url = home_url( '/' . echo_lgo_base() . '/' );
	return add_query_arg( 'token', $token, $url );
}

/**
 * 根据 token 读取并校验目标地址
 *
 * @param string $token token。
 * @return string 校验通过的目标地址，失败返回空串。
 */
function echo_lgo_resolve_token( $token ) {
	$token = is_string( $token ) ? sanitize_text_field( wp_unslash( $token ) ) : '';
	if ( '' === $token || ! preg_match( '/^[A-Za-z0-9]{20,64}$/', $token ) ) {
		return '';
	}
	$target = get_transient( 'echo_lgo_' . $token );
	if ( ! is_string( $target ) || ! echo_lgo_is_external_url( $target ) ) {
		return '';
	}
	// token 为服务端随机生成且目标存于 transient，外部无法伪造；
	// 保留至过期（1 小时），避免用户刷新页面后链接失效。
	return $target;
}

/**
 * 当前中转页的目标地址
 *
 * @return string
 */
function echo_lgo_current_target() {
	static $target = null;
	if ( null !== $target ) {
		return $target;
	}
	$token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$target = echo_lgo_resolve_token( $token );
	return $target;
}

/* ============================================================
 * AJAX：签发 token
 * ============================================================ */

/**
 * 前端点击外链时调用，为目标地址签发 token 并返回中转链接
 *
 * @return void
 */
function echo_lgo_ajax_token() {
	if ( ! echo_lgo_config( 'lgo_enabled', true ) ) {
		wp_send_json_error( array( 'message' => 'disabled' ) );
	}
	if ( ! check_ajax_referer( 'echo_lgo', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'bad_nonce' ) );
	}
	$target = isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$url    = echo_lgo_build_url( $target );
	if ( '' === $url ) {
		wp_send_json_error( array( 'message' => 'bad_target' ) );
	}
	wp_send_json_success( array( 'url' => $url ) );
}
add_action( 'wp_ajax_echo_lgo_token', 'echo_lgo_ajax_token' );
add_action( 'wp_ajax_nopriv_echo_lgo_token', 'echo_lgo_ajax_token' );

/* ============================================================
 * 刷新重写规则
 * ============================================================ */

/**
 * 主题启用时刷新重写规则
 *
 * @return void
 */
function echo_lgo_flush_rewrite() {
	echo_lgo_register_rewrite();
	flush_rewrite_rules( false );
}
add_action( 'after_switch_theme', 'echo_lgo_flush_rewrite' );

/* ============================================================
 * 后台字段渲染
 * ============================================================ */

/**
 * 中转页地址字段（附带当前地址）
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_lgo_base( $args ) {
	$key  = $args['label_for'];
	$base = sanitize_title( echo_get_setting( $key, 'go' ) );
	?>
	<input type="text" id="<?php echo esc_attr( $key ); ?>" name="echo_settings[<?php echo esc_attr( $key ); ?>]"
		value="<?php echo esc_attr( $base ); ?>" class="regular-text" />
	<p class="description">
		<?php esc_html_e( '中转页地址：', 'echo' ); ?>
		<code><?php echo esc_html( home_url( '/' . ( '' !== $base ? $base : 'go' ) . '/' ) ); ?></code>
	</p>
	<?php
}

/* ============================================================
 * 清洗
 * ============================================================ */

/**
 * 清洗外链中转设置
 *
 * @param array $input  原始提交。
 * @param array $output 已清洗的输出（按引用追加）。
 * @return void
 */
function echo_sanitize_link_go_settings( $input, &$output ) {
	$defaults = echo_lgo_defaults();

	$keys = array_keys( $defaults );
	$has  = false;
	foreach ( $keys as $k ) {
		if ( array_key_exists( $k, $input ) ) {
			$has = true;
			break;
		}
	}
	if ( ! $has ) {
		foreach ( $keys as $k ) {
			$output[ $k ] = echo_get_setting( $k, $defaults[ $k ] );
		}
		return;
	}

	$output['lgo_enabled'] = isset( $input['lgo_enabled'] ) && '1' === (string) $input['lgo_enabled'];
	$output['lgo_new_tab'] = isset( $input['lgo_new_tab'] ) && '1' === (string) $input['lgo_new_tab'];

	$base = isset( $input['lgo_base'] ) ? sanitize_title( $input['lgo_base'] ) : '';
	$output['lgo_base'] = '' !== $base ? $base : 'go';

	$mode = isset( $input['lgo_mode'] ) ? sanitize_key( $input['lgo_mode'] ) : 'auto';
	$output['lgo_mode'] = in_array( $mode, array( 'auto', 'manual' ), true ) ? $mode : 'auto';

	$output['lgo_delay'] = max( 1, min( 30, absint( isset( $input['lgo_delay'] ) ? $input['lgo_delay'] : 5 ) ) );

	$title = isset( $input['lgo_title'] ) ? sanitize_text_field( wp_unslash( $input['lgo_title'] ) ) : '';
	$output['lgo_title'] = '' !== trim( $title ) ? $title : $defaults['lgo_title'];

	$message = isset( $input['lgo_message'] ) ? sanitize_text_field( wp_unslash( $input['lgo_message'] ) ) : '';
	$output['lgo_message'] = '' !== trim( $message ) ? $message : $defaults['lgo_message'];

	$btn = isset( $input['lgo_btn_text'] ) ? sanitize_text_field( wp_unslash( $input['lgo_btn_text'] ) ) : '';
	$output['lgo_btn_text'] = '' !== trim( $btn ) ? $btn : $defaults['lgo_btn_text'];

	$whitelist = isset( $input['lgo_whitelist'] ) ? sanitize_textarea_field( wp_unslash( $input['lgo_whitelist'] ) ) : '';
	$lines = array();
	foreach ( explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $whitelist ) ) as $line ) {
		$line = strtolower( trim( $line ) );
		if ( '' !== $line ) {
			$lines[] = $line;
		}
	}
	$output['lgo_whitelist'] = implode( "\n", array_slice( $lines, 0, 100 ) );
}
