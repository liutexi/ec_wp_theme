<?php
/**
 * 登录 / 注册界面
 *
 * 与「我的」界面相同的组织方式：默认项、前台渲染、后台字段、清洗集中在
 * 本文件，所有选项统一存入 echo_settings，走主表单保存。
 *
 * 设计要点：
 * - 登录走 wp_signon()，注册走 wp_insert_user() / register_new_user()，
 *   完全复用 WordPress 自身的账号体系与钩子。
 * - 表单提交在本页处理（不跳 wp-login.php），因此错误提示、跳转都可控。
 * - nonce + 蜜罐 + 频率限制三层防滥用。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 登录 / 注册默认设置
 *
 * @return array
 */
function echo_auth_defaults() {
	return array(
		'auth_enabled'            => true,
		'auth_login_slug'         => 'login',
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
		'auth_welcome'            => '',
		'auth_shortcode'          => '',
		'auth_layout'             => 'auto',
		'auth_show_sidebar'       => false,
		'auth_custom_css'         => '',
		'auth_throttle'           => true,
	);
}

/**
 * 读取一项或全部登录/注册设置
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
 * 是否允许在前台注册
 *
 * 同时要求主题设置开启、且 WordPress「设置 → 常规」允许任何人注册。
 *
 * @return bool
 */
function echo_auth_registration_allowed() {
	return (bool) echo_auth_config( 'auth_allow_register', true ) && (bool) get_option( 'users_can_register' );
}

/**
 * 解析页面地址（优先实际页面，其次回退到 WordPress 原生地址）
 *
 * @param string $mode login|register。
 * @return string
 */
function echo_auth_url( $mode = 'login' ) {
	$mode = 'register' === $mode ? 'register' : 'login';
	$key  = 'register' === $mode ? 'auth_register_slug' : 'auth_login_slug';
	$slug = sanitize_title( echo_auth_config( $key, $mode ) );
	if ( '' === $slug ) {
		$slug = $mode;
	}

	$page = get_page_by_path( $slug );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}

	if ( 'register' === $mode ) {
		return get_option( 'users_can_register' ) ? wp_registration_url() : wp_login_url();
	}
	return wp_login_url();
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
 * 频率限制的 transient 键（按 IP + 动作）
 *
 * @param string $action login|register。
 * @return string
 */
function echo_auth_throttle_key( $action ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	return 'echo_auth_rl_' . $action . '_' . md5( $ip );
}

/**
 * 是否已被限流
 *
 * @param string $action login|register。
 * @return bool
 */
function echo_auth_is_throttled( $action ) {
	$data  = get_transient( echo_auth_throttle_key( $action ) );
	$limit = 'register' === $action ? 6 : 12;
	return is_array( $data ) && isset( $data['count'] ) && (int) $data['count'] >= $limit;
}

/**
 * 记录一次失败
 *
 * @param string $action login|register。
 * @return void
 */
function echo_auth_throttle_hit( $action ) {
	$key  = echo_auth_throttle_key( $action );
	$data = get_transient( $key );
	$count = ( is_array( $data ) && isset( $data['count'] ) ) ? (int) $data['count'] : 0;
	set_transient( $key, array( 'count' => $count + 1 ), 15 * MINUTE_IN_SECONDS );
}

/**
 * 成功后清除计数
 *
 * @param string $action login|register。
 * @return void
 */
function echo_auth_throttle_clear( $action ) {
	delete_transient( echo_auth_throttle_key( $action ) );
}

/**
 * 写入一条反馈（错误或提示）
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
 * 处理表单提交（在模板输出前调用）
 *
 * @param string $mode 当前页面 login|register。
 * @return void
 */
function echo_auth_handle_request( $mode = 'login' ) {
	if ( ! echo_auth_config( 'auth_enabled', true ) ) {
		return;
	}

	if ( ! isset( $GLOBALS['echo_auth_feedback'] ) || ! is_array( $GLOBALS['echo_auth_feedback'] ) ) {
		$GLOBALS['echo_auth_feedback'] = array(
			'error'  => '',
			'notice' => '',
		);
	}

	// 注册成功后的回跳提示
	if ( isset( $_GET['registered'] ) ) {
		echo_auth_set_feedback( 'notice', __( '注册成功，请使用新账号登录。', 'echo' ) );
	}

	if ( empty( $_POST['echo_auth_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['echo_auth_action'] ) );
	if ( ! in_array( $action, array( 'login', 'register' ), true ) ) {
		return;
	}

	if ( ! isset( $_POST['echo_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['echo_auth_nonce'] ) ), 'echo_auth' ) ) {
		echo_auth_set_feedback( 'error', __( '页面已过期，请刷新后重试。', 'echo' ) );
		return;
	}

	// 蜜罐字段：正常用户看不到，填了即视为机器人
	if ( ! empty( $_POST['echo_auth_hp'] ) ) {
		echo_auth_set_feedback( 'error', __( '提交失败，请稍后重试。', 'echo' ) );
		return;
	}

	if ( 'login' === $action ) {
		echo_auth_do_login();
	} else {
		echo_auth_do_register();
	}
}

/**
 * 执行登录
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
	$password = isset( $_POST['pwd'] ) ? (string) $_POST['pwd'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- 密码不能清洗，否则会改变原值。
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
			// 不区分「用户名不存在」与「密码错误」，避免泄露账号是否存在
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
 * 执行注册
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

	// 不使用 strict，保留中文用户名（strict 会剔除全部非 ASCII 字符）
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

	if ( $set_password ) {
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

		// 通知管理员有新用户（不向用户发密码邮件，因为密码由本人设置）
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

	// 由 WordPress 生成随机密码并邮件发送
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

/**
 * 已登录用户访问登录 / 注册页时跳到目标页
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
	if ( ! is_page_template( array( 'template-login.php', 'template-register.php' ) ) ) {
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
 * 渲染一条反馈
 *
 * @return void
 */
function echo_auth_render_feedback() {
	$fb = isset( $GLOBALS['echo_auth_feedback'] ) && is_array( $GLOBALS['echo_auth_feedback'] )
		? $GLOBALS['echo_auth_feedback']
		: array( 'error' => '', 'notice' => '' );

	if ( ! empty( $fb['error'] ) ) {
		printf(
			'<div class="echo-auth__alert echo-auth__alert--error" role="alert">%s</div>',
			esc_html( $fb['error'] )
		);
	}
	if ( ! empty( $fb['notice'] ) ) {
		printf(
			'<div class="echo-auth__alert echo-auth__alert--success" role="status">%s</div>',
			esc_html( $fb['notice'] )
		);
	}
}

/**
 * 标签页（登录 / 注册互相切换）
 *
 * @param string $mode 当前模式。
 * @return void
 */
function echo_auth_render_tabs( $mode ) {
	if ( ! echo_auth_config( 'auth_show_tabs', true ) ) {
		return;
	}

	$show_register = echo_auth_registration_allowed();
	if ( 'register' === $mode && ! $show_register ) {
		return;
	}
	if ( 'login' === $mode && ! $show_register ) {
		return;
	}
	?>
	<nav class="echo-auth__tabs" aria-label="<?php esc_attr_e( '登录与注册', 'echo' ); ?>">
		<a class="echo-auth__tab<?php echo 'login' === $mode ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>"
			<?php echo 'login' === $mode ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( echo_auth_config( 'auth_login_title', __( '登录', 'echo' ) ) ); ?>
		</a>
		<a class="echo-auth__tab<?php echo 'register' === $mode ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( echo_auth_url( 'register' ) ); ?>"
			<?php echo 'register' === $mode ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( echo_auth_config( 'auth_register_title', __( '注册', 'echo' ) ) ); ?>
		</a>
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
				<a class="echo-auth__link" href="<?php echo esc_url( wp_lostpassword_url( echo_auth_url( 'login' ) ) ); ?>"><?php esc_html_e( '忘记密码？', 'echo' ); ?></a>
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
 * 主渲染入口
 *
 * @param string $mode login|register。
 * @return void
 */
function echo_auth_render( $mode = 'login' ) {
	$mode = 'register' === $mode ? 'register' : 'login';

	if ( ! echo_auth_config( 'auth_enabled', true ) ) {
		echo '<p class="echo-auth__notice">' . esc_html__( '登录 / 注册功能已关闭，请在「Echo 设置 → 登录 / 注册」中开启。', 'echo' ) . '</p>';
		return;
	}

	$layout  = echo_auth_config( 'auth_layout', 'auto' );
	$title   = 'register' === $mode ? echo_auth_config( 'auth_register_title', __( '注册', 'echo' ) ) : echo_auth_config( 'auth_login_title', __( '登录', 'echo' ) );
	$welcome = (string) echo_auth_config( 'auth_welcome', '' );
	$short   = trim( (string) echo_auth_config( 'auth_shortcode', '' ) );

	if ( 'register' === $mode && ! echo_auth_registration_allowed() ) {
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
	?>
	<div class="echo-auth echo-auth--<?php echo esc_attr( $layout ); ?>">
		<div class="echo-auth__card">
			<h1 class="echo-auth__title"><?php echo esc_html( $title ); ?></h1>

			<?php if ( '' !== $welcome ) : ?>
				<p class="echo-auth__desc"><?php echo esc_html( $welcome ); ?></p>
			<?php endif; ?>

			<?php
			echo_auth_render_tabs( $mode );
			echo_auth_render_feedback();

			if ( 'register' === $mode ) {
				echo_auth_form_register();
			} else {
				echo_auth_form_login();
			}
			?>

			<?php if ( '' !== $short ) : ?>
				<div class="echo-auth__shortcode"><?php echo do_shortcode( $short ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>

			<p class="echo-auth__footer">
				<?php if ( 'register' === $mode ) : ?>
					<?php esc_html_e( '已有账号？', 'echo' ); ?>
					<a class="echo-auth__link" href="<?php echo esc_url( echo_auth_url( 'login' ) ); ?>"><?php esc_html_e( '直接登录', 'echo' ); ?></a>
				<?php elseif ( echo_auth_registration_allowed() ) : ?>
					<?php esc_html_e( '还没有账号？', 'echo' ); ?>
					<a class="echo-auth__link" href="<?php echo esc_url( echo_auth_url( 'register' ) ); ?>"><?php esc_html_e( '立即注册', 'echo' ); ?></a>
				<?php endif; ?>
				<span class="echo-auth__dot" aria-hidden="true">·</span>
				<a class="echo-auth__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'echo' ); ?></a>
			</p>
		</div>
	</div>
	<?php
}

/* ============================================================
 * 后台字段渲染
 * ============================================================ */

/**
 * 页面别名字段（附带地址与页面状态）
 *
 * @param array $args 字段参数，需含 mode=login|register。
 * @return void
 */
function echo_field_auth_slug( $args ) {
	$mode = isset( $args['mode'] ) ? $args['mode'] : 'login';
	$key  = isset( $args['label_for'] ) ? $args['label_for'] : ( 'register' === $mode ? 'auth_register_slug' : 'auth_login_slug' );
	$slug = sanitize_title( echo_get_setting( $key, $mode ) );
	$page = get_page_by_path( $slug );
	?>
	<input type="text" id="<?php echo esc_attr( $key ); ?>" name="echo_settings[<?php echo esc_attr( $key ); ?>]"
		value="<?php echo esc_attr( $slug ); ?>" class="regular-text" />
	<p class="description">
		<?php esc_html_e( '页面地址：', 'echo' ); ?>
		<code><?php echo esc_html( home_url( '/' . $slug . '/' ) ); ?></code>
	</p>
	<?php if ( $page ) : ?>
		<p class="description">
			<?php esc_html_e( '已找到同名页面。', 'echo' ); ?>
			<a href="<?php echo esc_url( get_edit_post_link( $page ) ); ?>"><?php esc_html_e( '编辑该页面', 'echo' ); ?></a>
			<?php
			echo esc_html(
				'register' === $mode
					? __( '，并在「页面属性 → 模板」中选择「注册」。', 'echo' )
					: __( '，并在「页面属性 → 模板」中选择「登录」。', 'echo' )
			);
			?>
		</p>
	<?php else : ?>
		<p class="description echo-my-slug-warn">
			<?php esc_html_e( '尚未找到该别名的页面。请新建页面、把别名设为上面的值，并在「页面属性 → 模板」中选择对应模板。', 'echo' ); ?>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( '新建页面', 'echo' ); ?></a>
		</p>
	<?php endif;
}

/* ============================================================
 * 清洗
 * ============================================================ */

/**
 * 清洗登录 / 注册设置
 *
 * @param array $input  原始提交。
 * @param array $output 已清洗的输出（按引用追加）。
 * @return void
 */
function echo_sanitize_auth_settings( $input, &$output ) {
	$defaults = echo_auth_defaults();

	// 旧备份中没有这些字段时保留当前值，避免导入旧配置把登录/注册整组重置。
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
	$reg_slug   = isset( $input['auth_register_slug'] ) ? sanitize_title( $input['auth_register_slug'] ) : '';
	$output['auth_login_slug']    = '' !== $login_slug ? $login_slug : 'login';
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
	$output['auth_login_title']     = isset( $input['auth_login_title'] ) && '' !== trim( $input['auth_login_title'] )
		? sanitize_text_field( $input['auth_login_title'] )
		: $defaults['auth_login_title'];
	$output['auth_register_title']  = isset( $input['auth_register_title'] ) && '' !== trim( $input['auth_register_title'] )
		? sanitize_text_field( $input['auth_register_title'] )
		: $defaults['auth_register_title'];

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
 * 登录 / 注册页的额外 CSS（自定义 CSS）
 *
 * @return string
 */
function echo_auth_css() {
	$custom = trim( (string) echo_auth_config( 'auth_custom_css', '' ) );
	return '' !== $custom ? $custom : '';
}

/**
 * 导航栏未登录时的登录 / 注册入口
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
