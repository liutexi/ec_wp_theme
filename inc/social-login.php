<?php
/**
 * 彩虹聚合登录 —— 主题原生集成（基于 Clogin Passport 1.1, GPL2）
 *
 * 完整内置 OAuth 流程：后台配置 → 登录按钮 → 授权回调 → 绑定 / 自动注册 → 登录，
 * 无需再单独安装「Clogin Passport」插件。
 *
 * 渲染沿用钩子方式：
 * - do_action( 'clogin-passport' )                      登录按钮
 * - do_action( 'binding_social_media_account', $user )  绑定入口
 * 配置未填写（无 appid / 无启用平台）时钩子输出为空，主题渲染函数不输出任何容器。
 *
 * 头像优先级：主题自定义头像 > 社交头像（clogin_passport_avatar） > Gravatar。
 *
 * 上游：https://u.cccyun.cc/  · Clogin Passport by cccyun (GPL2)
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/clogin/lib/Oauth.php';

/**
 * 支持的登录平台
 *
 * @return array type => 名称
 */
function echo_social_platforms() {
	return array(
		'qq'        => 'QQ',
		'wx'        => '微信',
		'alipay'    => '支付宝',
		'sina'      => '微博',
		'baidu'     => '百度',
		'huawei'    => '华为',
		'google'    => 'Google',
		'microsoft' => 'Microsoft',
		'facebook'  => 'Facebook',
		'twitter'   => 'Twitter',
		'dingtalk'  => '钉钉',
		'github'    => 'GitHub',
		'gitee'     => 'Gitee',
	);
}

/**
 * 默认设置
 *
 * @return array
 */
function echo_social_defaults() {
	return array(
		'social_login'   => true,
		'social_title'   => '第三方登录',
		'social_binding' => true,
		'social_avatar'  => true,
		'social_appurl'  => '',
		'social_appid'   => '',
		'social_appkey'  => '',
		'social_types'   => array(),
		'social_autoreg' => false,
	);
}

/**
 * 读取一项或全部设置
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_social_config( $key = null, $default = null ) {
	$settings = echo_get_settings();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 是否已正确配置（有 appid 且至少启用一个平台）
 *
 * @return bool
 */
function echo_social_ready() {
	$appid = trim( (string) echo_social_config( 'social_appid', '' ) );
	$types = echo_social_config( 'social_types', array() );
	return '' !== $appid && is_array( $types ) && ! empty( $types );
}

/**
 * 已启用的平台（过滤白名单）
 *
 * @return array
 */
function echo_social_active_types() {
	$types   = echo_social_config( 'social_types', array() );
	$allowed = echo_social_platforms();
	$out     = array();
	if ( ! is_array( $types ) ) {
		return $out;
	}
	foreach ( $types as $type ) {
		if ( is_string( $type ) && isset( $allowed[ $type ] ) ) {
			$out[] = $type;
		}
	}
	return $out;
}

/**
 * 某平台是否已启用
 *
 * @param string $type 平台标识。
 * @return bool
 */
function echo_social_is_active( $type ) {
	return in_array( $type, echo_social_active_types(), true );
}

/**
 * OAuth 配置（供 Oauth 类使用）
 *
 * @return array
 */
function echo_social_oauth_config() {
	return array(
		'appurl' => trim( (string) echo_social_config( 'social_appurl', '' ) ),
		'appid'  => trim( (string) echo_social_config( 'social_appid', '' ) ),
		'appkey' => trim( (string) echo_social_config( 'social_appkey', '' ) ),
	);
}

/**
 * 回调地址
 *
 * @return string
 */
function echo_social_callback_url() {
	return home_url( 'index.php/wp-json/echo-social/callback/' );
}

/**
 * 图标 URL
 *
 * @param string $type 平台标识。
 * @return string
 */
function echo_social_icon_url( $type ) {
	return get_template_directory_uri() . '/inc/clogin/icon/' . sanitize_key( $type ) . '.png';
}

/* ============================================================
 * 渲染：登录按钮 / 绑定入口（钩子输出）
 * ============================================================ */

/**
 * 登录按钮（挂 'clogin-passport'）
 *
 * @return void
 */
function echo_social_passport_buttons() {
	if ( ! echo_social_ready() ) {
		return;
	}

	global $pagenow;
	$redirect_uri = '';
	$on_login_page = isset( $pagenow ) && 'wp-login.php' === $pagenow;
	if ( ! is_admin() && ! $on_login_page ) {
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : home_url();
		$fallback     = ( 0 === strpos( $request_uri, '/' ) ) ? $request_uri : home_url();
		$referer      = isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		$redirect_uri = '' !== $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
	}

	$platforms = echo_social_platforms();
	foreach ( echo_social_active_types() as $type ) {
		$url  = add_query_arg( 'rurl', rawurlencode( $redirect_uri ), home_url( 'index.php/wp-json/echo-social/login/' . $type ) );
		$name = $platforms[ $type ];
		echo '<a class="echo-social-btn loginbtn" href="' . esc_url( $url ) . '" title="' . esc_attr( $name ) . '">'
			. '<img src="' . esc_url( echo_social_icon_url( $type ) ) . '" alt="' . esc_attr( $name ) . '" />'
			. '</a>';
	}
}
add_action( 'clogin-passport', 'echo_social_passport_buttons' );

/**
 * 若用户仍装着 Clogin Passport 插件，移除它在共享钩子上的回调，避免按钮重复。
 * 主题已原生实现同一套逻辑，插件的 REST 路由也会一并失效（主题接管）。
 *
 * @return void
 */
function echo_social_remove_plugin_hooks() {
	$shared = array( 'clogin-passport', 'binding_social_media_account', 'login_form', 'show_user_profile' );
	foreach ( $shared as $hook ) {
		if ( empty( $GLOBALS['wp_filter'][ $hook ] ) ) {
			continue;
		}
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$fn = isset( $callback['function'] ) ? $callback['function'] : null;
				if ( ! $fn ) {
					continue;
				}
				// 识别插件命名空间下的回调
				$ref = '';
				if ( is_array( $fn ) && is_object( $fn[0] ) ) {
					$ref = get_class( $fn[0] );
				} elseif ( is_string( $fn ) ) {
					$ref = $fn;
				} elseif ( $fn instanceof \Closure ) {
					$ref = 'closure';
				}
				if ( 0 === strpos( $ref, 'clogin_passport\\' ) ) {
					remove_action( $hook, $fn, $priority );
				}
			}
		}
	}
}
add_action( 'init', 'echo_social_remove_plugin_hooks', 5 );

// 原生登录页 / wp_login_form() 也显示按钮（替代原插件的钩子）
add_action( 'login_form', 'echo_social_passport_buttons' );
add_filter( 'login_form_middle', 'echo_social_login_form_middle', 10, 2 );

/**
 * wp_login_form() 中间插入按钮
 *
 * @param string $content   原内容。
 * @param array  $args      表单参数。
 * @return string
 */
function echo_social_login_form_middle( $content, $args = array() ) {
	ob_start();
	echo_social_passport_buttons();
	return $content . ob_get_clean();
}

/**
 * 绑定入口（挂 'binding_social_media_account'）
 *
 * @param WP_User|null $profileuser 用户对象。
 * @return void
 */
function echo_social_binding_form( $profileuser = null ) {
	if ( ! echo_social_ready() || ! $profileuser || empty( $profileuser->ID ) ) {
		return;
	}

	$platforms = echo_social_platforms();
	foreach ( echo_social_active_types() as $type ) {
		$url    = add_query_arg( 'rurl', rawurlencode( admin_url( 'profile.php' ) ), home_url( 'index.php/wp-json/echo-social/login/' . $type ) );
		$openid = get_user_meta( $profileuser->ID, 'clogin_passport_' . $type, true );
		$label  = $platforms[ $type ] . ( $openid ? '（' . __( '已绑定', 'echo' ) . '）' : '' );
		echo '<a class="button ' . ( $openid ? '' : 'button-primary' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
	}
}
add_action( 'binding_social_media_account', 'echo_social_binding_form' );

// 后台个人资料页显示绑定入口
add_action( 'show_user_profile', 'echo_social_admin_binding' );

/**
 * 后台资料页输出绑定区块
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_social_admin_binding( $user ) {
	if ( ! echo_social_ready() ) {
		return;
	}
	echo '<h2>' . esc_html__( '社交账号绑定', 'echo' ) . '</h2>';
	echo '<table class="form-table"><tbody>';
	do_action( 'binding_social_media_account', $user );
	echo '</tbody></table>';
}

/* ============================================================
 * 前台容器渲染（主题调用）
 * ============================================================ */

/**
 * 渲染登录 / 注册页的第三方登录按钮
 *
 * @return void
 */
function echo_social_login_buttons() {
	if ( ! echo_social_config( 'social_login', true ) ) {
		return;
	}

	$html = echo_social_capture( 'login' );
	if ( '' === $html ) {
		return;
	}

	$title = trim( (string) echo_social_config( 'social_title', '第三方登录' ) );
	?>
	<div class="echo-social-login">
		<?php if ( '' !== $title ) : ?>
			<div class="echo-social__divider"><span><?php echo esc_html( $title ); ?></span></div>
		<?php endif; ?>
		<div class="echo-social__list"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
	<?php
}

/**
 * 渲染个人中心的社交账号绑定入口
 *
 * @param WP_User|null $user 用户对象，默认当前用户。
 * @return void
 */
function echo_social_binding_buttons( $user = null ) {
	if ( ! echo_social_config( 'social_binding', true ) || ! is_user_logged_in() ) {
		return;
	}
	if ( ! $user ) {
		$user = wp_get_current_user();
	}
	if ( ! echo_social_ready() ) {
		return;
	}

	$arg = $user;
	if ( ! function_exists( 'get_user_to_edit' ) ) {
		$user_file = ABSPATH . 'wp-admin/includes/user.php';
		if ( file_exists( $user_file ) ) {
			require_once $user_file;
		}
	}
	if ( function_exists( 'get_user_to_edit' ) ) {
		$edit = get_user_to_edit( $user->ID );
		if ( $edit ) {
			$arg = $edit;
		}
	}

	ob_start();
	do_action( 'binding_social_media_account', $arg );
	$html = trim( (string) ob_get_clean() );
	if ( '' === $html ) {
		return;
	}
	?>
	<div class="echo-social-binding">
		<h3 class="echo-social-binding__title"><?php esc_html_e( '社交账号绑定', 'echo' ); ?></h3>
		<div class="echo-social-binding__list"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
	<?php
}

/**
 * 捕捉 'clogin-passport' 钩子的输出
 *
 * @return string
 */
function echo_social_capture() {
	ob_start();
	do_action( 'clogin-passport' );
	return trim( (string) ob_get_clean() );
}

/**
 * 是否可用（原生集成后等价于是否配置完成）
 *
 * @return bool
 */
function echo_social_plugin_active() {
	return echo_social_ready();
}

/* ============================================================
 * REST：授权跳转与回调
 * ============================================================ */

/**
 * 注册 REST 路由
 *
 * @return void
 */
function echo_social_register_rest() {
	if ( ! echo_social_ready() ) {
		return;
	}
	register_rest_route(
		'echo-social',
		'login/(?P<slug>[a-z0-9]+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'echo_social_rest_login',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'echo-social',
		'callback',
		array(
			'methods'             => 'GET',
			'callback'            => 'echo_social_rest_callback',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'echo_social_register_rest' );

/**
 * 确保 Session 可用
 *
 * @return bool
 */
function echo_social_start_session() {
	return session_status() === PHP_SESSION_ACTIVE || ( ! headers_sent() && @session_start() );
}

/**
 * 发起授权跳转
 *
 * @param WP_REST_Request $request 请求对象。
 * @return WP_Error|void
 */
function echo_social_rest_login( $request ) {
	$slug = sanitize_key( $request->get_param( 'slug' ) );
	if ( ! echo_social_is_active( $slug ) ) {
		return new WP_Error( 'echo_social_disabled', __( '该登录方式未启用。', 'echo' ), array( 'status' => 404 ) );
	}
	if ( ! echo_social_start_session() ) {
		return new WP_Error( 'echo_social_session', __( '无法创建登录会话。', 'echo' ), array( 'status' => 500 ) );
	}

	$rurl = $request->get_param( 'rurl' );
	if ( is_string( $rurl ) && '' !== $rurl ) {
		$_SESSION['echo_social_redirect'] = wp_validate_redirect( wp_unslash( $rurl ), home_url() );
	}

	$config             = echo_social_oauth_config();
	$config['callback'] = echo_social_callback_url();
	$oauth              = new \clogin_passport\lib\Oauth( $config );
	$result             = $oauth->login( $slug );

	$url   = isset( $result['url'] ) && is_string( $result['url'] ) ? $result['url'] : '';
	$parts = '' !== $url ? wp_parse_url( $url ) : false;
	$code  = isset( $result['code'] ) ? $result['code'] : null;
	if ( ! in_array( $code, array( 0, '0' ), true ) || empty( $parts['host'] ) || ! in_array( strtolower( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ), array( 'http', 'https' ), true ) ) {
		$msg = isset( $result['msg'] ) ? $result['msg'] : __( '获取登录地址失败。', 'echo' );
		return new WP_Error( 'echo_social_login_url', $msg, array( 'status' => 502 ) );
	}

	$_SESSION['echo_social_type'] = $slug;
	session_write_close();
	wp_redirect( $url );
	exit;
}

/**
 * 登录成功后跳转
 *
 * @return void
 */
function echo_social_redirect_after_login() {
	$redirect = isset( $_SESSION['echo_social_redirect'] ) ? $_SESSION['echo_social_redirect'] : '';
	unset( $_SESSION['echo_social_redirect'] );

	$fallback = function_exists( 'echo_auth_after_login_url' ) ? echo_auth_after_login_url() : home_url();
	if ( ! is_string( $redirect ) || '' === $redirect || preg_match( '/\.(css|js|png|jpe?g|gif|svg|ico)$/i', $redirect ) ) {
		$redirect = $fallback;
	}

	// 智能跳转：若目标仍是登录 / 注册页自身，则回退到默认落地页，避免"登录后又回登录页"。
	$path = wp_parse_url( $redirect, PHP_URL_PATH );
	if ( is_string( $path ) ) {
		$auth_slugs = array();
		if ( function_exists( 'echo_auth_config' ) ) {
			$auth_slugs[] = trim( (string) echo_auth_config( 'auth_login_slug', 'login' ), '/' );
			$auth_slugs[] = trim( (string) echo_auth_config( 'auth_register_slug', 'register' ), '/' );
		}
		$auth_slugs = array_filter( $auth_slugs, 'strlen' );
		foreach ( $auth_slugs as $slug ) {
			if ( '/' . $slug === untrailingslashit( $path ) || 0 === strpos( $path, '/' . $slug . '/' ) ) {
				$redirect = $fallback;
				break;
			}
		}
	}

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * 授权回调：换取用户信息 → 绑定 / 注册 / 登录
 *
 * @param WP_REST_Request $request 请求对象。
 * @return WP_Error|void
 */
function echo_social_rest_callback( $request ) {
	$state = $request->get_param( 'state' );
	$code  = $request->get_param( 'code' );
	$type  = $request->get_param( 'type' );

	if ( ! is_string( $state ) || '' === $state || ! is_string( $code ) || '' === $code ) {
		wp_redirect( wp_login_url() );
		exit;
	}

	if ( ! echo_social_start_session() || empty( $_SESSION['echo_social_state'] ) || ! is_string( $_SESSION['echo_social_state'] ) || ! hash_equals( $_SESSION['echo_social_state'], $state ) ) {
		return new WP_Error( 'echo_social_state', __( '状态校验失败，请重试。', 'echo' ), array( 'status' => 403 ) );
	}

	$type = is_string( $type ) ? sanitize_key( $type ) : '';
	if ( '' === $type || ! echo_social_is_active( $type ) || ( isset( $_SESSION['echo_social_type'] ) && $_SESSION['echo_social_type'] !== $type ) ) {
		return new WP_Error( 'echo_social_disabled', __( '该登录方式未启用。', 'echo' ), array( 'status' => 400 ) );
	}

	$config = echo_social_oauth_config();
	$oauth  = new \clogin_passport\lib\Oauth( $config );
	$result = $oauth->callback( $code, $type );
	if ( ! is_array( $result ) || ! in_array( isset( $result['code'] ) ? $result['code'] : null, array( 0, '0' ), true ) ) {
		$msg = isset( $result['msg'] ) ? $result['msg'] : __( '获取用户信息失败。', 'echo' );
		return new WP_Error( 'echo_social_callback', $msg, array( 'status' => 502 ) );
	}

	$openid = isset( $result['social_uid'] ) ? $result['social_uid'] : '';
	if ( ! is_scalar( $openid ) || '' === (string) $openid ) {
		return new WP_Error( 'echo_social_data', __( '登录数据不完整。', 'echo' ), array( 'status' => 502 ) );
	}
	$openid   = (string) $openid;
	$avatar   = isset( $result['faceimg'] ) && is_string( $result['faceimg'] ) ? $result['faceimg'] : '';
	$meta_key = 'clogin_passport_' . $type;
	unset( $_SESSION['echo_social_state'], $_SESSION['echo_social_type'] );

	// 已登录：绑定当前账号
	$current_id = echo_social_current_user_id();
	if ( $current_id ) {
		update_user_meta( $current_id, $meta_key, $openid );
		echo_social_delete_duplicate_meta( $current_id, $meta_key, $openid );
		if ( '' !== $avatar ) {
			update_user_meta( $current_id, 'clogin_passport_avatar', set_url_scheme( esc_url_raw( $avatar ) ) );
		}
		echo_social_redirect_after_login();
	}

	// 已绑定用户：直接登录
	$user = echo_social_find_user( $type, $openid );
	if ( $user ) {
		echo_social_do_login( $user );
	}

	// 未绑定且不允许自动注册
	if ( ! get_option( 'users_can_register' ) || ! echo_social_config( 'social_autoreg', false ) ) {
		wp_redirect( wp_login_url() );
		exit;
	}

	// 自动注册
	$nickname   = isset( $result['nickname'] ) && is_string( $result['nickname'] ) && '' !== $result['nickname'] ? $result['nickname'] : (string) current_time( 'timestamp' );
	$user_login = 'echo_' . substr( md5( $type . $openid ), 0, 12 );
	$meta_input = array( $meta_key => $openid );
	if ( '' !== $avatar ) {
		$meta_input['clogin_passport_avatar'] = set_url_scheme( esc_url_raw( $avatar ) );
	}
	$user_id = wp_insert_user(
		array(
			'user_login'    => $user_login,
			'user_pass'     => wp_generate_password(),
			'user_nicename' => sanitize_title( $nickname ),
			'nickname'      => sanitize_text_field( $nickname ),
			'display_name'  => sanitize_text_field( $nickname ),
			'meta_input'    => $meta_input,
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'echo_social_register', $user_id->get_error_message(), array( 'status' => 500 ) );
	}
	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		return new WP_Error( 'echo_social_register', __( '自动注册失败。', 'echo' ), array( 'status' => 500 ) );
	}
	echo_social_do_login( $user );
}

/**
 * 当前登录用户 ID（REST 回调无 nonce，用 cookie 校验）
 *
 * @return int|false
 */
function echo_social_current_user_id() {
	if ( ! isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) || ! is_string( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
		return false;
	}
	return wp_validate_auth_cookie( '', 'logged_in' );
}

/**
 * 按 openid 查找用户
 *
 * @param string $type   平台。
 * @param string $openid 第三方 UID。
 * @return WP_User|false
 */
function echo_social_find_user( $type, $openid ) {
	$args = array(
		'meta_key'     => 'clogin_passport_' . $type,
		'meta_value'   => $openid,
		'meta_compare' => '=',
		'number'       => 1,
	);
	if ( is_multisite() ) {
		$args['blog_id'] = 0;
	}
	$users = get_users( $args );
	if ( empty( $users ) ) {
		return false;
	}
	$user = $users[0];
	if ( is_multisite() ) {
		$blog_id = get_current_blog_id();
		if ( ! is_user_member_of_blog( $user->ID, $blog_id ) ) {
			$role = in_array( 'administrator', $user->roles, true ) ? 'administrator' : 'subscriber';
			add_user_to_blog( $blog_id, $user->ID, $role );
		}
	}
	return $user;
}

/**
 * 删除其他账号上重复的 openid
 *
 * @param int    $user_id  当前用户。
 * @param string $meta_key 元数据键。
 * @param string $openid   第三方 UID。
 * @return void
 */
function echo_social_delete_duplicate_meta( $user_id, $meta_key, $openid ) {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s AND user_id <> %d",
			$meta_key,
			$openid,
			$user_id
		)
	);
	foreach ( $ids as $meta_id ) {
		delete_metadata_by_mid( 'user', $meta_id );
	}
}

/**
 * 执行登录
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_social_do_login( $user ) {
	wp_set_current_user( $user->ID, $user->user_login );
	wp_set_auth_cookie( $user->ID, true );
	do_action( 'wp_login', $user->user_login, $user );
	echo_social_redirect_after_login();
}

/* ============================================================
 * 头像
 * ============================================================ */

/**
 * 从用户标识解析 UID
 *
 * @param mixed $id_or_email 用户 ID / 邮箱 / WP_User / WP_Comment。
 * @return int
 */
function echo_social_avatar_uid( $id_or_email ) {
	if ( is_numeric( $id_or_email ) ) {
		return absint( $id_or_email );
	}
	if ( $id_or_email instanceof WP_User ) {
		return (int) $id_or_email->ID;
	}
	if ( $id_or_email instanceof WP_Comment ) {
		return absint( $id_or_email->user_id );
	}
	if ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
		return absint( $id_or_email->user_id );
	}
	if ( is_string( $id_or_email ) && false === strpos( $id_or_email, '@md5.gravatar.com' ) ) {
		$by_email = get_user_by( 'email', $id_or_email );
		if ( $by_email ) {
			return (int) $by_email->ID;
		}
	}
	return 0;
}

/**
 * 社交头像：自定义头像与 Gravatar 之间的中间层（优先级 5，低于自定义的 10）
 *
 * @param array $args        头像参数。
 * @param mixed $id_or_email 用户标识。
 * @return array
 */
function echo_social_avatar_filter( $args, $id_or_email ) {
	if ( ! echo_social_config( 'social_avatar', true ) ) {
		return $args;
	}

	$uid = echo_social_avatar_uid( $id_or_email );
	if ( ! $uid ) {
		return $args;
	}

	if ( function_exists( 'echo_user_avatar_data' ) && echo_user_avatar_data( $uid ) ) {
		return $args;
	}

	$social = get_user_meta( $uid, 'clogin_passport_avatar', true );
	$social = is_string( $social ) ? trim( $social ) : '';
	if ( '' === $social || ! preg_match( '#^https?://#i', $social ) ) {
		return $args;
	}

	$args['url']          = set_url_scheme( esc_url_raw( $social ) );
	$args['found_avatar'] = true;
	return $args;
}
add_filter( 'pre_get_avatar_data', 'echo_social_avatar_filter', 5, 2 );

/**
 * 编辑区显示当前生效的社交头像
 *
 * @param array|null $data 自定义头像数据。
 * @param int        $uid  用户 ID。
 * @return array|null
 */
function echo_social_effective_avatar( $data, $uid ) {
	if ( $data || ! echo_social_config( 'social_avatar', true ) ) {
		return $data;
	}

	$social = get_user_meta( $uid, 'clogin_passport_avatar', true );
	$social = is_string( $social ) ? trim( $social ) : '';
	if ( '' === $social || ! preg_match( '#^https?://#i', $social ) ) {
		return null;
	}

	return array(
		'url' => set_url_scheme( esc_url_raw( $social ) ),
		'v'   => 'social',
	);
}

/* ============================================================
 * 设置清洗
 * ============================================================ */

/**
 * 清洗设置
 *
 * @param array $input  提交数据。
 * @param array $output 已清洗输出（引用）。
 * @return void
 */
function echo_sanitize_social_settings( $input, &$output ) {
	$defaults = echo_social_defaults();

	$has_social = false;
	foreach ( array_keys( $defaults ) as $social_key ) {
		if ( array_key_exists( $social_key, $input ) ) {
			$has_social = true;
			break;
		}
	}

	// 旧备份不含这些字段时沿用当前值
	if ( ! $has_social ) {
		foreach ( $defaults as $social_key => $social_value ) {
			$output[ $social_key ] = echo_get_setting( $social_key, $social_value );
		}
		return;
	}

	$output['social_login']   = isset( $input['social_login'] ) && '1' === (string) $input['social_login'];
	$output['social_binding'] = isset( $input['social_binding'] ) && '1' === (string) $input['social_binding'];
	$output['social_avatar']  = isset( $input['social_avatar'] ) && '1' === (string) $input['social_avatar'];
	$output['social_autoreg'] = isset( $input['social_autoreg'] ) && '1' === (string) $input['social_autoreg'];

	$title = isset( $input['social_title'] ) ? sanitize_text_field( $input['social_title'] ) : '';
	$output['social_title'] = '' !== $title ? $title : $defaults['social_title'];

	$appurl = isset( $input['social_appurl'] ) ? trim( sanitize_text_field( $input['social_appurl'] ) ) : '';
	if ( '' !== $appurl && ! preg_match( '#^https?://#i', $appurl ) ) {
		$appurl = '';
	}
	$output['social_appurl'] = rtrim( $appurl, '/' );
	$output['social_appid']  = isset( $input['social_appid'] ) ? sanitize_text_field( $input['social_appid'] ) : '';
	$output['social_appkey'] = isset( $input['social_appkey'] ) ? sanitize_text_field( $input['social_appkey'] ) : '';

	$types   = isset( $input['social_types'] ) && is_array( $input['social_types'] ) ? $input['social_types'] : array();
	$allowed = echo_social_platforms();
	$clean   = array();
	foreach ( $types as $type ) {
		if ( is_string( $type ) && isset( $allowed[ $type ] ) ) {
			$clean[] = $type;
		}
	}
	$output['social_types'] = array_values( array_unique( $clean ) );
}
