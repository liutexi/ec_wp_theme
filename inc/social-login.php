<?php
/**
 * 彩虹聚合登录适配（Clogin Passport / 同类聚合登录插件）
 *
 * 插件通过在钩子上输出内容来渲染按钮：
 * - 登录按钮：do_action( 'clogin-passport' )
 * - 绑定入口：do_action( 'binding_social_media_account', $user )
 * - 社交头像：user meta 'clogin_passport_avatar'
 *
 * 本文件不要求插件一定存在：钩子没有回调时输出为空，主题不渲染任何容器，
 * 因此装上或卸载插件都不会留下空白区块。
 *
 * 头像优先级：主题自定义头像（用户主页上传） > 社交头像 > Gravatar。
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
function echo_social_defaults() {
	return array(
		'social_login'   => true,
		'social_title'   => '第三方登录',
		'social_binding' => true,
		'social_avatar'  => true,
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
 * 插件提供的钩子名（兼容官方与常见别名）
 *
 * @param string $type 'login' 或 'binding'。
 * @return array
 */
function echo_social_hooks( $type ) {
	if ( 'binding' === $type ) {
		return array( 'binding_social_media_account', 'binding_social_meta_account', 'clogin-binding' );
	}
	return array( 'clogin-passport', 'clogin_passport', 'clogin-login-buttons' );
}

/**
 * 捕捉插件在指定钩子上的输出
 *
 * @param string   $type 钩子类型：login / binding。
 * @param mixed    $arg  传给绑定钩子的用户对象。
 * @return string 插件输出，无插件返回空字符串。
 */
function echo_social_capture( $type, $arg = null ) {
	$html = '';
	foreach ( echo_social_hooks( $type ) as $hook ) {
		if ( ! has_action( $hook ) ) {
			continue;
		}
		ob_start();
		if ( null === $arg ) {
			do_action( $hook );
		} else {
			do_action( $hook, $arg );
		}
		$html .= (string) ob_get_clean();
	}
	return trim( $html );
}

/**
 * 插件是否可用（任一相关钩子已注册）
 *
 * @return bool
 */
function echo_social_plugin_active() {
	foreach ( array( 'login', 'binding' ) as $type ) {
		foreach ( echo_social_hooks( $type ) as $hook ) {
			if ( has_action( $hook ) ) {
				return true;
			}
		}
	}
	return false;
}

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

	// 没有插件钩子时直接返回，避免无谓加载 wp-admin/includes/user.php
	$hook_ready = false;
	foreach ( echo_social_hooks( 'binding' ) as $hook ) {
		if ( has_action( $hook ) ) {
			$hook_ready = true;
			break;
		}
	}
	if ( ! $hook_ready ) {
		return;
	}

	// get_user_to_edit 位于 admin include，部分插件据此读取用户字段
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

	$html = echo_social_capture( 'binding', $arg );
	if ( '' === $html ) {
		return;
	}
	?>
	<div class="echo-social-binding">
		<h3 class="echo-social-binding__title"><?php esc_html_e( '社交账号绑定', 'echo' ); ?></h3>
		<div class="echo-social__list"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
	<?php
}

/**
 * 从用户标识解析 UID（供头像过滤器复用）
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
 * 社交头像：作为自定义头像与 Gravatar 之间的中间层
 *
 * 挂在 pre_get_avatar_data 优先级 5，低于主题自定义头像的 10，
 * 因此用户在自己主页上传的头像始终优先。
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

	// 主题自定义头像存在时交给主题处理，避免抢先
	if ( function_exists( 'echo_user_avatar_data' ) && echo_user_avatar_data( $uid ) ) {
		return $args;
	}

	$social = get_user_meta( $uid, 'clogin_passport_avatar', true );
	if ( is_array( $social ) ) {
		$social = isset( $social['url'] ) ? $social['url'] : '';
	}
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
 * 把社交头像同步到主题的自定义头像字段（用于「编辑资料」里显示当前头像）
 *
 * 仅在用户从未上传过自定义头像、且开启了社交头像时返回社交头像数据，
 * 这样编辑区能看到正在使用的头像，并允许上传自定义头像覆盖。
 *
 * @param array|null $data 主题自定义头像数据。
 * @param int        $uid  用户 ID。
 * @return array|null
 */
function echo_social_effective_avatar( $data, $uid ) {
	if ( $data || ! echo_social_config( 'social_avatar', true ) ) {
		return $data;
	}

	$social = get_user_meta( $uid, 'clogin_passport_avatar', true );
	if ( is_array( $social ) ) {
		$social = isset( $social['url'] ) ? $social['url'] : '';
	}
	$social = is_string( $social ) ? trim( $social ) : '';
	if ( '' === $social || ! preg_match( '#^https?://#i', $social ) ) {
		return null;
	}

	return array(
		'url' => set_url_scheme( esc_url_raw( $social ) ),
		'v'   => 'social',
	);
}

/**
 * 清洗设置
 *
 * @param array $input  提交数据。
 * @param array $output 已清洗的输出（引用）。
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

	// 旧备份不含这些字段时沿用当前值，避免误判为全部关闭
	if ( ! $has_social ) {
		foreach ( $defaults as $social_key => $social_value ) {
			$output[ $social_key ] = echo_get_setting( $social_key, $social_value );
		}
		return;
	}

	$output['social_login']   = isset( $input['social_login'] ) && '1' === (string) $input['social_login'];
	$output['social_binding'] = isset( $input['social_binding'] ) && '1' === (string) $input['social_binding'];
	$output['social_avatar']  = isset( $input['social_avatar'] ) && '1' === (string) $input['social_avatar'];

	$title = isset( $input['social_title'] ) ? sanitize_text_field( $input['social_title'] ) : '';
	$output['social_title'] = '' !== $title ? $title : $defaults['social_title'];
}
