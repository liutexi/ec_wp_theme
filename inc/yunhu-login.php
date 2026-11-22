<?php
/**
 * 云湖 OAuth2 登录（独立接入，不依赖彩虹登录插件）
 *
 * - 标准 OAuth2 授权码模式 + 强制 PKCE S256。
 * - state / code_verifier 存 PHP session，回调先校验 state，再用原始 verifier 兑换令牌。
 * - 用户信息用 user_id（云湖用户唯一标识）关联本地账号，昵称/头像仅作资料填充。
 * - 复用主题既有的社交登录会话、查找、绑定、自动注册与登录跳转流程。
 *
 * 文档：https://www.yhchat.com/c/p/45805
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 默认设置 / 配置
 * ============================================================ */

/**
 * 云湖登录默认设置。
 *
 * @return array
 */
function echo_yh_defaults() {
	return array(
		'yh_enable'      => false,
		'yh_client_id'   => '',
		'yh_client_secret' => '',
	);
}

/**
 * 读取云湖配置。
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_yh_config( $key = null, $default = null ) {
	$settings = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_yh_defaults();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 是否已完成配置且启用（且总社交登录开关开启）。
 *
 * @return bool
 */
function echo_yh_ready() {
	if ( ! echo_yh_config( 'yh_enable', false ) ) {
		return false;
	}
	if ( '' === trim( (string) echo_yh_config( 'yh_client_id', '' ) ) || '' === trim( (string) echo_yh_config( 'yh_client_secret', '' ) ) ) {
		return false;
	}
	// 主题总社交登录开关与云湖自身开关独立：总开关关闭时不显示任何第三方登录。
	if ( function_exists( 'echo_social_config' ) && ! echo_social_config( 'social_login', true ) ) {
		return false;
	}
	return true;
}

/**
 * 云湖 OAuth 服务地址。
 *
 * @return string
 */
function echo_yh_base_url() {
	return 'https://oauth2.jwzhd.com';
}

/**
 * 回调地址（需在云湖应用后台登记为完全一致的地址）。
 *
 * @return string
 */
function echo_yh_callback_url() {
	return home_url( 'index.php/wp-json/echo-yunhu/callback/' );
}

/* ============================================================
 * 设置清洗
 * ============================================================ */

/**
 * 清洗云湖设置（由 customizer 委托调用）。
 *
 * @param array $input  原始输入。
 * @param array $output 清洗结果（引用）。
 * @return void
 */
function echo_sanitize_yh_settings( $input, &$output ) {
	$output['yh_enable']       = isset( $input['yh_enable'] ) && '1' === (string) $input['yh_enable'];
	$output['yh_client_id']    = isset( $input['yh_client_id'] ) ? sanitize_text_field( $input['yh_client_id'] ) : '';
	$output['yh_client_secret'] = isset( $input['yh_client_secret'] ) ? sanitize_text_field( $input['yh_client_secret'] ) : '';
}

/* ============================================================
 * PKCE 工具
 * ============================================================ */

/**
 * 生成安全随机串（用于 state / code_verifier）。
 *
 * @param int $bytes 随机字节数。
 * @return string
 */
function echo_yh_random( $bytes = 32 ) {
	if ( function_exists( 'random_bytes' ) ) {
		try {
			return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
		} catch ( Exception $e ) {
			// 回退到 wp_generate_password。
		}
	}
	return wp_generate_password( max( 43, $bytes * 2 ), false, false );
}

/**
 * 由 code_verifier 计算 PKCE S256 挑战值。
 *
 * @param string $verifier code_verifier。
 * @return string
 */
function echo_yh_pkce_challenge( $verifier ) {
	return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
}

/**
 * 确保 Session 可用（复用主题既有实现，若不存在则自行开启）。
 *
 * @return bool
 */
function echo_yh_start_session() {
	if ( function_exists( 'echo_social_start_session' ) ) {
		return echo_social_start_session();
	}
	return session_status() === PHP_SESSION_ACTIVE || ( ! headers_sent() && @session_start() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/* ============================================================
 * 渲染：登录按钮
 * ============================================================ */

/**
 * 输出云湖登录按钮（挂 'clogin-passport'，与既有平台按钮并列）。
 *
 * @return void
 */
function echo_yh_passport_button() {
	if ( ! echo_yh_ready() ) {
		return;
	}

	$redirect_uri = '';
	global $pagenow;
	$on_login_page = isset( $pagenow ) && 'wp-login.php' === $pagenow;
	if ( ! is_admin() && ! $on_login_page ) {
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : home_url();
		$fallback     = ( 0 === strpos( $request_uri, '/' ) ) ? $request_uri : home_url();
		$referer      = isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		$redirect_uri = '' !== $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
	}

	$url = add_query_arg( 'rurl', rawurlencode( $redirect_uri ), home_url( 'index.php/wp-json/echo-yunhu/login' ) );
	$icon = get_template_directory_uri() . '/inc/clogin/icon/yunhu.png';
	$name = __( '云湖登录', 'echo' );
	echo '<a class="echo-social-btn loginbtn echo-yh-btn" href="' . esc_url( $url ) . '" title="' . esc_attr( $name ) . '">'
		. '<img src="' . esc_url( $icon ) . '" alt="' . esc_attr( $name ) . '" />'
		. '</a>';
}
add_action( 'clogin-passport', 'echo_yh_passport_button', 20 );

/* ============================================================
 * REST：发起登录 / 回调
 * ============================================================ */

/**
 * 注册 REST 路由。
 *
 * @return void
 */
function echo_yh_register_rest() {
	if ( ! echo_yh_ready() ) {
		return;
	}
	register_rest_route(
		'echo-yunhu',
		'login',
		array(
			'methods'             => 'GET',
			'callback'            => 'echo_yh_rest_login',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'echo-yunhu',
		'callback',
		array(
			'methods'             => 'GET',
			'callback'            => 'echo_yh_rest_callback',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'echo_yh_register_rest' );

/**
 * 发起授权跳转：生成 state 与 PKCE 参数，保存会话，重定向到云湖授权页。
 *
 * @param WP_REST_Request $request 请求对象。
 * @return WP_Error|void
 */
function echo_yh_rest_login( $request ) {
	if ( ! echo_yh_ready() ) {
		return new WP_Error( 'echo_yh_disabled', __( '云湖登录未启用或未配置。', 'echo' ), array( 'status' => 404 ) );
	}
	if ( ! echo_yh_start_session() ) {
		return new WP_Error( 'echo_yh_session', __( '无法创建登录会话。', 'echo' ), array( 'status' => 500 ) );
	}

	// 智能跳转：保存用户原本想访问的页面（白名单校验）。
	$rurl = $request->get_param( 'rurl' );
	if ( is_string( $rurl ) && '' !== $rurl ) {
		$validated = wp_validate_redirect( wp_unslash( $rurl ), '' );
		if ( '' !== $validated ) {
			$_SESSION['echo_social_redirect'] = $validated;
		}
	}

	$state   = echo_yh_random( 32 );
	$verifier = echo_yh_random( 48 );
	$params  = array(
		'response_type'         => 'code',
		'client_id'             => trim( (string) echo_yh_config( 'yh_client_id', '' ) ),
		'redirect_uri'          => echo_yh_callback_url(),
		'scope'                 => 'openid profile',
		'state'                 => $state,
		'code_challenge'        => echo_yh_pkce_challenge( $verifier ),
		'code_challenge_method' => 'S256',
	);

	$_SESSION['echo_yh_state']   = $state;
	$_SESSION['echo_yh_verifier'] = $verifier;
	$_SESSION['echo_social_type'] = 'yunhu';
	session_write_close();

	$url = echo_yh_base_url() . '/oauth/authorize?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	wp_redirect( $url );
	exit;
}

/**
 * 回调：校验 state → 兑换令牌 → 获取用户信息 → 绑定/注册/登录。
 *
 * @param WP_REST_Request $request 请求对象。
 * @return WP_Error|void
 */
function echo_yh_rest_callback( $request ) {
	$state = $request->get_param( 'state' );
	$code  = $request->get_param( 'code' );
	$error = $request->get_param( 'error' );

	if ( 'access_denied' === $error ) {
		wp_redirect( wp_login_url() );
		exit;
	}

	if ( ! echo_yh_start_session() || empty( $_SESSION['echo_yh_state'] ) || ! is_string( $state ) || ! hash_equals( (string) $_SESSION['echo_yh_state'], $state ) ) {
		return new WP_Error( 'echo_yh_state', __( '状态校验失败，请重试。', 'echo' ), array( 'status' => 403 ) );
	}
	if ( ! is_string( $code ) || '' === $code ) {
		return new WP_Error( 'echo_yh_code', __( '授权码缺失，请重试。', 'echo' ), array( 'status' => 400 ) );
	}

	$verifier = isset( $_SESSION['echo_yh_verifier'] ) ? (string) $_SESSION['echo_yh_verifier'] : '';
	if ( '' === $verifier ) {
		return new WP_Error( 'echo_yh_verifier', __( '登录会话已失效，请重试。', 'echo' ), array( 'status' => 400 ) );
	}

	// 消费本次授权记录，避免回调被重复处理。
	unset( $_SESSION['echo_yh_state'], $_SESSION['echo_yh_verifier'] );
	session_write_close();

	// 用 code 兑换 access_token。
	$token = echo_yh_fetch_token( $code, $verifier );
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	// 获取用户信息。
	$info = echo_yh_fetch_userinfo( $token );
	if ( is_wp_error( $info ) ) {
		return $info;
	}

	$openid = isset( $info['user_id'] ) ? (string) $info['user_id'] : '';
	if ( '' === $openid ) {
		return new WP_Error( 'echo_yh_data', __( '登录数据不完整。', 'echo' ), array( 'status' => 502 ) );
	}
	$nickname = isset( $info['nickname'] ) && is_string( $info['nickname'] ) ? $info['nickname'] : '';
	$avatar   = isset( $info['avatar_url'] ) && is_string( $info['avatar_url'] ) ? $info['avatar_url'] : '';
	$meta_key = 'clogin_passport_yunhu';

	// 已登录：绑定当前账号（与既有社交绑定逻辑一致）。
	$current_id = function_exists( 'echo_social_current_user_id' ) ? echo_social_current_user_id() : ( is_user_logged_in() ? get_current_user_id() : 0 );
	if ( $current_id ) {
		update_user_meta( $current_id, $meta_key, $openid );
		if ( function_exists( 'echo_social_delete_duplicate_meta' ) ) {
			echo_social_delete_duplicate_meta( $current_id, $meta_key, $openid );
		}
		if ( '' !== $avatar && function_exists( 'set_url_scheme' ) ) {
			update_user_meta( $current_id, 'clogin_passport_avatar', set_url_scheme( esc_url_raw( $avatar ) ) );
		}
		echo_yh_redirect_after_login();
	}

	// 已绑定用户：直接登录。
	$user = function_exists( 'echo_social_find_user' ) ? echo_social_find_user( 'yunhu', $openid ) : false;
	if ( $user ) {
		echo_yh_do_login( $user );
	}

	// 未绑定且不允许自动注册：回登录页。
	$auto_reg = function_exists( 'echo_social_config' ) ? (bool) echo_social_config( 'social_autoreg', false ) : false;
	if ( ! get_option( 'users_can_register' ) || ! $auto_reg ) {
		wp_redirect( wp_login_url() );
		exit;
	}

	// 自动注册：用户名用 openid 派生，避免昵称重复/变更导致冲突。
	$user_login = 'yh_' . substr( md5( 'yunhu' . $openid ), 0, 12 );
	$display    = '' !== $nickname ? sanitize_text_field( $nickname ) : $user_login;
	$meta_input = array( $meta_key => $openid );
	if ( '' !== $avatar ) {
		$meta_input['clogin_passport_avatar'] = set_url_scheme( esc_url_raw( $avatar ) );
	}
	$user_id = wp_insert_user(
		array(
			'user_login'    => $user_login,
			'user_pass'     => wp_generate_password(),
			'user_nicename' => sanitize_title( $display ),
			'nickname'      => $display,
			'display_name'  => $display,
			'role'          => get_option( 'default_role' ),
			'meta_input'    => $meta_input,
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'echo_yh_register', $user_id->get_error_message(), array( 'status' => 500 ) );
	}
	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		return new WP_Error( 'echo_yh_register', __( '自动注册失败。', 'echo' ), array( 'status' => 500 ) );
	}
	echo_yh_do_login( $user );
}

/**
 * 用授权码兑换 access_token。
 *
 * @param string $code    授权码。
 * @param string $verifier 原始 code_verifier。
 * @return array|WP_Error token 数组或错误。
 */
function echo_yh_fetch_token( $code, $verifier ) {
	$response = wp_remote_post(
		echo_yh_base_url() . '/oauth/token',
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => echo_yh_callback_url(),
				'client_id'     => trim( (string) echo_yh_config( 'yh_client_id', '' ) ),
				'client_secret' => trim( (string) echo_yh_config( 'yh_client_secret', '' ) ),
				'code_verifier' => $verifier,
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'echo_yh_token', __( '连接云湖服务失败。', 'echo' ), array( 'status' => 502 ) );
	}
	$status = wp_remote_retrieve_response_code( $response );
	$body   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || ! is_array( $body ) || empty( $body['access_token'] ) ) {
		return new WP_Error( 'echo_yh_token', __( '令牌兑换失败，请重新授权。', 'echo' ), array( 'status' => 502 ) );
	}
	return $body;
}

/**
 * 用 access_token 获取用户信息。
 *
 * @param array $token token 数组。
 * @return array|WP_Error 用户信息或错误。
 */
function echo_yh_fetch_userinfo( $token ) {
	$access = isset( $token['access_token'] ) ? (string) $token['access_token'] : '';
	if ( '' === $access ) {
		return new WP_Error( 'echo_yh_userinfo', __( '令牌无效。', 'echo' ), array( 'status' => 502 ) );
	}
	$response = wp_remote_get(
		echo_yh_base_url() . '/api/userinfo',
		array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $access ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'echo_yh_userinfo', __( '获取用户信息失败。', 'echo' ), array( 'status' => 502 ) );
	}
	$status = wp_remote_retrieve_response_code( $response );
	$body   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || ! is_array( $body ) ) {
		return new WP_Error( 'echo_yh_userinfo', __( '获取用户信息失败，请重新授权。', 'echo' ), array( 'status' => 502 ) );
	}
	return $body;
}

/**
 * 云湖登录后完成登录并设置会话。
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_yh_do_login( $user ) {
	wp_set_current_user( $user->ID, $user->user_login );
	wp_set_auth_cookie( $user->ID, true );
	do_action( 'wp_login', $user->user_login, $user );
	echo_yh_redirect_after_login();
}

/**
 * 登录成功后跳转：优先 session 中保存的原页面（智能跳转）。
 *
 * @return void
 */
function echo_yh_redirect_after_login() {
	// 复用既有实现（它读取 echo_social_redirect 并做白名单校验后 exit）。
	if ( function_exists( 'echo_social_redirect_after_login' ) ) {
		echo_social_redirect_after_login();
		exit;
	}
	wp_safe_redirect( home_url() );
	exit;
}
