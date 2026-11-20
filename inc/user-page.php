<?php
/**
 * 用户主页（/uid/{id}）与自助资料编辑
 *
 * - 路由：^<base>/([0-9]+)/?$ → index.php?echo_uid=ID
 * - 页面：template-user.php，通过 template_include 载入
 * - 资料：登录用户可在自己的主页修改昵称、简介、网站与密码
 *
 * 选项统一存入 echo_settings。
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
function echo_user_defaults() {
	return array(
		'user_enabled'         => true,
		'user_base'            => 'uid',
		'user_allow_edit'      => true,
		'user_allow_password'  => true,
		'user_allow_avatar'    => true,
		'user_avatar_max_kb'   => 4096,
		'user_avatar_size'     => 96,
		'user_recent_posts'    => 5,
		'user_recent_comments' => 5,
		'user_show_stats'      => true,
		'user_show_posts'      => true,
		'user_show_comments'   => true,
		'user_show_sidebar'    => false,
		'user_card_style'      => 'auto',
		'user_custom_css'      => '',
	);
}

/**
 * 读取设置
 *
 * @param string|null $key     键名。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_user_config( $key = null, $default = null ) {
	$settings = echo_get_settings();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 路由前缀（默认 uid）
 *
 * @return string
 */
function echo_user_base() {
	$base = sanitize_title( echo_user_config( 'user_base', 'uid' ) );
	return '' !== $base ? $base : 'uid';
}

/**
 * 用户主页地址
 *
 * 固定链接为「朴素」时退回查询参数，保证链接可用。
 *
 * @param int $uid 用户 ID。
 * @return string
 */
function echo_user_url( $uid ) {
	$uid = absint( $uid );
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		return add_query_arg( 'echo_uid', $uid, home_url( '/' ) );
	}
	return home_url( '/' . echo_user_base() . '/' . $uid . '/' );
}

/**
 * 注册重写规则
 *
 * @return void
 */
function echo_user_register_rewrite() {
	add_rewrite_tag( '%echo_uid%', '([0-9]+)' );
	add_rewrite_rule(
		'^' . preg_quote( echo_user_base(), '#' ) . '/([0-9]+)/?$',
		'index.php?echo_uid=$matches[1]',
		'top'
	);
}
add_action( 'init', 'echo_user_register_rewrite' );

/**
 * 当前请求的用户 ID
 *
 * @return int
 */
function echo_user_current_uid() {
	$uid = get_query_var( 'echo_uid' );
	if ( '' === $uid && isset( $_GET['echo_uid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$uid = wp_unslash( $_GET['echo_uid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return absint( $uid );
}

/**
 * 载入用户主页模板
 *
 * @param string $template 原模板。
 * @return string
 */
function echo_user_template_include( $template ) {
	$uid = echo_user_current_uid();
	if ( ! $uid || ! echo_user_config( 'user_enabled', true ) ) {
		return $template;
	}
	if ( ! get_userdata( $uid ) ) {
		return $template; // 交给 404
	}
	$custom = locate_template( 'template-user.php' );
	return $custom ? $custom : $template;
}
add_filter( 'template_include', 'echo_user_template_include' );

/**
 * 用户不存在或功能关闭时返回 404
 *
 * @return void
 */
function echo_user_maybe_404() {
	$uid = echo_user_current_uid();
	if ( ! $uid ) {
		return;
	}
	if ( echo_user_config( 'user_enabled', true ) && get_userdata( $uid ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'echo_user_maybe_404' );

/**
 * 用户主页不参与 canonical 重定向
 *
 * 自定义查询变量没有对应的 WP 对象，canonical 可能把 /uid/1/ 重定向到首页。
 *
 * @param string|false $redirect 目标地址。
 * @return string|false
 */
function echo_user_disable_canonical( $redirect ) {
	if ( echo_user_current_uid() ) {
		return false;
	}
	return $redirect;
}
add_filter( 'redirect_canonical', 'echo_user_disable_canonical' );

/**
 * 页面标题
 *
 * @param array $parts 标题片段。
 * @return array
 */
function echo_user_document_title( $parts ) {
	$uid = echo_user_current_uid();
	if ( ! $uid ) {
		return $parts;
	}
	$user = get_userdata( $uid );
	if ( $user ) {
		/* translators: %s: 用户显示名 */
		$parts['title'] = sprintf( __( '%s 的主页', 'echo' ), $user->display_name );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'echo_user_document_title' );

/**
 * body class
 *
 * @param array $classes 已有 class。
 * @return array
 */
function echo_user_body_class( $classes ) {
	if ( echo_user_current_uid() ) {
		$classes[] = 'echo-user-page';
	}
	return $classes;
}
add_filter( 'body_class', 'echo_user_body_class' );

/**
 * 首次启用 / 切换主题 / 修改前缀后刷新重写规则
 *
 * @return void
 */
function echo_user_flush_rewrite() {
	flush_rewrite_rules( false );
	update_option( 'echo_user_rewrite_flag', ECHO_VERSION . '|' . echo_user_base() );
}
add_action( 'after_switch_theme', 'echo_user_flush_rewrite' );

/**
 * 后台加载时按版本 / 前缀补一次刷新
 *
 * @return void
 */
function echo_user_maybe_flush() {
	$flag = get_option( 'echo_user_rewrite_flag' );
	if ( $flag === ECHO_VERSION . '|' . echo_user_base() ) {
		return;
	}
	echo_user_flush_rewrite();
}
add_action( 'admin_init', 'echo_user_maybe_flush', 20 );

/**
 * 是否有权编辑该用户资料
 *
 * @param int $uid 目标用户 ID。
 * @return bool
 */
function echo_user_can_edit( $uid ) {
	if ( ! is_user_logged_in() || ! echo_user_config( 'user_allow_edit', true ) ) {
		return false;
	}
	$uid = absint( $uid );
	return get_current_user_id() === $uid || current_user_can( 'edit_user', $uid );
}

/* ============================================================
 * 自定义头像
 * ============================================================ */

/**
 * 读取自定义头像数据
 *
 * @param int $uid 用户 ID。
 * @return array|null array( url, file, v ) 或 null。
 */
function echo_user_avatar_data( $uid ) {
	$data = get_user_meta( absint( $uid ), 'echo_custom_avatar', true );
	if ( is_array( $data ) && ! empty( $data['url'] ) ) {
		return $data;
	}
	return null;
}

/**
 * 全站头像优先使用自定义头像
 *
 * @param array        $args        头像参数。
 * @param mixed        $id_or_email 用户 ID / 邮箱 / WP_User / WP_Comment。
 * @return array
 */
function echo_user_filter_avatar( $args, $id_or_email ) {
	$uid = 0;
	if ( is_numeric( $id_or_email ) ) {
		$uid = absint( $id_or_email );
	} elseif ( $id_or_email instanceof WP_User ) {
		$uid = (int) $id_or_email->ID;
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$uid = absint( $id_or_email->user_id );
	} elseif ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
		$uid = absint( $id_or_email->user_id );
	} elseif ( is_string( $id_or_email ) && false === strpos( $id_or_email, '@md5.gravatar.com' ) ) {
		$by_email = get_user_by( 'email', $id_or_email );
		if ( $by_email ) {
			$uid = (int) $by_email->ID;
		}
	}

	if ( ! $uid ) {
		return $args;
	}

	// alt 留空会让无障碍读取和首字母兜底失效
	if ( empty( $args['alt'] ) ) {
		$target = get_userdata( $uid );
		if ( $target ) {
			$args['alt'] = $target->display_name;
		}
	}

	$data = echo_user_avatar_data( $uid );
	if ( $data ) {
		// 必须跟随当前页面协议：https 页面里嵌 http 图片会被浏览器按混合内容拦截
		$avatar_url = set_url_scheme( $data['url'] );

		$args['url']          = add_query_arg( 'v', isset( $data['v'] ) ? (string) $data['v'] : '1', $avatar_url );
		$args['found_avatar'] = true;
	}
	return $args;
}
add_filter( 'pre_get_avatar_data', 'echo_user_filter_avatar', 10, 2 );

/**
 * 兜底：数据最终返回前再确认一次自定义头像
 *
 * 部分插件会在 pre 阶段之后改动 url，这里以高优先级覆盖回来。
 *
 * @param array $args        头像参数。
 * @param mixed $id_or_email 用户标识。
 * @return array
 */
function echo_user_filter_avatar_data( $args, $id_or_email ) {
	static $running = false;
	if ( $running ) {
		return $args;
	}
	$running = true;
	$args    = echo_user_filter_avatar( $args, $id_or_email );
	$running = false;
	return $args;
}
add_filter( 'get_avatar_data', 'echo_user_filter_avatar_data', 9999, 2 );

/**
 * 删除自定义头像（含文件）
 *
 * @param int $uid 用户 ID。
 * @return void
 */
function echo_user_delete_avatar( $uid ) {
	$data = echo_user_avatar_data( $uid );
	if ( ! $data || empty( $data['file'] ) ) {
		delete_user_meta( $uid, 'echo_custom_avatar' );
		return;
	}

	$uploads = wp_get_upload_dir();
	$file    = wp_normalize_path( $data['file'] );
	$base    = wp_normalize_path( $uploads['basedir'] );
	if ( 0 === strpos( $file, $base . '/' ) && file_exists( $file ) ) {
		wp_delete_file( $file );
	}
	delete_user_meta( $uid, 'echo_custom_avatar' );
}

/**
 * 处理头像上传 / 恢复默认
 *
 * @param int $uid 目标用户 ID。
 * @return bool true = 无需处理或成功；false = 出错（已写入反馈）。
 */
function echo_user_handle_avatar_upload( $uid ) {
	if ( ! echo_user_config( 'user_allow_avatar', true ) ) {
		return true;
	}

	// 恢复默认头像
	if ( ! empty( $_POST['remove_avatar'] ) && '1' === (string) $_POST['remove_avatar'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		echo_user_delete_avatar( $uid );
		return true;
	}

	// 未选择文件，跳过
	if ( empty( $_FILES['echo_user_avatar'] ) || ! isset( $_FILES['echo_user_avatar']['error'] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES['echo_user_avatar']['error'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return true;
	}

	$file = $_FILES['echo_user_avatar']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		echo_user_set_feedback( 'error', __( '头像上传失败，请重试。', 'echo' ) );
		return false;
	}

	$max_kb = max( 256, min( 8192, absint( echo_user_config( 'user_avatar_max_kb', 4096 ) ) ) );
	if ( (int) $file['size'] > $max_kb * 1024 ) {
		echo_user_set_feedback( 'error', sprintf( /* translators: %d: 最大 KB */ __( '头像文件超过 %d KB 限制。', 'echo' ), $max_kb ) );
		return false;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$overrides = array(
		'test_form' => false,
		'mimes'     => array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		),
	);

	$moved = wp_handle_upload( $file, $overrides );
	if ( ! is_array( $moved ) || isset( $moved['error'] ) ) {
		echo_user_set_feedback( 'error', is_array( $moved ) && isset( $moved['error'] ) ? wp_strip_all_tags( $moved['error'] ) : __( '头像上传失败，请重试。', 'echo' ) );
		return false;
	}

	// 真实内容必须是图片
	$info = function_exists( 'getimagesize' ) ? @getimagesize( $moved['file'] ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! $info || empty( $info['mime'] ) || 0 !== strpos( (string) $info['mime'], 'image/' ) ) {
		wp_delete_file( $moved['file'] );
		echo_user_set_feedback( 'error', __( '文件不是有效的图片。', 'echo' ) );
		return false;
	}

	// 换新前删旧
	echo_user_delete_avatar( $uid );

	update_user_meta(
		$uid,
		'echo_custom_avatar',
		array(
			'url'  => set_url_scheme( esc_url_raw( $moved['url'] ) ),
			'file' => $moved['file'],
			'v'    => time(),
		)
	);

	return true;
}

/**
 * 写入反馈
 *
 * @param string $type error|notice。
 * @param string $text 文案。
 * @return void
 */
function echo_user_set_feedback( $type, $text ) {
	if ( ! isset( $GLOBALS['echo_user_feedback'] ) || ! is_array( $GLOBALS['echo_user_feedback'] ) ) {
		$GLOBALS['echo_user_feedback'] = array(
			'error'  => '',
			'notice' => '',
		);
	}
	$GLOBALS['echo_user_feedback'][ $type ] = $text;
}

/**
 * 是否有错误
 *
 * @return bool
 */
function echo_user_has_error() {
	return isset( $GLOBALS['echo_user_feedback']['error'] ) && '' !== $GLOBALS['echo_user_feedback']['error'];
}

/**
 * 处理资料提交（输出前调用）
 *
 * @param int $uid 目标用户 ID。
 * @return void
 */
function echo_user_handle_update( $uid ) {
	$uid = absint( $uid );

	if ( ! isset( $GLOBALS['echo_user_feedback'] ) || ! is_array( $GLOBALS['echo_user_feedback'] ) ) {
		$GLOBALS['echo_user_feedback'] = array(
			'error'  => '',
			'notice' => '',
		);
	}

	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo_user_set_feedback( 'notice', __( '资料已更新。', 'echo' ) );
	}

	/*
	 * 上传体积超过服务器 post_max_size 时，PHP 会丢弃整个 POST，
	 * 表现为「点了保存什么都没发生」。这里显式提示，避免静默失败。
	 */
	if ( empty( $_POST ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > 0 ) {
		echo_user_set_feedback(
			'error',
			__( '提交内容超过服务器限制，头像可能没上传成功。请换一张更小的图片，或联系管理员调大 post_max_size / upload_max_filesize。', 'echo' )
		);
		return;
	}

	if ( empty( $_POST['echo_user_action'] ) || 'update' !== sanitize_key( wp_unslash( $_POST['echo_user_action'] ) ) ) {
		return;
	}

	if ( ! echo_user_config( 'user_enabled', true ) || ! echo_user_can_edit( $uid ) ) {
		echo_user_set_feedback( 'error', __( '你没有权限修改该用户资料。', 'echo' ) );
		return;
	}

	if ( ! isset( $_POST['echo_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['echo_user_nonce'] ) ), 'echo_user_update' ) ) {
		echo_user_set_feedback( 'error', __( '页面已过期，请刷新后重试。', 'echo' ) );
		return;
	}

	if ( ! empty( $_POST['echo_user_hp'] ) ) {
		echo_user_set_feedback( 'error', __( '提交失败，请稍后重试。', 'echo' ) );
		return;
	}

	$user = get_userdata( $uid );
	if ( ! $user ) {
		echo_user_set_feedback( 'error', __( '用户不存在。', 'echo' ) );
		return;
	}

	$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
	$description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
	$user_url     = isset( $_POST['user_url'] ) ? esc_url_raw( wp_unslash( $_POST['user_url'] ) ) : '';

	if ( '' === trim( $display_name ) ) {
		echo_user_set_feedback( 'error', __( '昵称不能为空。', 'echo' ) );
		return;
	}
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $display_name ) > 50 ) {
		echo_user_set_feedback( 'error', __( '昵称不能超过 50 个字符。', 'echo' ) );
		return;
	}

	// 头像上传 / 恢复默认
	if ( ! echo_user_handle_avatar_upload( $uid ) ) {
		return;
	}

	$args = array(
		'ID'           => $uid,
		'display_name' => $display_name,
		'description'  => $description,
		'user_url'     => $user_url,
	);

	// 密码修改
	$change_password = false;
	$current_pass    = isset( $_POST['current_pass'] ) ? (string) $_POST['current_pass'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$new_pass        = isset( $_POST['new_pass'] ) ? (string) $_POST['new_pass'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$confirm_pass    = isset( $_POST['confirm_pass'] ) ? (string) $_POST['confirm_pass'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( '' !== $new_pass || '' !== $confirm_pass || '' !== $current_pass ) {
		if ( ! echo_user_config( 'user_allow_password', true ) ) {
			echo_user_set_feedback( 'error', __( '本站未开放前台修改密码。', 'echo' ) );
			return;
		}
		if ( '' === $current_pass || '' === $new_pass || '' === $confirm_pass ) {
			echo_user_set_feedback( 'error', __( '修改密码需要填写当前密码、新密码与确认密码。', 'echo' ) );
			return;
		}
		if ( ! wp_check_password( $current_pass, $user->user_pass, $uid ) ) {
			echo_user_set_feedback( 'error', __( '当前密码不正确。', 'echo' ) );
			return;
		}
		if ( $new_pass !== $confirm_pass ) {
			echo_user_set_feedback( 'error', __( '两次输入的新密码不一致。', 'echo' ) );
			return;
		}

		$min = function_exists( 'echo_auth_config' ) ? absint( echo_auth_config( 'auth_password_min', 6 ) ) : 6;
		$min = max( 6, min( 32, $min ) );
		if ( strlen( $new_pass ) < $min ) {
			echo_user_set_feedback( 'error', sprintf( /* translators: %d: 最小密码长度 */ __( '新密码至少需要 %d 位。', 'echo' ), $min ) );
			return;
		}

		$args['user_pass'] = $new_pass;
		$change_password   = true;
	}

	$result = wp_update_user( $args );

	if ( is_wp_error( $result ) ) {
		echo_user_set_feedback( 'error', wp_strip_all_tags( $result->get_error_message() ) );
		return;
	}

	// 密码变更后保持当前设备登录
	if ( $change_password && get_current_user_id() === $uid ) {
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, true, is_ssl() );
	}

	wp_safe_redirect( add_query_arg( 'updated', '1', echo_user_url( $uid ) ) );
	exit;
}

/**
 * 输出反馈
 *
 * @return void
 */
function echo_user_render_feedback() {
	$fb = isset( $GLOBALS['echo_user_feedback'] ) && is_array( $GLOBALS['echo_user_feedback'] )
		? $GLOBALS['echo_user_feedback']
		: array( 'error' => '', 'notice' => '' );

	if ( ! empty( $fb['error'] ) ) {
		printf( '<div class="echo-user-alert echo-user-alert--error" role="alert">%s</div>', esc_html( $fb['error'] ) );
	}
	if ( ! empty( $fb['notice'] ) ) {
		printf( '<div class="echo-user-alert echo-user-alert--success" role="status">%s</div>', esc_html( $fb['notice'] ) );
	}
}

/**
 * 资料卡
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_user_section_profile( $user ) {
	$size   = max( 32, min( 256, absint( echo_user_config( 'user_avatar_size', 96 ) ) ) );
	$bio    = get_user_meta( $user->ID, 'description', true );
	$site   = $user->user_url;
	$is_me  = get_current_user_id() === (int) $user->ID;
	$my_url = function_exists( 'echo_my_page_url' ) ? echo_my_page_url() : '';
	?>
	<section class="echo-user-card echo-user-profile">
		<div class="echo-user-profile__head">
			<div class="echo-user-profile__avatar" style="--echo-avatar-size:<?php echo (int) $size; ?>px"><?php echo get_avatar( $user->ID, $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="echo-user-profile__id">
				<h1 class="echo-user-profile__name"><?php echo esc_html( $user->display_name ); ?></h1>
				<p class="echo-user-profile__meta">
					<span>@<?php echo esc_html( $user->user_login ); ?></span>
					<span class="echo-user-profile__uid">UID <?php echo esc_html( (string) $user->ID ); ?></span>
					<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?><?php esc_html_e( ' 加入', 'echo' ); ?></span>
				</p>
			</div>
		</div>

		<?php if ( $bio ) : ?>
			<p class="echo-user-profile__bio"><?php echo esc_html( $bio ); ?></p>
		<?php endif; ?>

		<div class="echo-user-profile__links">
			<?php if ( $site ) : ?>
				<a class="echo-user-link" href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $site ); ?></a>
			<?php endif; ?>
			<?php if ( $is_me && echo_user_can_edit( $user->ID ) ) : ?>
				<a class="echo-user-link" href="#echo-user-edit"><?php esc_html_e( '编辑资料', 'echo' ); ?></a>
			<?php endif; ?>
			<?php if ( $is_me && $my_url ) : ?>
				<a class="echo-user-link" href="<?php echo esc_url( $my_url ); ?>"><?php esc_html_e( '个人中心', 'echo' ); ?></a>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * 数据统计
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_user_section_stats( $user ) {
	$posts    = (int) count_user_posts( $user->ID, 'post', true );
	$comments = (int) get_comments(
		array(
			'user_id' => $user->ID,
			'status'  => 'approve',
			'count'   => true,
		)
	);
	?>
	<section class="echo-user-card echo-user-stats">
		<div class="echo-user-stats__grid">
			<div class="echo-user-stats__item">
				<span class="echo-user-stats__value"><?php echo esc_html( (string) $posts ); ?></span>
				<span class="echo-user-stats__label"><?php esc_html_e( '文章', 'echo' ); ?></span>
			</div>
			<div class="echo-user-stats__item">
				<span class="echo-user-stats__value"><?php echo esc_html( (string) $comments ); ?></span>
				<span class="echo-user-stats__label"><?php esc_html_e( '评论', 'echo' ); ?></span>
			</div>
			<div class="echo-user-stats__item">
				<span class="echo-user-stats__value"><?php echo esc_html( 'UID ' . $user->ID ); ?></span>
				<span class="echo-user-stats__label"><?php esc_html_e( '用户编号', 'echo' ); ?></span>
			</div>
		</div>
	</section>
	<?php
}

/**
 * 最近文章
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_user_section_posts( $user ) {
	$count = max( 0, min( 20, absint( echo_user_config( 'user_recent_posts', 5 ) ) ) );
	if ( 0 === $count ) {
		return;
	}

	$posts = get_posts(
		array(
			'author'      => $user->ID,
			'numberposts' => $count,
			'post_type'   => 'post',
			'post_status' => 'publish',
		)
	);
	?>
	<section class="echo-user-card echo-user-list">
		<h2 class="echo-user-card__title"><?php esc_html_e( '最近文章', 'echo' ); ?></h2>
		<?php if ( empty( $posts ) ) : ?>
			<p class="echo-user-empty"><?php esc_html_e( '还没有发布文章。', 'echo' ); ?></p>
		<?php else : ?>
			<ul class="echo-user-list__items">
				<?php foreach ( $posts as $item ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $item ) ); ?>"><?php echo esc_html( get_the_title( $item ) ); ?></a>
						<span class="echo-user-list__date"><?php echo esc_html( get_the_date( '', $item ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * 最近评论
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_user_section_comments( $user ) {
	$count = max( 0, min( 20, absint( echo_user_config( 'user_recent_comments', 5 ) ) ) );
	if ( 0 === $count ) {
		return;
	}

	$comments = get_comments(
		array(
			'user_id' => $user->ID,
			'status'  => 'approve',
			'number'  => $count,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);
	?>
	<section class="echo-user-card echo-user-list">
		<h2 class="echo-user-card__title"><?php esc_html_e( '最近评论', 'echo' ); ?></h2>
		<?php if ( empty( $comments ) ) : ?>
			<p class="echo-user-empty"><?php esc_html_e( '还没有发表评论。', 'echo' ); ?></p>
		<?php else : ?>
			<ul class="echo-user-list__items echo-user-list__items--comments">
				<?php foreach ( $comments as $comment ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $comment->comment_post_ID ) ); ?>"><?php echo esc_html( get_the_title( $comment->comment_post_ID ) ); ?></a>
						<span class="echo-user-list__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 24, '…' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * 自助编辑表单
 *
 * @param WP_User $user 用户对象。
 * @return void
 */
function echo_user_section_edit( $user ) {
	if ( ! echo_user_can_edit( $user->ID ) ) {
		return;
	}

	$bio  = get_user_meta( $user->ID, 'description', true );
	$open = echo_user_has_error() ? ' open' : '';
	?>
	<section class="echo-user-card echo-user-edit-card" id="echo-user-edit">
		<details class="echo-user-edit"<?php echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<summary class="echo-user-edit__summary"><?php esc_html_e( '编辑资料', 'echo' ); ?></summary>

			<form class="echo-user-form" method="post" action="<?php echo esc_url( echo_user_url( $user->ID ) ); ?>" enctype="multipart/form-data" novalidate>
				<?php wp_nonce_field( 'echo_user_update', 'echo_user_nonce' ); ?>
				<input type="hidden" name="echo_user_action" value="update" />

					<?php if ( echo_user_config( 'user_allow_avatar', true ) ) : ?>
					<?php
					$custom_avatar = echo_user_avatar_data( $user->ID );
					$social_avatar = ( ! $custom_avatar && function_exists( 'echo_social_effective_avatar' ) )
						? echo_social_effective_avatar( null, $user->ID )
						: null;
					$current_avatar = $custom_avatar ? $custom_avatar : $social_avatar;
					$is_social      = ! $custom_avatar && $social_avatar;
					?>
					<fieldset class="echo-user-fieldset echo-user-avatar-fieldset">
						<legend><?php esc_html_e( '头像', 'echo' ); ?></legend>
						<?php if ( $current_avatar ) : ?>
							<div class="echo-user-avatar-row">
								<img class="echo-user-avatar-row__img"
									src="<?php echo esc_url( add_query_arg( 'v', isset( $current_avatar['v'] ) ? (string) $current_avatar['v'] : '1', set_url_scheme( $current_avatar['url'] ) ) ); ?>"
									alt="<?php esc_attr_e( '当前头像', 'echo' ); ?>" />
								<span class="echo-user-hint">
									<?php if ( $is_social ) : ?>
										<?php esc_html_e( '当前使用社交账号头像（来自聚合登录）。上传新头像可覆盖。', 'echo' ); ?>
									<?php else : ?>
										<?php esc_html_e( '当前使用自定义头像。', 'echo' ); ?>
									<?php endif; ?>
								</span>
							</div>
							<?php if ( ! $is_social ) : ?>
								<label class="echo-user-check">
									<input type="checkbox" name="remove_avatar" value="1" />
									<?php esc_html_e( '恢复默认头像（Gravatar）', 'echo' ); ?>
								</label>
							<?php endif; ?>
						<?php else : ?>
							<p class="echo-user-hint"><?php esc_html_e( '当前使用 Gravatar 头像（基于你的邮箱）。', 'echo' ); ?></p>
						<?php endif; ?>
						<p class="echo-user-field">
							<label for="echo-user-avatar"><?php esc_html_e( '上传新头像', 'echo' ); ?></label>
							<?php $echo_max_kb = max( 256, min( 8192, absint( echo_user_config( 'user_avatar_max_kb', 4096 ) ) ) ); ?>
							<input id="echo-user-avatar" name="echo_user_avatar" type="file"
								accept="image/jpeg,image/png,image/gif,image/webp"
								data-max-kb="<?php echo esc_attr( (string) $echo_max_kb ); ?>" />
							<span class="echo-user-hint echo-user-hint--avatar">
								<?php
								printf(
									/* translators: %d: 最大 KB */
									esc_html__( '支持 JPG / PNG / GIF / WebP，最大 %d KB；不更换头像请保持不选择文件。', 'echo' ),
									esc_html( (string) $echo_max_kb )
								);
								?>
							</span>
							<span class="echo-user-hint echo-user-hint--avatar-error" role="alert" hidden></span>
						</p>
					</fieldset>
				<?php endif; ?>

				<p class="echo-user-field">
					<label for="echo-user-display"><?php esc_html_e( '昵称', 'echo' ); ?></label>
					<input id="echo-user-display" name="display_name" type="text" required
						value="<?php echo esc_attr( $user->display_name ); ?>" />
					<span class="echo-user-hint"><?php esc_html_e( '公开显示的名字，最多 50 个字符。', 'echo' ); ?></span>
				</p>

				<p class="echo-user-field">
					<label for="echo-user-bio"><?php esc_html_e( '个人简介', 'echo' ); ?></label>
					<textarea id="echo-user-bio" name="description" rows="4"><?php echo esc_textarea( $bio ); ?></textarea>
				</p>

				<p class="echo-user-field">
					<label for="echo-user-url"><?php esc_html_e( '个人网站', 'echo' ); ?></label>
					<input id="echo-user-url" name="user_url" type="url" placeholder="https://"
						value="<?php echo esc_attr( $user->user_url ); ?>" />
				</p>

				<?php if ( echo_user_config( 'user_allow_password', true ) ) : ?>
					<fieldset class="echo-user-fieldset">
						<legend><?php esc_html_e( '修改密码', 'echo' ); ?></legend>
						<p class="echo-user-hint"><?php esc_html_e( '不修改密码请留空。', 'echo' ); ?></p>

						<p class="echo-user-field">
							<label for="echo-user-current"><?php esc_html_e( '当前密码', 'echo' ); ?></label>
							<input id="echo-user-current" name="current_pass" type="password" autocomplete="current-password" />
						</p>
						<p class="echo-user-field">
							<label for="echo-user-new"><?php esc_html_e( '新密码', 'echo' ); ?></label>
							<input id="echo-user-new" name="new_pass" type="password" autocomplete="new-password" />
						</p>
						<p class="echo-user-field">
							<label for="echo-user-confirm"><?php esc_html_e( '确认新密码', 'echo' ); ?></label>
							<input id="echo-user-confirm" name="confirm_pass" type="password" autocomplete="new-password" />
						</p>
					</fieldset>
				<?php endif; ?>

				<input class="echo-user-hp" type="text" name="echo_user_hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

				<div class="echo-user-form__actions">
					<button type="submit" class="echo-btn"><?php esc_html_e( '保存修改', 'echo' ); ?></button>
					<a class="echo-user-link" href="<?php echo esc_url( echo_user_url( $user->ID ) ); ?>"><?php esc_html_e( '取消', 'echo' ); ?></a>
				</div>
			</form>
		</details>
	</section>
	<?php
}

/**
 * 页面主体
 *
 * @param int $uid 用户 ID。
 * @return void
 */
function echo_user_render( $uid ) {
	$uid  = absint( $uid );
	$user = get_userdata( $uid );
	if ( ! $user || ! echo_user_config( 'user_enabled', true ) ) {
		echo '<p class="echo-user-empty">' . esc_html__( '用户不存在。', 'echo' ) . '</p>';
		return;
	}

	$layout = echo_user_config( 'user_card_style', 'auto' );
	?>
	<div class="echo-user echo-user--<?php echo esc_attr( $layout ); ?>">
		<?php
		echo_user_render_feedback();
		echo_user_section_profile( $user );

		if ( echo_user_config( 'user_show_stats', true ) ) {
			echo_user_section_stats( $user );
		}
		if ( echo_user_config( 'user_show_posts', true ) ) {
			echo_user_section_posts( $user );
		}
		if ( echo_user_config( 'user_show_comments', true ) ) {
			echo_user_section_comments( $user );
		}

		echo_user_section_edit( $user );
		?>
	</div>
	<?php
}

/**
 * 整页输出
 *
 * @return void
 */
function echo_user_render_page() {
	$uid = echo_user_current_uid();
	echo_user_handle_update( $uid );

	if ( ! get_userdata( $uid ) || ! echo_user_config( 'user_enabled', true ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	get_header();
	?>
	<main id="primary" class="echo-main">
		<div class="echo-container">
			<div class="echo-layout<?php echo echo_user_config( 'user_show_sidebar', false ) ? '' : ' echo-layout--full'; ?>">
				<div class="echo-content">
					<?php echo_user_render( $uid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php
				if ( echo_user_config( 'user_show_sidebar', false ) ) {
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
 * 清洗
 * ============================================================ */

/**
 * 清洗设置
 *
 * @param array $input  原始提交。
 * @param array $output 已清洗的输出。
 * @return void
 */
function echo_sanitize_user_settings( $input, &$output ) {
	$defaults = echo_user_defaults();

	$has_user = false;
	foreach ( array_keys( $defaults ) as $user_key ) {
		if ( array_key_exists( $user_key, $input ) ) {
			$has_user = true;
			break;
		}
	}
	if ( ! $has_user ) {
		foreach ( $defaults as $user_key => $user_value ) {
			$output[ $user_key ] = echo_get_setting( $user_key, $user_value );
		}
		return;
	}

	$bool_keys = array(
		'user_enabled',
		'user_allow_edit',
		'user_allow_password',
		'user_allow_avatar',
		'user_show_stats',
		'user_show_posts',
		'user_show_comments',
		'user_show_sidebar',
	);
	foreach ( $bool_keys as $bool_key ) {
		$output[ $bool_key ] = isset( $input[ $bool_key ] ) && '1' === (string) $input[ $bool_key ];
	}

	$base = isset( $input['user_base'] ) ? sanitize_title( $input['user_base'] ) : '';
	$output['user_base'] = '' !== $base ? $base : 'uid';

	$output['user_avatar_size'] = max( 32, min( 256, absint( isset( $input['user_avatar_size'] ) ? $input['user_avatar_size'] : $defaults['user_avatar_size'] ) ) );

	$output['user_avatar_max_kb'] = max( 256, min( 8192, absint( isset( $input['user_avatar_max_kb'] ) ? $input['user_avatar_max_kb'] : $defaults['user_avatar_max_kb'] ) ) );

	$clamp = function ( $value, $max ) {
		$n = absint( $value );
		return $n > $max ? $max : $n;
	};
	$output['user_recent_posts']    = $clamp( isset( $input['user_recent_posts'] ) ? $input['user_recent_posts'] : 5, 20 );
	$output['user_recent_comments'] = $clamp( isset( $input['user_recent_comments'] ) ? $input['user_recent_comments'] : 5, 20 );

	$layout = isset( $input['user_card_style'] ) ? $input['user_card_style'] : 'auto';
	$output['user_card_style'] = in_array( $layout, array( 'auto', 'classic', 'miuix' ), true ) ? $layout : 'auto';

	$custom_css = isset( $input['user_custom_css'] ) ? wp_strip_all_tags( $input['user_custom_css'] ) : '';
	$custom_css = preg_replace( '/<\s*\/?\s*style\b[^>]*>/i', '', $custom_css );
	$output['user_custom_css'] = $custom_css;
}

/**
 * 自定义 CSS
 *
 * @return string
 */
function echo_user_css() {
	$custom = trim( (string) echo_user_config( 'user_custom_css', '' ) );
	return '' !== $custom ? $custom : '';
}
