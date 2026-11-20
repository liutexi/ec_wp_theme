<?php
/**
 * 邮件 / SMTP 与注册邮件
 *
 * - SMTP：接管 PHPMailer，配置主机、端口、加密方式、认证与发件人
 * - 测试：后台「Echo 设置 → 邮件 / SMTP」一键发送测试邮件，结果直接回显
 * - 注册验证码：先校验邮箱验证码，通过后才创建账号
 * - 欢迎邮件：注册成功后可自动发送一封欢迎信
 *
 * 所有选项与主题其余设置一起存入 echo_settings。
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
function echo_mail_defaults() {
	return array(
		// SMTP
		'smtp_enabled'             => false,
		'smtp_host'                => '',
		'smtp_port'                => 465,
		'smtp_secure'              => 'ssl',
		'smtp_auth'                => true,
		'smtp_user'                => '',
		'smtp_pass'                => '',
		'smtp_from_email'          => '',
		'smtp_from_name'           => '',
		'smtp_timeout'             => 15,
		// 注册邮件
		'auth_email_code'          => false,
		'auth_email_code_ttl'      => 10,
		'auth_email_subject'       => '【{site}】注册验证码：{code}',
		'auth_email_body'          => "你好，\n\n你正在注册「{site}」，本次验证码为：\n\n{code}\n\n验证码 {minutes} 分钟内有效，请勿转发给他人。\n如果这不是你的操作，忽略本邮件即可。",
		'auth_email_welcome'       => false,
		'auth_email_welcome_subject' => '欢迎加入 {site}',
		'auth_email_welcome_body'  => "你好 {user}，\n\n欢迎加入「{site}」，你的账号已经可以正常登录。\n\n登录地址：{login_url}\n\n祝你使用愉快。",
	);
}

/**
 * 读取一项或全部邮件设置
 *
 * @param string|null $key     键名，null 返回全部设置。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_mail_config( $key = null, $default = null ) {
	$settings = echo_get_settings();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * SMTP 是否已配置（开关打开且填了主机）
 *
 * @return bool
 */
function echo_mail_smtp_ready() {
	return (bool) echo_mail_config( 'smtp_enabled', false ) && '' !== trim( (string) echo_mail_config( 'smtp_host', '' ) );
}

/**
 * 注册验证码是否启用
 *
 * @return bool
 */
function echo_mail_code_enabled() {
	return (bool) echo_mail_config( 'auth_email_code', false );
}

/**
 * 验证码有效期（分钟）
 *
 * @return int
 */
function echo_mail_code_ttl() {
	return max( 5, min( 60, absint( echo_mail_config( 'auth_email_code_ttl', 10 ) ) ) );
}

/* ============================================================
 * SMTP 接管
 * ============================================================ */

/**
 * 配置 PHPMailer
 *
 * @param PHPMailer\PHPMailer\PHPMailer|object $phpmailer PHPMailer 实例。
 * @return void
 */
function echo_mail_configure_phpmailer( $phpmailer ) {
	if ( ! echo_mail_smtp_ready() || ! is_object( $phpmailer ) ) {
		return;
	}

	$host = trim( (string) echo_mail_config( 'smtp_host', '' ) );

	try {
		$phpmailer->isSMTP();
		$phpmailer->Host   = $host;
		$phpmailer->Port   = max( 1, min( 65535, absint( echo_mail_config( 'smtp_port', 465 ) ) ) );
		$phpmailer->CharSet = 'UTF-8';
		$phpmailer->Timeout = max( 5, min( 60, absint( echo_mail_config( 'smtp_timeout', 15 ) ) ) );
		$phpmailer->SMTPDebug = 0;

		$secure = (string) echo_mail_config( 'smtp_secure', 'ssl' );
		if ( 'ssl' === $secure ) {
			$phpmailer->SMTPSecure  = 'ssl';
			$phpmailer->SMTPAutoTLS = false;
		} elseif ( 'tls' === $secure ) {
			$phpmailer->SMTPSecure  = 'tls';
			$phpmailer->SMTPAutoTLS = true;
		} else {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		}

		$user = (string) echo_mail_config( 'smtp_user', '' );
		$pass = (string) echo_mail_config( 'smtp_pass', '' );
		$phpmailer->SMTPAuth = (bool) echo_mail_config( 'smtp_auth', true ) && '' !== $user;
		if ( $phpmailer->SMTPAuth ) {
			$phpmailer->Username = $user;
			$phpmailer->Password = $pass;
		}

		$from_email = (string) echo_mail_config( 'smtp_from_email', '' );
		if ( '' === $from_email ) {
			$from_email = $user;
		}
		if ( is_email( $from_email ) ) {
			$from_name = trim( (string) echo_mail_config( 'smtp_from_name', '' ) );
			if ( '' === $from_name ) {
				$from_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			}
			$phpmailer->setFrom( $from_email, $from_name );
		}
	} catch ( Exception $e ) {
		// 配置异常时退回 WordPress 默认发信方式，避免整站发信被拖死
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Echo SMTP: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
add_action( 'phpmailer_init', 'echo_mail_configure_phpmailer' );

/**
 * 默认发件邮箱
 *
 * @param string $email 原发件邮箱。
 * @return string
 */
function echo_mail_from_email( $email ) {
	if ( ! echo_mail_smtp_ready() ) {
		return $email;
	}
	$custom = sanitize_email( (string) echo_mail_config( 'smtp_from_email', '' ) );
	if ( '' === $custom ) {
		$custom = sanitize_email( (string) echo_mail_config( 'smtp_user', '' ) );
	}
	return is_email( $custom ) ? $custom : $email;
}
add_filter( 'wp_mail_from', 'echo_mail_from_email' );

/**
 * 默认发件人名称
 *
 * @param string $name 原发件人名称。
 * @return string
 */
function echo_mail_from_name( $name ) {
	if ( ! echo_mail_smtp_ready() ) {
		return $name;
	}
	$custom = trim( (string) echo_mail_config( 'smtp_from_name', '' ) );
	return '' !== $custom ? $custom : $name;
}
add_filter( 'wp_mail_from_name', 'echo_mail_from_name' );

/**
 * 记录最近一次发信失败原因
 *
 * @param WP_Error $error 失败信息。
 * @return void
 */
function echo_mail_capture_failure( $error ) {
	if ( is_wp_error( $error ) ) {
		$GLOBALS['echo_mail_last_error'] = $error->get_error_message();
	}
}
add_action( 'wp_mail_failed', 'echo_mail_capture_failure' );

/**
 * 取最近一次失败原因
 *
 * @return string
 */
function echo_mail_last_error() {
	return isset( $GLOBALS['echo_mail_last_error'] ) ? (string) $GLOBALS['echo_mail_last_error'] : '';
}

/* ============================================================
 * 发信与模板
 * ============================================================ */

/**
 * 占位符替换
 *
 * @param string $text 原始文本。
 * @param array  $vars 键值对（不含花括号）。
 * @return string
 */
function echo_mail_fill( $text, $vars ) {
	foreach ( $vars as $key => $value ) {
		$text = str_replace( '{' . $key . '}', (string) $value, $text );
	}
	return $text;
}

/**
 * 包一层简单 HTML 邮件外壳
 *
 * @param string $inner 正文 HTML。
 * @return string
 */
function echo_mail_wrap_html( $inner ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	return '<!DOCTYPE html><html><head><meta charset="UTF-8" /></head>'
		. '<body style="margin:0;padding:24px 12px;background:#f6f7f9;">'
		. '<div style="max-width:560px;margin:0 auto;padding:28px 30px;border-radius:16px;background:#ffffff;'
		. 'font-family:-apple-system,BlinkMacSystemFont,\'PingFang SC\',\'Microsoft YaHei\',sans-serif;'
		. 'font-size:15px;line-height:1.75;color:#1f2328;">'
		. $inner
		. '<p style="margin:26px 0 0;padding-top:16px;border-top:1px solid #eceff3;font-size:12px;color:#8b949e;">'
		. esc_html( $site ) . ' · 此邮件由系统自动发送，请勿直接回复。'
		. '</p></div></body></html>';
}

/**
 * 正文转 HTML（保留换行，突出验证码）
 *
 * @param string $text 纯文本正文。
 * @param string $code 需要高亮的验证码，可为空。
 * @return string
 */
function echo_mail_text_to_html( $text, $code = '' ) {
	$html = nl2br( esc_html( $text ) );
	if ( '' !== $code ) {
		$html = preg_replace(
			'/(?<![0-9])' . preg_quote( $code, '/' ) . '(?![0-9])/',
			'<strong style="display:inline-block;padding:2px 10px;border-radius:8px;background:#eaf2ff;'
			. 'color:#3482ff;font-size:22px;letter-spacing:6px;">' . $code . '</strong>',
			$html,
			1
		);
	}
	return '<div>' . $html . '</div>';
}

/**
 * 发送一封邮件
 *
 * @param string $to      收件人。
 * @param string $subject 主题。
 * @param string $html    正文 HTML。
 * @return bool
 */
function echo_mail_send( $to, $subject, $html ) {
	if ( ! is_email( $to ) ) {
		$GLOBALS['echo_mail_last_error'] = '收件邮箱格式不正确';
		return false;
	}

	$GLOBALS['echo_mail_last_error'] = '';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$sent    = wp_mail( $to, $subject, $html, $headers );

	if ( ! $sent && '' === echo_mail_last_error() ) {
		$GLOBALS['echo_mail_last_error'] = __( '服务器未返回发送结果，可能是 SMTP 连接失败', 'echo' );
	}
	return (bool) $sent;
}

/**
 * 邮箱脱敏：ab***@qq.com
 *
 * @param string $email 邮箱。
 * @return string
 */
function echo_mail_mask_email( $email ) {
	$parts = explode( '@', (string) $email, 2 );
	if ( count( $parts ) < 2 ) {
		return (string) $email;
	}
	$name = $parts[0];
	$len  = mb_strlen( $name );
	if ( $len <= 1 ) {
		$masked = $name . '***';
	} elseif ( $len <= 3 ) {
		$masked = mb_substr( $name, 0, 1 ) . '***';
	} else {
		$masked = mb_substr( $name, 0, 2 ) . '***';
	}
	return $masked . '@' . $parts[1];
}

/**
 * 验证码哈希
 *
 * @param string $code  验证码。
 * @param string $email 邮箱。
 * @return string
 */
function echo_mail_code_hash( $code, $email ) {
	return wp_hash( $code . '|' . strtolower( $email ), 'auth' );
}

/**
 * 发送注册验证码邮件
 *
 * @param string $email 收件邮箱。
 * @param string $code  验证码。
 * @return bool
 */
function echo_mail_send_register_code( $email, $code ) {
	$ttl  = echo_mail_code_ttl();
	$vars = array(
		'site'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'code'    => $code,
		'minutes' => $ttl,
	);

	$subject = (string) echo_mail_config( 'auth_email_subject', '' );
	if ( '' === trim( $subject ) ) {
		$subject = echo_mail_defaults()['auth_email_subject'];
	}
	$body = (string) echo_mail_config( 'auth_email_body', '' );
	if ( '' === trim( $body ) ) {
		$body = echo_mail_defaults()['auth_email_body'];
	}

	$subject = echo_mail_fill( $subject, $vars );
	$text    = echo_mail_fill( $body, $vars );

	return echo_mail_send( $email, $subject, echo_mail_wrap_html( echo_mail_text_to_html( $text, $code ) ) );
}

/**
 * 发送注册欢迎邮件
 *
 * @param int $user_id 用户 ID。
 * @return bool
 */
function echo_mail_send_welcome( $user_id ) {
	if ( ! echo_mail_config( 'auth_email_welcome', false ) ) {
		return false;
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return false;
	}

	$defaults = echo_mail_defaults();
	$subject  = (string) echo_mail_config( 'auth_email_welcome_subject', '' );
	if ( '' === trim( $subject ) ) {
		$subject = $defaults['auth_email_welcome_subject'];
	}
	$body = (string) echo_mail_config( 'auth_email_welcome_body', '' );
	if ( '' === trim( $body ) ) {
		$body = $defaults['auth_email_welcome_body'];
	}

	$vars = array(
		'site'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'user'      => $user->display_name,
		'login_url' => echo_auth_url( 'login' ),
	);

	$subject = echo_mail_fill( $subject, $vars );
	$text    = echo_mail_fill( $body, $vars );

	return echo_mail_send( $user->user_email, $subject, echo_mail_wrap_html( echo_mail_text_to_html( $text ) ) );
}

/* ============================================================
 * 注册验证码：校验
 * ============================================================ */

/**
 * 校验注册验证码
 *
 * 成功返回 true，失败返回 WP_Error（带可直接展示的中文提示）。
 *
 * @param string $email 邮箱。
 * @param string $code  用户输入的验证码。
 * @return true|WP_Error
 */
function echo_mail_verify_register_code( $email, $code ) {
	$key  = 'echo_code_' . md5( strtolower( (string) $email ) );
	$data = get_transient( $key );

	if ( ! is_array( $data ) || empty( $data['hash'] ) ) {
		return new WP_Error( 'echo_code_expired', __( '验证码已过期，请重新获取。', 'echo' ) );
	}

	$tries = isset( $data['tries'] ) ? (int) $data['tries'] : 0;
	if ( $tries >= 5 ) {
		delete_transient( $key );
		return new WP_Error( 'echo_code_locked', __( '验证码错误次数过多，请重新获取。', 'echo' ) );
	}

	$code = preg_replace( '/\D+/', '', (string) $code );
	if ( '' === $code || ! hash_equals( (string) $data['hash'], echo_mail_code_hash( $code, $email ) ) ) {
		$data['tries'] = $tries + 1;
		$expires       = isset( $data['expires'] ) ? (int) $data['expires'] : time() + MINUTE_IN_SECONDS;
		set_transient( $key, $data, max( 60, $expires - time() ) );
		return new WP_Error( 'echo_code_invalid', __( '验证码不正确，请检查后重试。', 'echo' ) );
	}

	delete_transient( $key );
	return true;
}

/**
 * 客户端 IP（仅用于限流）
 *
 * @return string
 */
function echo_mail_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	return $ip;
}

/**
 * AJAX：发送注册验证码
 *
 * @return void
 */
function echo_ajax_send_register_code() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'echo_auth_mail' ) ) {
		wp_send_json_error( array( 'message' => __( '页面已过期，请刷新后重试。', 'echo' ) ), 403 );
	}

	if ( ! echo_auth_registration_allowed() ) {
		wp_send_json_error( array( 'message' => __( '本站暂未开放注册。', 'echo' ) ), 403 );
	}
	if ( ! echo_mail_code_enabled() ) {
		wp_send_json_error( array( 'message' => __( '当前未启用邮箱验证码。', 'echo' ) ), 403 );
	}

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( '请先填写正确的邮箱地址。', 'echo' ) ), 400 );
	}
	if ( email_exists( $email ) ) {
		wp_send_json_error( array( 'message' => __( '该邮箱已注册，请直接登录。', 'echo' ) ), 400 );
	}

	$slug      = md5( strtolower( $email ) );
	$ttl       = echo_mail_code_ttl();
	$cooldown  = (int) get_transient( 'echo_code_cd_' . $slug );
	if ( $cooldown ) {
		$left = max( 1, $cooldown - time() );
		wp_send_json_error(
			array(
				'message'  => sprintf( /* translators: %d: 剩余秒数 */ __( '请 %d 秒后再获取。', 'echo' ), $left ),
				'cooldown' => $left,
			),
			429
		);
	}

	$hits = (int) get_transient( 'echo_code_hits_' . $slug );
	if ( $hits >= 5 ) {
		wp_send_json_error( array( 'message' => __( '该邮箱获取验证码过于频繁，请 1 小时后再试。', 'echo' ) ), 429 );
	}

	$ip_key  = 'echo_code_ip_' . md5( echo_mail_client_ip() );
	$ip_hits = (int) get_transient( $ip_key );
	if ( $ip_hits >= 12 ) {
		wp_send_json_error( array( 'message' => __( '操作过于频繁，请稍后再试。', 'echo' ) ), 429 );
	}

	$code    = (string) wp_rand( 100000, 999999 );
	$expires = time() + $ttl * MINUTE_IN_SECONDS;

	set_transient(
		'echo_code_' . $slug,
		array(
			'hash'    => echo_mail_code_hash( $code, $email ),
			'tries'   => 0,
			'expires' => $expires,
		),
		$ttl * MINUTE_IN_SECONDS
	);
	set_transient( 'echo_code_cd_' . $slug, time() + 60, 2 * MINUTE_IN_SECONDS );
	set_transient( 'echo_code_hits_' . $slug, $hits + 1, HOUR_IN_SECONDS );
	set_transient( $ip_key, $ip_hits + 1, HOUR_IN_SECONDS );

	if ( ! echo_mail_send_register_code( $email, $code ) ) {
		delete_transient( 'echo_code_' . $slug );
		delete_transient( 'echo_code_cd_' . $slug );
		wp_send_json_error(
			array(
				'message' => sprintf(
					/* translators: %s: 失败原因 */
					__( '邮件发送失败：%s。请检查「邮件 / SMTP」配置。', 'echo' ),
					echo_mail_last_error()
				),
			),
			500
		);
	}

	wp_send_json_success(
		array(
			'message'  => sprintf(
				/* translators: 1: 脱敏邮箱 2: 有效期分钟 */
				__( '验证码已发送至 %1$s，%2$d 分钟内有效。', 'echo' ),
				echo_mail_mask_email( $email ),
				$ttl
			),
			'cooldown' => 60,
			'ttl'      => $ttl,
		)
	);
}
add_action( 'wp_ajax_echo_send_register_code', 'echo_ajax_send_register_code' );
add_action( 'wp_ajax_nopriv_echo_send_register_code', 'echo_ajax_send_register_code' );

/**
 * AJAX：后台发送测试邮件
 *
 * @return void
 */
function echo_ajax_test_mail() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( '无权限', 'echo' ) ), 403 );
	}

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'echo_settings_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( '安全校验失败，请刷新页面后重试。', 'echo' ) ), 403 );
	}

	$to = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( '' === $to ) {
		$current = wp_get_current_user();
		$to      = $current ? $current->user_email : '';
	}
	if ( ! is_email( $to ) ) {
		wp_send_json_error( array( 'message' => __( '请填写正确的收件邮箱。', 'echo' ) ), 400 );
	}

	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = sprintf( /* translators: %s: 站点名 */ __( '【%s】测试邮件', 'echo' ), $site );
	$body    = '<p>' . esc_html__( '这是一封来自 Echo 主题的测试邮件。', 'echo' ) . '</p>'
		. '<p>' . esc_html__( '如果你收到了这封邮件，说明 SMTP 配置已经生效，注册验证码、找回密码等邮件都可以正常发出。', 'echo' ) . '</p>'
		. '<p style="color:#8b949e;font-size:13px;">' . esc_html( sprintf( /* translators: %s: 时间 */ __( '发送时间：%s', 'echo' ), date_i18n( 'Y-m-d H:i:s' ) ) ) . '</p>';

	$start = microtime( true );
	$sent  = echo_mail_send( $to, $subject, echo_mail_wrap_html( $body ) );
	$cost  = (int) round( ( microtime( true ) - $start ) * 1000 );

	if ( ! $sent ) {
		wp_send_json_error(
			array(
				'message' => sprintf(
					/* translators: %s: 失败原因 */
					__( '发送失败：%s', 'echo' ),
					echo_mail_last_error()
				),
			),
			500
		);
	}

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: 1: 邮箱 2: 毫秒 */
				__( '已发送到 %1$s，耗时 %2$d ms，请查收（含垃圾箱）。', 'echo' ),
				echo_mail_mask_email( $to ),
				$cost
			),
		)
	);
}
add_action( 'wp_ajax_echo_test_mail', 'echo_ajax_test_mail' );

/* ============================================================
 * 后台字段
 * ============================================================ */

/**
 * 分类说明：显示当前发信状态
 *
 * @return void
 */
function echo_section_mail_desc() {
	$ready = echo_mail_smtp_ready();
	$code  = echo_mail_code_enabled();
	?>
	<p class="echo-mail-status">
		<span class="echo-mail-status__dot<?php echo $ready ? ' is-on' : ''; ?>" aria-hidden="true"></span>
		<?php
		echo esc_html(
			$ready
				? sprintf( /* translators: %s: SMTP 主机 */ __( '已启用 SMTP：%s', 'echo' ), (string) echo_mail_config( 'smtp_host', '' ) )
				: __( '当前使用服务器默认发信（mail()），建议配置 SMTP。', 'echo' )
		);
		?>
	</p>
	<?php if ( $code && ! $ready ) : ?>
		<p class="echo-mail-warn">
			<?php esc_html_e( '注册验证码已开启，但 SMTP 还没配好，验证码大概率发不出去，请先完成下面的配置。', 'echo' ); ?>
		</p>
	<?php endif; ?>
	<p class="description">
		<?php esc_html_e( '配置后请先在「发送测试」里发一封，确认能收到再开启注册验证码。', 'echo' ); ?>
	</p>
	<?php
}

/**
 * SMTP 常见服务商预设
 *
 * @return array
 */
function echo_mail_presets() {
	return array(
		'qq'      => array( 'label' => 'QQ 邮箱', 'host' => 'smtp.qq.com', 'port' => 465, 'secure' => 'ssl' ),
		'163'     => array( 'label' => '163 邮箱', 'host' => 'smtp.163.com', 'port' => 465, 'secure' => 'ssl' ),
		'126'     => array( 'label' => '126 邮箱', 'host' => 'smtp.126.com', 'port' => 465, 'secure' => 'ssl' ),
		'exmail'  => array( 'label' => '腾讯企业邮', 'host' => 'smtp.exmail.qq.com', 'port' => 465, 'secure' => 'ssl' ),
		'aliyun'  => array( 'label' => '阿里企业邮', 'host' => 'smtp.mxhichina.com', 'port' => 465, 'secure' => 'ssl' ),
		'gmail'   => array( 'label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls' ),
		'outlook' => array( 'label' => 'Outlook', 'host' => 'smtp.office365.com', 'port' => 587, 'secure' => 'tls' ),
	);
}

/**
 * 服务商预设按钮
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_smtp_preset( $args ) {
	?>
	<div class="echo-smtp-presets">
		<?php foreach ( echo_mail_presets() as $key => $preset ) : ?>
			<button type="button" class="button echo-smtp-presets__item"
				data-smtp-host="<?php echo esc_attr( $preset['host'] ); ?>"
				data-smtp-port="<?php echo esc_attr( (string) $preset['port'] ); ?>"
				data-smtp-secure="<?php echo esc_attr( $preset['secure'] ); ?>">
				<?php echo esc_html( $preset['label'] ); ?>
			</button>
		<?php endforeach; ?>
	</div>
	<p class="description"><?php esc_html_e( '点击自动填好主机、端口与加密方式，再补账号密码即可。', 'echo' ); ?></p>
	<?php
}

/**
 * 密码 / 密钥字段：不回显已保存的值
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_secret( $args ) {
	$key     = $args['label_for'];
	$saved   = '' !== (string) echo_get_setting( $key, '' );
	$placeholder = $saved ? __( '已保存，留空则保持不变', 'echo' ) : __( '请输入密码或授权码', 'echo' );
	?>
	<span class="echo-secret-field">
		<input type="password" id="<?php echo esc_attr( $key ); ?>"
			name="echo_settings[<?php echo esc_attr( $key ); ?>]"
			value="" placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text" autocomplete="new-password" data-echo-secret />
		<button type="button" class="button echo-secret-toggle" data-echo-secret-toggle><?php esc_html_e( '显示', 'echo' ); ?></button>
	</span>
	<?php if ( ! empty( $args['desc'] ) ) : ?>
		<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
	<?php endif;
}

/**
 * 测试邮件字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_mail_test( $args ) {
	$user  = wp_get_current_user();
	$email = $user ? $user->user_email : '';
	?>
	<div class="echo-mail-test">
		<input type="email" class="regular-text" data-mail-test-to
			value="<?php echo esc_attr( $email ); ?>"
			placeholder="<?php esc_attr_e( '收件邮箱', 'echo' ); ?>" />
		<button type="button" class="button button-secondary echo-mail-test__btn" data-mail-test>
			<?php esc_html_e( '发送测试邮件', 'echo' ); ?>
		</button>
	</div>
	<p class="echo-mail-test__status" data-mail-test-status aria-live="polite"></p>
	<p class="description"><?php esc_html_e( '请先保存上面的 SMTP 配置，再点这里测试；失败时会显示 SMTP 返回的具体原因。', 'echo' ); ?></p>
	<?php
}

/* ============================================================
 * 清洗
 * ============================================================ */

/**
 * 清洗邮件相关设置
 *
 * @param array $input  原始提交。
 * @param array $output 已清洗的输出（按引用追加）。
 * @return void
 */
function echo_sanitize_mail_settings( $input, &$output ) {
	$defaults = echo_mail_defaults();

	$has_mail = false;
	foreach ( array_keys( $defaults ) as $mail_key ) {
		if ( array_key_exists( $mail_key, $input ) ) {
			$has_mail = true;
			break;
		}
	}

	// 旧备份不含这些字段时沿用当前值
	if ( ! $has_mail ) {
		foreach ( $defaults as $mail_key => $mail_value ) {
			$output[ $mail_key ] = echo_get_setting( $mail_key, $mail_value );
		}
		return;
	}

	foreach ( array( 'smtp_enabled', 'smtp_auth', 'auth_email_code', 'auth_email_welcome' ) as $bool_key ) {
		$output[ $bool_key ] = isset( $input[ $bool_key ] ) && '1' === (string) $input[ $bool_key ];
	}

	$output['smtp_host'] = isset( $input['smtp_host'] ) ? sanitize_text_field( $input['smtp_host'] ) : '';
	$output['smtp_host'] = preg_replace( '#^https?://#i', '', trim( $output['smtp_host'] ) );
	$output['smtp_host'] = rtrim( (string) $output['smtp_host'], '/' );

	$port             = isset( $input['smtp_port'] ) ? absint( $input['smtp_port'] ) : (int) $defaults['smtp_port'];
	$output['smtp_port'] = max( 1, min( 65535, $port ) );

	$secure            = isset( $input['smtp_secure'] ) ? sanitize_key( $input['smtp_secure'] ) : 'ssl';
	$output['smtp_secure'] = in_array( $secure, array( 'ssl', 'tls', 'none' ), true ) ? $secure : 'ssl';

	$output['smtp_user'] = isset( $input['smtp_user'] ) ? sanitize_text_field( $input['smtp_user'] ) : '';

	// 密码不回显：留空视为保持原值
	$pass = isset( $input['smtp_pass'] ) ? trim( (string) wp_unslash( $input['smtp_pass'] ) ) : '';
	$output['smtp_pass'] = '' !== $pass ? $pass : (string) echo_get_setting( 'smtp_pass', '' );

	$from_email = isset( $input['smtp_from_email'] ) ? sanitize_email( $input['smtp_from_email'] ) : '';
	$output['smtp_from_email'] = is_email( $from_email ) ? $from_email : '';

	$output['smtp_from_name'] = isset( $input['smtp_from_name'] ) ? sanitize_text_field( $input['smtp_from_name'] ) : '';

	$timeout              = isset( $input['smtp_timeout'] ) ? absint( $input['smtp_timeout'] ) : (int) $defaults['smtp_timeout'];
	$output['smtp_timeout'] = max( 5, min( 60, $timeout ) );

	$ttl                    = isset( $input['auth_email_code_ttl'] ) ? absint( $input['auth_email_code_ttl'] ) : (int) $defaults['auth_email_code_ttl'];
	$output['auth_email_code_ttl'] = max( 5, min( 60, $ttl ) );

	$subject = isset( $input['auth_email_subject'] ) ? sanitize_text_field( $input['auth_email_subject'] ) : '';
	$output['auth_email_subject'] = '' !== $subject ? $subject : $defaults['auth_email_subject'];

	$body = isset( $input['auth_email_body'] ) ? sanitize_textarea_field( $input['auth_email_body'] ) : '';
	$output['auth_email_body'] = '' !== trim( (string) $body ) ? $body : $defaults['auth_email_body'];

	$welcome_subject = isset( $input['auth_email_welcome_subject'] ) ? sanitize_text_field( $input['auth_email_welcome_subject'] ) : '';
	$output['auth_email_welcome_subject'] = '' !== $welcome_subject ? $welcome_subject : $defaults['auth_email_welcome_subject'];

	$welcome_body = isset( $input['auth_email_welcome_body'] ) ? sanitize_textarea_field( $input['auth_email_welcome_body'] ) : '';
	$output['auth_email_welcome_body'] = '' !== trim( (string) $welcome_body ) ? $welcome_body : $defaults['auth_email_welcome_body'];
}
