<?php
/**
 * 登录 / 注册 / 找回密码（单页三态）
 *
 * 同一页面通过 ?view=login|register|lostpassword|reset 切换：
 * - 登录：wp_signon()
 * - 注册：wp_insert_user()（自设密码）或 register_new_user()
 * - 找回密码：retrieve_password()，并把邮件里的重置链接改写回本页
 * - 重置密码：check_password_reset_key() + reset_password()
 *
 * 所有选项存入 echo_settings，走主表单保存。
 * 安全：nonce + 蜜罐 + 按 IP 频率限制 + 防开放跳转。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 默认设置
 *
 * @return array
 */
function echo_auth_defaults() {
	return array(
		'auth_enabled'            => true,
		'auth_login_slug'         => 'login',
		// 保留旧键以兼容历史配置；单页模式下不再使用独立注册页别名。
		'auth_register_slug'      => 'register',
		'auth_show_nav'           => true,
		'auth_nav_login_label'    => '登录',
		'auth_nav_register_label' => '注册',
		'auth_show_tabs'          => true,
		'auth_allow_register'     => true,
		'auth_register_password'  => true,
		'auth_password_min'       => 6,
		'auth_auto_login'         => true,
		'auth_redirect_logged_in' => true,
		'auth_after_login'        => 'my',
		'auth_after_login_url'    => '',
		'auth_show_remember'      => true,
		'auth_show_lostpassword'  => true,
		'auth_terms_enabled'      => false,
		'auth_terms_text'         => '',
		'auth_login_title'        => '登录',
		'auth_register_title'     => '注册',
		'auth_lostpassword_title' => '找回密码',
		'auth_welcome'            => '',
		'auth_shortcode'          => '',
		'auth_layout'             => 'auto',
		'auth_show_sidebar'       => false,
		'auth_custom_css'         => '',
		'auth_throttle'           => true,
	);
}

/**
 * 读取一项或全部设置
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_auth_config( $key = null, $default = null ) {
	$settings = echo_get_settings();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 是否允许前台注册
 *
 * @return bool
 */
function echo_auth_registration_allowed() {
	return (bool) echo_auth_config( 'auth_allow_register', true ) && (bool) get_option( 'users_can_register' );
}

/**
 * 认证页的实际固定链接（页面不存在时返回空串）
 *
 * @return string
 */
function echo_auth_page_permalink() {
	$slug = sanitize_title( echo_auth_config( 'auth_login_slug', 'login' ) );
	if ( '' === $slug ) {
		$slug = 'login';
	}
	$page = get_page_by_path( $slug );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}
	return '';
}

/**
 * 各视图的地址
 *
 * 页面存在时统一指回同一页并带 ?view=；不存在时回退到 WordPress 原生地址。
 *
 * @param string $view login|register|lostpassword|reset。
 * @return string
 */
function echo_auth_url( $view = 'login' ) {
	$valid = array( 'login', 'register', 'lostpassword', 'reset' );
	$view  = in_array( $view, $valid, true ) ? $view : 'login';

	$base = echo_auth_page_permalink();
	if ( '' !== $base ) {
		if ( 'login' === $view ) {
			return $base;
		}
		return add_query_arg( 'view', $view, $base );
	}

	if ( 'register' === $view ) {
		return get_option( 'users_can_register' ) ? wp_registration_url() : wp_login_url();
	}
	if ( 'lostpassword' === $view ) {
		return wp_lostpassword_url();
	}
	return wp_login_url();
}

/**
 * 当前视图
 *
 * @param string $default 没有 ?view= 时的默认视图。
 * @return string
 */
function echo_auth_current_view( $default = 'login' ) {
	$valid   = array( 'login', 'register', 'lostpassword', 'reset' );
	$default = in_array( $default, $valid, true ) ? $default : 'login';

	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
	if ( ! in_array( $view, $valid, true ) ) {
		$view = $default;
	}

	if ( 'register' === $view && ! echo_auth_registration_allowed() ) {
		$view = 'login';
	}
	if ( in_array( $view, array( 'lostpassword', 'reset' ), true ) && ! echo_auth_config( 'auth_show_lostpassword', true ) ) {
		$view = 'login';
	}
	return $view;
}

/**
 * 登录后的跳转地址
 *
 * @return string
 */
function echo_auth_after_login_url() {
	$choice = echo_auth_config( 'auth_after_login', 'my' );

	switch ( $choice ) {
		case 'home':
			$url = home_url( '/' );
			break;
		case 'custom':
			$url = esc_url_raw( (string) echo_auth_config( 'auth_after_login_url', '' ) );
			break;
		case 'my':
		default:
			$url = function_exists( 'echo_my_page_url' ) ? echo_my_page_url() : home_url( '/' );
			break;
	}

	if ( '' === $url ) {
		$url = home_url( '/' );
	}
	return wp_validate_redirect( $url, home_url( '/' ) );
}

/**
 * 频率限制键
 *
 * @param string $action 动作。
 * @return string
 */
function echo_auth_throttle_key( $action ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	return 'echo_auth_rl_' . $action . '_' . md5( $ip );
}

/**
 * 是否已被限流
 *
 * @param string $action 动作。
 * @return bool
 */
function echo_auth_is_throttled( $action ) {
	$data   = get_transient( echo_auth_throttle_key( $action ) );
	$limits = array(
		'login'        => 12,
		'register'     => 6,
		'lostpassword' => 8,
		'reset'        => 8,
	);
	$limit = isset( $limits[ $action ] ) ? $limits[ $action ] : 10;
	return is_array( $data ) && isset( $data['count'] ) && (int) $data['count'] >= $limit;
}

/**
 * 记录一次
 *
 * @param string $action 动作。
 * @return void
 */
function echo_auth_throttle_hit( $action ) {
	$key   = echo_auth_throttle_key( $action );
	$data  = get_transient( $key );
	$count = ( is_array( $data ) && isset( $data['count'] ) ) ? (int) $data['count'] : 0;
	set_transient( $key, array( 'count' => $count + 1 ), 15 * MINUTE_IN_SECONDS );
}

/**
 * 清除计数
 *
 * @param string $action 动作。
 * @return void
 */
function echo_auth_throttle_clear( $action ) {
	delete_transient( echo_auth_throttle_key( $action ) );
}

/**
 * 写入反馈
 *
 * @param string $type error|notice。
 * @param string $text 文案。
 * @return void
 */
function echo_auth_set_feedback( $type, $text ) {
	if ( ! isset( $GLOBALS['echo_auth_feedback'] ) || ! is_array( $GLOBALS['echo_auth_feedback'] ) ) {
		$GLOBALS['echo_auth_feedback'] = array(
			'error'  => '',
			'notice' => '',
		);
	}
	$GLOBALS['echo_auth_feedback'][ $type ] = $text;
}

/**
 * 重置密码表单的 key / login（先看 POST，再看 GET）
 *
 * @return array
 */
function echo_auth_reset_credentials() {
	$key   = '';
	$login = '';

	if ( isset( $_POST['key'] ) ) {
		$key = sanitize_text_field( wp_unslash( $_POST['key'] ) );
	} elseif ( isset( $_GET['key'] ) ) {
		$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
	}

	if ( isset( $_POST['login'] ) ) {
		$login = sanitize_text_field( wp_unslash( $_POST['login'] ) );
	} elseif ( isset( $_GET['login'] ) ) {
		$login = sanitize_text_field( wp_unslash( $_GET['login'] ) );
	}

	return array( $key, $login );
}

/**
 * 处理提交（在输出前调用）
 *
 * @return void
 */
function echo_auth_handle_request() {
	if ( ! echo_auth_config( 'auth_enabled', true ) ) {
		return;
	}

	if ( ! isset( $GLOBALS['echo_auth_feedback'] ) || ! is_array( $GLOBALS['echo_auth_feedback'] ) ) {
		$GLOBALS['echo_auth_feedback'] = array(
			'error'  => '',
			'notice' => '',
		);
	}

	// 跳转回来的提示
	if ( isset( $_GET['registered'] ) ) {
		echo_auth_set_feedback( 'notice', __( '注册成功，请使用新账号登录。', 'echo' ) );
	}
	if ( isset( $_GET['reset'] ) ) {
		echo_auth_set_feedback( 'notice', __( '密码已更新，请用新密码登录。', 'echo' ) );
	}
	if ( isset( $_GET['sent'] ) ) {
		echo_auth_set_feedback( 'notice', __( '如果该账号存在，重置链接已发送到注册邮箱，请查收（含垃圾箱）。', 'echo' ) );
	}

	if ( empty( $_POST['echo_auth_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['echo_auth_action'] ) );
	if ( ! in_array( $action, array( 'login', 'register', 'lostpassword', 'reset' ), true ) ) {
		return;
	}

	if ( ! isset( $_POST['echo_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['echo_auth_nonce'] ) ), 'echo_auth' ) ) {
		echo_auth_set_feedback( 'error', __( '页面已过期，请刷新后重试。', 'echo' ) );
		return;
	}

	if ( ! empty( $_POST['echo_auth_hp'] ) ) {
		echo_auth_set_feedback( 'error', __( '提交失败，请稍后重试。', 'echo' ) );
		return;
	}

	switch ( $action ) {
		case 'login':
			echo_auth_do_login();
			break;
		case 'register':
			echo_auth_do_register();
			break;
		case 'lostpassword':
			echo_auth_do_lostpassword();
			break;
		case 'reset':
			echo_auth_do_reset();
			break;
	}
}

/**
 * 登录
 *
 * @return void
 */
function echo_auth_do_login() {
	$throttle = (bool) echo_auth_config( 'auth_throttle', true );
	if ( $throttle && echo_auth_is_throttled( 'login' ) ) {
		echo_auth_set_feedback( 'error', __( '尝试次数过多，请 15 分钟后再试。', 'echo' ) );
		return;
	}

	$login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
	$password = isset( $_POST['pwd'] ) ? (string) $_POST['pwd'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- 密码不能清洗。
	$remember = ! empty( $_POST['rememberme'] );

	if ( '' === $login || '' === $password ) {
		echo_auth_set_feedback( 'error', __( '请填写用户名和密码。', 'echo' ) );
		return;
	}

	$user = wp_signon(
		array(
			'user_login'    => $login,
			'user_password' => $password,
			'remember'      => $remember,
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		if ( $throttle ) {
			echo_auth_throttle_hit( 'login' );
		}
		$code = $user->get_error_code();
		if ( in_array( $code, array( 'invalid_username', 'incorrect_password', 'invalid_email', 'invalidcombo' ), true ) ) {
			echo_auth_set_feedback( 'error', __( '用户名或密码不正确。', 'echo' ) );
		} else {
			echo_auth_set_feedback( 'error', wp_strip_all_tags( $user->get_error_message() ) );
		}
		return;
	}

	if ( $throttle ) {
		echo_auth_throttle_clear( 'login' );
	}

	wp_set_current_user( $user->ID );

	$redirect = echo_auth_after_login_url();
	if ( ! empty( $_POST['redirect_to'] ) ) {
		$candidate = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), '' );
		if ( '' !== $candidate ) {
			$redirect = $candidate;
		}
	}

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * 注册
 *
 * @return void
 */
function echo_auth_do_register() {
	if ( ! echo_auth_registration_allowed() ) {
		echo_auth_set_feedback( 'error', __( '本站暂未开放注册。', 'echo' ) );
		return;
	}

	$throttle = (bool) echo_auth_config( 'auth_throttle', true );
	if ( $throttle && echo_auth_is_throttled( 'register' ) ) {
		echo_auth_set_feedback( 'error', __( '操作过于频繁，请 15 分钟后再试。', 'echo' ) );
		return;
	}

	// 非 strict：保留中文用户名。
	$login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
	$email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';

	if ( '' === $login || '' === $email ) {
		echo_auth_set_feedback( 'error', __( '请填写用户名和邮箱。', 'echo' ) );
		return;
	}
	if ( ! validate_username( $login ) ) {
		echo_auth_set_feedback( 'error', __( '用户名包含非法字符，请更换。', 'echo' ) );
		return;
	}
	if ( username_exists( $login ) ) {
		echo_auth_set_feedback( 'error', __( '该用户名已被占用。', 'echo' ) );
		return;
	}
	if ( ! is_email( $email ) ) {
		echo_auth_set_feedback( 'error', __( '邮箱格式不正确。', 'echo' ) );
		return;
	}
	if ( email_exists( $email ) ) {
		echo_auth_set_feedback( 'error', __( '该邮箱已注册。', 'echo' ) );
		return;
	}
	if ( echo_auth_config( 'auth_terms_enabled', false ) && empty( $_POST['echo_auth_terms'] ) ) {
		echo_auth_set_feedback( 'error', __( '请先阅读并同意相关条款。', 'echo' ) );
		return;
	}

	$set_password = (bool) echo_auth_config( 'auth_register_password', true );

	if ( ! $set_password ) {
		// WordPress 生成随机密码并邮件发送
		$user_id = register_new_user( $login, $email );
		if ( is_wp_error( $user_id ) ) {
			if ( $throttle ) {
				echo_auth_throttle_hit( 'register' );
			}
			echo_auth_set_feedback( 'error', wp_strip_all_tags( $user_id->get_error_message() ) );
			return;
		}
		if ( $throttle ) {
			echo_auth_throttle_clear( 'register' );
		}
		wp_safe_redirect( add_query_arg( 'registered', '1', echo_auth_url( 'login' ) ) );
		exit;
	}

	$password = isset( $_POST['user_pass'] ) ? (string) $_POST['user_pass'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$confirm  = isset( $_POST['user_pass2'] ) ? (string) $_POST['user_pass2'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$min      = max( 6, min( 32, absint( echo_auth_config( 'auth_password_min', 6 ) ) ) );

	if ( '' === $password || '' === $confirm ) {
		echo_auth_set_feedback( 'error', __( '请设置并确认密码。', 'echo' ) );
		return;
	}
	if ( $password !== $confirm ) {
		echo_auth_set_feedback( 'error', __( '两次输入的密码不一致。', 'echo' ) );
		return;
	}
	if ( strlen( $password ) < $min ) {
		echo_auth_set_feedback( 'error', sprintf( /* translators: %d: 最小密码长度 */ __( '密码至少需要 %d 位。', 'echo' ), $min ) );
		return;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $login,
			'role'         => get_option( 'default_role' ),
		)
	);

	if ( is_wp_error( $user_id ) ) {
		if ( $throttle ) {
			echo_auth_throttle_hit( 'register' );
		}
		echo_auth_set_feedback( 'error', wp_strip_all_tags( $user_id->get_error_message() ) );
		return;
	}

	wp_new_user_notification( $user_id, null, 'admin' );

	if ( $throttle ) {
		echo_auth_throttle_clear( 'register' );
	}

	if ( echo_auth_config( 'auth_auto_login', true ) ) {
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		wp_safe_redirect( echo_auth_after_login_url() );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'registered', '1', echo_auth_url( 'login' ) ) );
	exit;
}

/**
 * 找回密码：发送重置邮件
 *
 * @return void
 */
function echo_auth_do_lostpassword() {
	$throttle = (bool) echo_auth_config( 'auth_throttle', true );
	if ( $throttle && echo_auth_is_throttled( 'lostpassword' ) ) {
		echo_auth_set_feedback( 'error', __( '操作过于频繁，请 15 分钟后再试。', 'echo' ) );
		return;
	}

	$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
	if ( '' === $login ) {
		echo_auth_set_feedback( 'error', __( '请填写用户名或邮箱。', 'echo' ) );
		return;
	}

	// 防止邮件轰炸：每次请求都计数。
	if ( $throttle ) {
		echo_auth_throttle_hit( 'lostpassword' );
	}

	$result = retrieve_password( $login );

	// 无论账号是否存在都跳回同一提示，避免枚举账号。
	if ( is_wp_error( $result ) && 'retrieve_password_email_failure' === $result->get_error_code() ) {
		echo_auth_set_feedback( 'error', __( '邮件发送失败，请联系管理员。', 'echo' ) );
		return;
	}

	wp_safe_redirect( add_query_arg( 'sent', '1', echo_auth_url( 'lostpassword' ) ) );
	exit;
}

/**
 * 重置密码：校验 key 并写入新密码
 *
 * @return void
 */
function echo_auth_do_reset() {
	$throttle = (bool) echo_auth_config( 'auth_throttle', true );
	if ( $throttle && echo_auth_is_throttled( 'reset' ) ) {
		echo_auth_set_feedback( 'error', __( '操作过于频繁，请 15 分钟后再试。', 'echo' ) );
		return;
	}

	list( $key, $login ) = echo_auth_reset_credentials();

	if ( '' === $key || '' === $login ) {
		echo_auth_set_feedback( 'error', __( '重置链接不完整，请重新申请。', 'echo' ) );
		return;
	}

	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) ) {
		if ( $throttle ) {
			echo_auth_throttle_hit( 'reset' );
		}
		echo_auth_set_feedback( 'error', __( '重置链接无效或已过期，请重新申请。', 'echo' ) );
		return;
	}

	$password = isset( $_POST['user_pass'] ) ? (string) $_POST['user_pass'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$confirm  = isset( $_POST['user_pass2'] ) ? (string) $_POST['user_pass2'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$min      = max( 6, min( 32, absint( echo_auth_config( 'auth_password_min', 6 ) ) ) );

	if ( '' === $password || '' === $confirm ) {
		echo_auth_set_feedback( 'error', __( '请填写并确认新密码。', 'echo' ) );
		return;
	}
	if ( $password !== $confirm ) {
		echo_auth_set_feedback( 'error', __( '两次输入的密码不一致。', 'echo' ) );
		return;
	}
	if ( strlen( $password ) < $min ) {
		echo_auth_set_feedback( 'error', sprintf( /* translators: %d: 最小密码长度 */ __( '密码至少需要 %d 位。', 'echo' ), $min ) );
		return;
	}

	reset_password( $user, $password );

	if ( $throttle ) {
		echo_auth_throttle_clear( 'reset' );
	}

	wp_safe_redirect( add_query_arg( 'reset', '1', echo_auth_url( 'login' ) ) );
	exit;
}

/**
 * 把重置邮件里的 wp-login 链接改写回本页
 *
 * @param string $message    邮件正文。
 * @param string $key        重置 key。
 * @param string $user_login 用户名。
 * @return string
 */
function echo_auth_rewrite_reset_link( $message, $key, $user_login ) {
	$base = echo_auth_page_permalink();
	if ( '' === $base ) {
		return $message;
	}

	$url = add_query_arg(
		array(
			'view'  => 'reset',
			'key'   => $key,
			'login' => rawurlencode( $user_login ),
		),
		$base
	);

	$pattern  = '#https?://[^\s<>]*wp-login\.php\?action=rp[^\s<>]*#i';
	$replaced = preg_replace( $pattern, $url, $message );

	return ( null === $replaced || '' === $replaced ) ? $message : $replaced;
}
add_filter( 'retrieve_password_message', 'echo_auth_rewrite_reset_link', 10, 3 );

/**
 * 已登录用户访问认证页时跳走
 *
 * @return void
 */
function echo_auth_redirect_logged_in() {
	if ( ! echo_auth_config( 'auth_enabled', true ) || ! echo_auth_config( 'auth_redirect_logged_in', true ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( ! is_page_template( array( 'template-auth.php', 'template-login.php', 'template-register.php' ) ) ) {
		return;
	}

	$target = echo_auth_after_login_url();
	if ( $target && $target !== get_permalink() ) {
		wp_safe_redirect( $target );
		exit;
	}
}
add_action( 'template_redirect', 'echo_auth_redirect_logged_in' );

/**
 * 输出反馈
 *
 * @return void
 */
function echo_auth_render_feedback() {
	$fb = isset( $GLOBALS['echo_auth_feedback'] ) && is_array( $GLOBALS['echo_auth_feedback'] )
		? $GLOBALS['echo_auth_feedback']
		: array( 'error' => '', 'notice' => '' );

	if ( ! empty( $fb['error'] ) ) {
		printf( '<div class="echo-auth__alert echo-auth__alert--error" role="alert">%s</div>', esc_html( $fb['error'] ) );
	}
	if ( ! empty( $fb['notice'] ) ) {
		printf( '<div class="echo-auth__alert echo-auth__alert--success" role="status">%s</div>', esc_html( $fb['notice'] ) );
	}
}

/**
 * 登录 / 注册两个标签页
 *
 * 找回密码不占标签位，作为登录表单里的「忘记密码？」链接进入独立视图。
 *
 * @param string $view 当前视图。
 * @return void
 */
function echo_auth_render_tabs( $view ) {
	if ( ! echo_auth_config( 'auth_show_tabs', true ) || in_array( $view, array( 'reset', 'lostpassword' ), true ) ) {
		return;
	}

	$items = array(
		'login' => array(
			'label' => echo_auth_config( 'auth_login_title', __( '登录', 'echo' ) ),
			'url'   => echo_auth_url( 'login' ),
		),
	);
	if ( echo_auth_registration_allowed() ) {
		$items['register'] = array(
			'label' => echo_auth_config( 'auth_register_title', __( '注册', 'echo' ) ),
			'url'   => echo_auth_url( 'register' ),
		);
	}

	if ( count( $items ) < 2 ) {
		return;
	}
	?>
	<nav class="echo-auth__tabs" aria-label="<?php esc_attr_e( '登录与注册', 'echo' ); ?>">
		<?php foreach ( $items as $key => $item ) : ?>
			<a class="echo-auth__tab<?php echo $key === $view ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( $item['url'] ); ?>"
				<?php echo $key === $view ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $item['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * 密码显隐按钮
 *
 * @return void
 */
function echo_auth_eye_button() {
	?>
	<button type="button" class="echo-auth__eye" aria-label="<?php esc_attr_e( '显示或隐藏密码', 'echo' ); ?>" aria-pressed="false">
		<svg class="echo-auth__eye-on" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
			<circle cx="12" cy="12" r="3"></circle>
		</svg>
		<svg class="echo-auth__eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
			<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
			<line x1="1" y1="1" x2="23" y2="23"></line>
		</svg>
	</button>
	<?php
}

/**
 * 登录表单
 *
 * @return void
 */
function echo_auth_form_login() {
	$remember = (bool) echo_auth_config( 'auth_show_remember', true );
	?>
	<form class="echo-auth__form" method="post" action="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>" novalidate>
		<?php wp_nonce_field( 'echo_auth', 'echo_auth_nonce' ); ?>
		<input type="hidden" name="echo_auth_action" value="login" />
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( echo_auth_after_login_url() ); ?>" />

		<p class="echo-auth__field">
			<label for="echo-auth-login"><?php esc_html_e( '用户名或邮箱', 'echo' ); ?></label>
			<input id="echo-auth-login" name="log" type="text" autocomplete="username" required
				value="<?php echo isset( $_POST['log'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['log'] ) ) ) : ''; ?>" />
		</p>

		<p class="echo-auth__field">
			<label for="echo-auth-pwd"><?php esc_html_e( '密码', 'echo' ); ?></label>
			<span class="echo-auth__pwd-wrap">
				<input id="echo-auth-pwd" name="pwd" type="password" autocomplete="current-password" required />
				<?php echo_auth_eye_button(); ?>
			</span>
		</p>

		<div class="echo-auth__row">
			<?php if ( $remember ) : ?>
				<label class="echo-auth__check">
					<input type="checkbox" name="rememberme" value="forever" />
					<span><?php esc_html_e( '记住我', 'echo' ); ?></span>
				</label>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
			<?php if ( echo_auth_config( 'auth_show_lostpassword', true ) ) : ?>
				<a class="echo-auth__link" href="<?php echo esc_url( echo_auth_url( 'lostpassword' ) ); ?>"><?php esc_html_e( '忘记密码？', 'echo' ); ?></a>
			<?php endif; ?>
		</div>

		<input class="echo-auth__hp" type="text" name="echo_auth_hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

		<button type="submit" class="echo-auth__submit"><?php echo esc_html( echo_auth_config( 'auth_login_title', __( '登录', 'echo' ) ) ); ?></button>
	</form>
	<?php
}

/**
 * 注册表单
 *
 * @return void
 */
function echo_auth_form_register() {
	$set_password = (bool) echo_auth_config( 'auth_register_password', true );
	$min          = max( 6, min( 32, absint( echo_auth_config( 'auth_password_min', 6 ) ) ) );
	?>
	<form class="echo-auth__form" method="post" action="<?php echo esc_url( echo_auth_url( 'register' ) ); ?>" novalidate>
		<?php wp_nonce_field( 'echo_auth', 'echo_auth_nonce' ); ?>
		<input type="hidden" name="echo_auth_action" value="register" />

		<p class="echo-auth__field">
			<label for="echo-auth-user"><?php esc_html_e( '用户名', 'echo' ); ?></label>
			<input id="echo-auth-user" name="user_login" type="text" autocomplete="username" required
				value="<?php echo isset( $_POST['user_login'] ) ? esc_attr( sanitize_user( wp_unslash( $_POST['user_login'] ) ) ) : ''; ?>" />
			<span class="echo-auth__hint"><?php esc_html_e( '仅限字母、数字、下划线等，注册后不可修改。', 'echo' ); ?></span>
		</p>

		<p class="echo-auth__field">
			<label for="echo-auth-email"><?php esc_html_e( '邮箱', 'echo' ); ?></label>
			<input id="echo-auth-email" name="user_email" type="email" autocomplete="email" required
				value="<?php echo isset( $_POST['user_email'] ) ? esc_attr( sanitize_email( wp_unslash( $_POST['user_email'] ) ) ) : ''; ?>" />
		</p>

		<?php if ( $set_password ) : ?>
			<p class="echo-auth__field">
				<label for="echo-auth-pass"><?php esc_html_e( '设置密码', 'echo' ); ?></label>
				<span class="echo-auth__pwd-wrap">
					<input id="echo-auth-pass" name="user_pass" type="password" autocomplete="new-password" required />
					<?php echo_auth_eye_button(); ?>
				</span>
				<span class="echo-auth__hint"><?php echo esc_html( sprintf( /* translators: %d: 最小密码长度 */ __( '至少 %d 位字符。', 'echo' ), $min ) ); ?></span>
			</p>

			<p class="echo-auth__field">
				<label for="echo-auth-pass2"><?php esc_html_e( '确认密码', 'echo' ); ?></label>
				<span class="echo-auth__pwd-wrap">
					<input id="echo-auth-pass2" name="user_pass2" type="password" autocomplete="new-password" required />
					<?php echo_auth_eye_button(); ?>
				</span>
			</p>
		<?php else : ?>
			<p class="echo-auth__hint echo-auth__hint--block"><?php esc_html_e( '注册后系统会把随机密码发送到你的邮箱。', 'echo' ); ?></p>
		<?php endif; ?>

		<?php if ( echo_auth_config( 'auth_terms_enabled', false ) ) : ?>
			<label class="echo-auth__check echo-auth__check--terms">
				<input type="checkbox" name="echo_auth_terms" value="1" required />
				<span><?php echo wp_kses_post( (string) echo_auth_config( 'auth_terms_text', __( '我已阅读并同意相关条款。', 'echo' ) ) ); ?></span>
			</label>
		<?php endif; ?>

		<input class="echo-auth__hp" type="text" name="echo_auth_hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

		<button type="submit" class="echo-auth__submit"><?php echo esc_html( echo_auth_config( 'auth_register_title', __( '注册', 'echo' ) ) ); ?></button>
	</form>
	<?php
}

/**
 * 找回密码表单
 *
 * @return void
 */
function echo_auth_form_lostpassword() {
	?>
	<form class="echo-auth__form" method="post" action="<?php echo esc_url( echo_auth_url( 'lostpassword' ) ); ?>" novalidate>
		<?php wp_nonce_field( 'echo_auth', 'echo_auth_nonce' ); ?>
		<input type="hidden" name="echo_auth_action" value="lostpassword" />

		<p class="echo-auth__field">
			<label for="echo-auth-lost"><?php esc_html_e( '用户名或邮箱', 'echo' ); ?></label>
			<input id="echo-auth-lost" name="user_login" type="text" autocomplete="username" required
				value="<?php echo isset( $_POST['user_login'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : ''; ?>" />
			<span class="echo-auth__hint"><?php esc_html_e( '我们会向该账号的注册邮箱发送重置链接。', 'echo' ); ?></span>
		</p>

		<input class="echo-auth__hp" type="text" name="echo_auth_hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

		<button type="submit" class="echo-auth__submit"><?php esc_html_e( '发送重置链接', 'echo' ); ?></button>
	</form>
	<?php
}

/**
 * 重置密码表单
 *
 * @param string $key   重置 key。
 * @param string $login 用户名。
 * @return void
 */
function echo_auth_form_reset( $key, $login ) {
	$min = max( 6, min( 32, absint( echo_auth_config( 'auth_password_min', 6 ) ) ) );
	?>
	<form class="echo-auth__form" method="post" action="<?php echo esc_url( echo_auth_url( 'reset' ) ); ?>" novalidate>
		<?php wp_nonce_field( 'echo_auth', 'echo_auth_nonce' ); ?>
		<input type="hidden" name="echo_auth_action" value="reset" />
		<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>" />
		<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>" />

		<p class="echo-auth__field">
			<label for="echo-auth-newpass"><?php esc_html_e( '新密码', 'echo' ); ?></label>
			<span class="echo-auth__pwd-wrap">
				<input id="echo-auth-newpass" name="user_pass" type="password" autocomplete="new-password" required />
				<?php echo_auth_eye_button(); ?>
			</span>
			<span class="echo-auth__hint"><?php echo esc_html( sprintf( /* translators: %d: 最小密码长度 */ __( '至少 %d 位字符。', 'echo' ), $min ) ); ?></span>
		</p>

		<p class="echo-auth__field">
			<label for="echo-auth-newpass2"><?php esc_html_e( '确认新密码', 'echo' ); ?></label>
			<span class="echo-auth__pwd-wrap">
				<input id="echo-auth-newpass2" name="user_pass2" type="password" autocomplete="new-password" required />
				<?php echo_auth_eye_button(); ?>
			</span>
		</p>

		<input class="echo-auth__hp" type="text" name="echo_auth_hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

		<button type="submit" class="echo-auth__submit"><?php esc_html_e( '保存新密码', 'echo' ); ?></button>
	</form>
	<?php
}

/**
 * 主渲染入口
 *
 * @param string $default_view 没有 ?view= 时的默认视图。
 * @return void
 */
function echo_auth_render( $default_view = 'login' ) {
	$view = echo_auth_current_view( $default_view );

	if ( ! echo_auth_config( 'auth_enabled', true ) ) {
		echo '<p class="echo-auth__notice">' . esc_html__( '登录 / 注册功能已关闭，请在「Echo 设置 → 登录 / 注册」中开启。', 'echo' ) . '</p>';
		return;
	}

	$layout = echo_auth_config( 'auth_layout', 'auto' );

	if ( 'register' === $view && ! echo_auth_registration_allowed() ) {
		?>
		<div class="echo-auth echo-auth--<?php echo esc_attr( $layout ); ?>">
			<div class="echo-auth__card">
				<h1 class="echo-auth__title"><?php esc_html_e( '注册已关闭', 'echo' ); ?></h1>
				<p class="echo-auth__desc"><?php esc_html_e( '本站暂未开放注册。如已有账号，可直接登录。', 'echo' ); ?></p>
				<p class="echo-auth__footer">
					<a class="echo-btn" href="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>"><?php esc_html_e( '前往登录', 'echo' ); ?></a>
				</p>
			</div>
		</div>
		<?php
		return;
	}

	// 标题
	switch ( $view ) {
		case 'register':
			$title = echo_auth_config( 'auth_register_title', __( '注册', 'echo' ) );
			break;
		case 'lostpassword':
			$title = echo_auth_config( 'auth_lostpassword_title', __( '找回密码', 'echo' ) );
			break;
		case 'reset':
			$title = __( '设置新密码', 'echo' );
			break;
		case 'login':
		default:
			$title = echo_auth_config( 'auth_login_title', __( '登录', 'echo' ) );
			break;
	}

	$welcome = (string) echo_auth_config( 'auth_welcome', '' );
	$short   = trim( (string) echo_auth_config( 'auth_shortcode', '' ) );
	?>
	<div class="echo-auth echo-auth--<?php echo esc_attr( $layout ); ?>">
		<div class="echo-auth__card">
			<h1 class="echo-auth__title"><?php echo esc_html( $title ); ?></h1>

			<?php if ( '' !== $welcome && in_array( $view, array( 'login', 'register' ), true ) ) : ?>
				<p class="echo-auth__desc"><?php echo esc_html( $welcome ); ?></p>
			<?php endif; ?>

			<?php
			echo_auth_render_tabs( $view );
			echo_auth_render_feedback();

			switch ( $view ) {
				case 'register':
					echo_auth_form_register();
					break;
				case 'lostpassword':
					echo_auth_form_lostpassword();
					break;
				case 'reset':
					list( $key, $login ) = echo_auth_reset_credentials();
					$reset_user          = ( '' !== $key && '' !== $login ) ? check_password_reset_key( $key, $login ) : null;

					if ( ! $reset_user || is_wp_error( $reset_user ) ) {
						echo '<p class="echo-auth__desc">' . esc_html__( '重置链接无效或已过期，请重新申请一封邮件。', 'echo' ) . '</p>';
						echo '<p class="echo-auth__footer"><a class="echo-btn" href="' . esc_url( echo_auth_url( 'lostpassword' ) ) . '">' . esc_html__( '重新申请', 'echo' ) . '</a></p>';
					} else {
						echo_auth_form_reset( $key, $login );
					}
					break;
				case 'login':
				default:
					echo_auth_form_login();
					break;
			}

			if ( '' !== $short && in_array( $view, array( 'login', 'register' ), true ) ) {
				echo '<div class="echo-auth__shortcode">' . do_shortcode( $short ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			if ( in_array( $view, array( 'login', 'register' ), true ) && function_exists( 'echo_social_login_buttons' ) ) {
				echo_social_login_buttons();
			}
			?>

			<p class="echo-auth__footer">
				<?php if ( 'register' === $view ) : ?>
					<?php esc_html_e( '已有账号？', 'echo' ); ?>
					<a class="echo-auth__link" href="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>"><?php esc_html_e( '直接登录', 'echo' ); ?></a>
				<?php elseif ( 'lostpassword' === $view ) : ?>
					<?php esc_html_e( '想起密码了？', 'echo' ); ?>
					<a class="echo-auth__link" href="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>"><?php esc_html_e( '返回登录', 'echo' ); ?></a>
				<?php elseif ( 'reset' === $view ) : ?>
					<a class="echo-auth__link" href="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>"><?php esc_html_e( '返回登录', 'echo' ); ?></a>
				<?php else : ?>
					<?php if ( echo_auth_registration_allowed() ) : ?>
						<?php esc_html_e( '还没有账号？', 'echo' ); ?>
						<a class="echo-auth__link" href="<?php echo esc_url( echo_auth_url( 'register' ) ); ?>"><?php esc_html_e( '立即注册', 'echo' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( 'login' === $view ) : ?>
					<span class="echo-auth__dot" aria-hidden="true">·</span>
					<a class="echo-auth__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'echo' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	</div>
	<?php
}

/**
 * 整页渲染（供各模板复用）
 *
 * @param string $default_view 默认视图。
 * @return void
 */
function echo_auth_render_page( $default_view = 'login' ) {
	echo_auth_handle_request();
	get_header();
	?>
	<main id="primary" class="echo-main">
		<div class="echo-container">
			<div class="echo-layout<?php echo echo_auth_config( 'auth_show_sidebar', false ) ? '' : ' echo-layout--full'; ?>">
				<div class="echo-content">
					<?php
					while ( have_posts() ) :
						the_post();
						?>
						<article id="post-<?php the_ID(); ?>" <?php post_class( 'echo-single echo-single--auth' ); ?>>
							<?php echo_auth_render( $default_view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</article>
						<?php
					endwhile;
					?>
				</div>

				<?php
				if ( echo_auth_config( 'auth_show_sidebar', false ) ) {
					get_sidebar();
				}
				?>
			</div>
		</div>
	</main>
	<?php
	get_footer();
}

/* ============================================================
 * 后台字段渲染
 * ============================================================ */

/**
 * 页面别名字段（附地址与页面状态）
 *
 * @param array $args 字段参数，需含 mode。
 * @return void
 */
function echo_field_auth_slug( $args ) {
	$mode = isset( $args['mode'] ) ? $args['mode'] : 'login';
	$key  = isset( $args['label_for'] ) ? $args['label_for'] : 'auth_login_slug';
	$slug = sanitize_title( echo_get_setting( $key, $mode ) );
	$page = get_page_by_path( $slug );
	?>
	<input type="text" id="<?php echo esc_attr( $key ); ?>" name="echo_settings[<?php echo esc_attr( $key ); ?>]"
		value="<?php echo esc_attr( $slug ); ?>" class="regular-text" />
	<p class="description">
		<?php esc_html_e( '页面地址：', 'echo' ); ?>
		<code><?php echo esc_html( home_url( '/' . $slug . '/' ) ); ?></code>
		<?php esc_html_e( '（登录 / 注册 / 找回密码共用此页，通过 ?view= 切换）', 'echo' ); ?>
	</p>
	<?php if ( $page ) : ?>
		<p class="description">
			<?php esc_html_e( '已找到同名页面。', 'echo' ); ?>
			<a href="<?php echo esc_url( get_edit_post_link( $page ) ); ?>"><?php esc_html_e( '编辑该页面', 'echo' ); ?></a>
			<?php esc_html_e( '，并在「页面属性 → 模板」中选择「登录 / 注册」。', 'echo' ); ?>
		</p>
	<?php else : ?>
		<p class="description echo-my-slug-warn">
			<?php esc_html_e( '尚未找到该别名的页面。请新建页面、把别名设为上面的值，并在「页面属性 → 模板」中选择「登录 / 注册」。', 'echo' ); ?>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( '新建页面', 'echo' ); ?></a>
		</p>
	<?php endif; ?>
	<?php if ( ! empty( $args['desc'] ) ) : ?>
		<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
	<?php endif;
}

/* ============================================================
 * 清洗
 * ============================================================ */

/**
 * 清洗设置
 *
 * @param array $input  原始提交。
 * @param array $output 已清洗的输出（按引用追加）。
 * @return void
 */
function echo_sanitize_auth_settings( $input, &$output ) {
	$defaults = echo_auth_defaults();

	$has_auth = false;
	foreach ( array_keys( $defaults ) as $auth_key ) {
		if ( array_key_exists( $auth_key, $input ) ) {
			$has_auth = true;
			break;
		}
	}
	if ( ! $has_auth ) {
		foreach ( $defaults as $auth_key => $auth_value ) {
			$output[ $auth_key ] = echo_get_setting( $auth_key, $auth_value );
		}
		return;
	}

	$bool_keys = array(
		'auth_enabled',
		'auth_show_nav',
		'auth_show_tabs',
		'auth_allow_register',
		'auth_register_password',
		'auth_auto_login',
		'auth_redirect_logged_in',
		'auth_show_remember',
		'auth_show_lostpassword',
		'auth_terms_enabled',
		'auth_show_sidebar',
		'auth_throttle',
	);
	foreach ( $bool_keys as $bool_key ) {
		$output[ $bool_key ] = isset( $input[ $bool_key ] ) && '1' === (string) $input[ $bool_key ];
	}

	$login_slug = isset( $input['auth_login_slug'] ) ? sanitize_title( $input['auth_login_slug'] ) : '';
	$output['auth_login_slug'] = '' !== $login_slug ? $login_slug : 'login';

	// 旧键保留（单页模式下不再使用），避免历史配置读取时缺键
	$reg_slug = isset( $input['auth_register_slug'] ) ? sanitize_title( $input['auth_register_slug'] ) : '';
	$output['auth_register_slug'] = '' !== $reg_slug ? $reg_slug : 'register';

	$output['auth_nav_login_label'] = isset( $input['auth_nav_login_label'] ) && '' !== trim( $input['auth_nav_login_label'] )
		? sanitize_text_field( $input['auth_nav_login_label'] )
		: $defaults['auth_nav_login_label'];
	$output['auth_nav_register_label'] = isset( $input['auth_nav_register_label'] ) && '' !== trim( $input['auth_nav_register_label'] )
		? sanitize_text_field( $input['auth_nav_register_label'] )
		: $defaults['auth_nav_register_label'];

	$output['auth_password_min'] = max( 6, min( 32, absint( isset( $input['auth_password_min'] ) ? $input['auth_password_min'] : $defaults['auth_password_min'] ) ) );

	$after = isset( $input['auth_after_login'] ) ? $input['auth_after_login'] : 'my';
	$output['auth_after_login'] = in_array( $after, array( 'my', 'home', 'custom' ), true ) ? $after : 'my';

	$output['auth_after_login_url'] = isset( $input['auth_after_login_url'] ) ? esc_url_raw( $input['auth_after_login_url'] ) : '';
	$output['auth_terms_text']      = isset( $input['auth_terms_text'] ) ? wp_kses_post( $input['auth_terms_text'] ) : '';

	$output['auth_login_title'] = isset( $input['auth_login_title'] ) && '' !== trim( $input['auth_login_title'] )
		? sanitize_text_field( $input['auth_login_title'] )
		: $defaults['auth_login_title'];
	$output['auth_register_title'] = isset( $input['auth_register_title'] ) && '' !== trim( $input['auth_register_title'] )
		? sanitize_text_field( $input['auth_register_title'] )
		: $defaults['auth_register_title'];
	$output['auth_lostpassword_title'] = isset( $input['auth_lostpassword_title'] ) && '' !== trim( $input['auth_lostpassword_title'] )
		? sanitize_text_field( $input['auth_lostpassword_title'] )
		: $defaults['auth_lostpassword_title'];

	$output['auth_welcome']   = isset( $input['auth_welcome'] ) ? sanitize_textarea_field( $input['auth_welcome'] ) : '';
	$output['auth_shortcode'] = isset( $input['auth_shortcode'] ) ? sanitize_text_field( $input['auth_shortcode'] ) : '';

	$layout = isset( $input['auth_layout'] ) ? $input['auth_layout'] : 'auto';
	$output['auth_layout'] = in_array( $layout, array( 'auto', 'classic', 'miuix' ), true ) ? $layout : 'auto';

	$custom_css = isset( $input['auth_custom_css'] ) ? wp_strip_all_tags( $input['auth_custom_css'] ) : '';
	$custom_css = preg_replace( '/<\s*\/?\s*style\b[^>]*>/i', '', $custom_css );
	$output['auth_custom_css'] = $custom_css;
}

/* ============================================================
 * 前台输出
 * ============================================================ */

/**
 * 登录 / 注册页的自定义 CSS
 *
 * @return string
 */
function echo_auth_css() {
	$custom = trim( (string) echo_auth_config( 'auth_custom_css', '' ) );
	return '' !== $custom ? $custom : '';
}

/**
 * 导航栏未登录时的入口
 *
 * @return void
 */
function echo_auth_nav_links() {
	if ( ! echo_auth_config( 'auth_enabled', true ) || ! echo_auth_config( 'auth_show_nav', true ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		return;
	}

	$login_label = echo_auth_config( 'auth_nav_login_label', __( '登录', 'echo' ) );
	$reg_label   = echo_auth_config( 'auth_nav_register_label', __( '注册', 'echo' ) );
	?>
	<a class="echo-auth-nav-link" href="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>"><?php echo esc_html( $login_label ); ?></a>
	<?php if ( echo_auth_registration_allowed() ) : ?>
		<a class="echo-auth-nav-link echo-auth-nav-link--primary" href="<?php echo esc_url( echo_auth_url( 'register' ) ); ?>"><?php echo esc_html( $reg_label ); ?></a>
	<?php endif;
}
