<?php
/**
 * 前台发文章
 *
 * 让登录用户无需进入 wp-admin 即可在前台撰写、编辑自己的文章。
 *
 * - 路由：^<base>/?$                 → index.php?echo_write=1        （写文章）
 *         ^<base>/edit/([0-9]+)/?$   → index.php?echo_write=1&echo_edit=ID（编辑）
 * - 页面：template-write.php，通过 template_include 载入
 * - 提交：模板内服务端处理 + nonce 校验 + PRG 重定向
 * - 权限：写入需 edit_posts；默认状态可配置为草稿 / 待审核 / 直接发布
 * - 选项统一存入 echo_settings。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 内联图标（feather 风格，避免依赖外部图标库）
 * ============================================================ */

if ( ! function_exists( 'echo_front_post_icon' ) ) {
	/**
	 * 输出内联 SVG 图标
	 *
	 * @param string $name 图标名称。
	 */
	function echo_front_post_icon( $name ) {
		static $paths = array(
			'pen'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
			'file'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
			'sliders' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
			'image'   => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
			'list'    => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
			'lock'    => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
			'plus'    => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
			'send'    => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
			'info'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
			'check'   => '<polyline points="20 6 9 17 4 12"/>',
			'upload'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
			'trash'   => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return;
		}
		echo '<svg class="echo-fp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/* ============================================================
 * 默认设置与读取
 * ============================================================ */

/**
 * 默认设置
 *
 * @return array
 */
function echo_front_post_defaults() {
	return array(
		'fp_enabled'        => true,
		'fp_base'           => 'write',
		'fp_default_status' => 'pending',
		'fp_allow_status'   => array( 'draft', 'pending' ),
		'fp_allow_category' => true,
		'fp_allow_tags'     => true,
		'fp_allow_thumbnail' => true,
		'fp_thumbnail_max_kb' => 4096,
		'fp_content_min'    => 0,
		'fp_nav'            => true,
		'fp_nav_label'      => __( '写文章', 'echo' ),
	);
}

/**
 * 读取设置
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_front_post_config( $key = null, $default = null ) {
	$settings = echo_get_settings();
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
function echo_front_post_base() {
	$base = sanitize_title( echo_front_post_config( 'fp_base', 'write' ) );
	return '' !== $base ? $base : 'write';
}

/**
 * 写文章页面地址
 *
 * 固定链接为「朴素」时退回查询参数，保证链接可用。
 *
 * @param int $edit_id 文章 ID，>0 时为编辑链接。
 * @return string
 */
function echo_front_post_url( $edit_id = 0 ) {
	$edit_id = absint( $edit_id );
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		$url = add_query_arg( 'echo_write', '1', home_url( '/' ) );
		if ( $edit_id ) {
			$url = add_query_arg( 'echo_edit', $edit_id, $url );
		}
		return $url;
	}
	$url = home_url( '/' . echo_front_post_base() . '/' );
	if ( $edit_id ) {
		$url = home_url( '/' . echo_front_post_base() . '/edit/' . $edit_id . '/' );
	}
	return $url;
}

/* ============================================================
 * 路由与模板
 * ============================================================ */

/**
 * 注册重写规则
 *
 * @return void
 */
function echo_front_post_register_rewrite() {
	add_rewrite_tag( '%echo_write%', '([0-9]+)' );
	add_rewrite_tag( '%echo_edit%', '([0-9]+)' );
	$base = preg_quote( echo_front_post_base(), '#' );
	add_rewrite_rule(
		'^' . $base . '/edit/([0-9]+)/?$',
		'index.php?echo_write=1&echo_edit=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'^' . $base . '/?$',
		'index.php?echo_write=1',
		'top'
	);
}
add_action( 'init', 'echo_front_post_register_rewrite' );

/**
 * 当前请求是否为写文章页
 *
 * @return bool
 */
function echo_front_post_is_page() {
	return (bool) get_query_var( 'echo_write' );
}

/**
 * 当前请求要编辑的文章 ID
 *
 * @return int
 */
function echo_front_post_edit_id() {
	$id = get_query_var( 'echo_edit' );
	if ( '' === $id && isset( $_GET['echo_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = wp_unslash( $_GET['echo_edit'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return absint( $id );
}

/**
 * 载入写文章模板
 *
 * @param string $template 原模板。
 * @return string
 */
function echo_front_post_template_include( $template ) {
	if ( ! echo_front_post_is_page() ) {
		return $template;
	}
	$custom = locate_template( 'template-write.php' );
	return $custom ? $custom : $template;
}
add_filter( 'template_include', 'echo_front_post_template_include' );

/**
 * 功能关闭或路由无效时返回 404
 *
 * @return void
 */
function echo_front_post_maybe_404() {
	if ( ! echo_front_post_is_page() ) {
		return;
	}
	if ( echo_front_post_config( 'fp_enabled', true ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'echo_front_post_maybe_404' );

/**
 * 写文章页不参与 canonical 重定向
 *
 * @param string|false $redirect 目标地址。
 * @return string|false
 */
function echo_front_post_disable_canonical( $redirect ) {
	if ( echo_front_post_is_page() ) {
		return false;
	}
	return $redirect;
}
add_filter( 'redirect_canonical', 'echo_front_post_disable_canonical' );

/**
 * 页面标题
 *
 * @param array $parts 标题片段。
 * @return array
 */
function echo_front_post_document_title( $parts ) {
	if ( ! echo_front_post_is_page() ) {
		return $parts;
	}
	$parts['title'] = echo_front_post_edit_id()
		? __( '编辑文章', 'echo' )
		: __( '写文章', 'echo' );
	return $parts;
}
add_filter( 'document_title_parts', 'echo_front_post_document_title' );

/**
 * body class
 *
 * @param array $classes 已有 class。
 * @return array
 */
function echo_front_post_body_class( $classes ) {
	if ( echo_front_post_is_page() ) {
		$classes[] = 'echo-write-page';
	}
	return $classes;
}
add_filter( 'body_class', 'echo_front_post_body_class' );

/**
 * 首次启用 / 切换主题 / 修改前缀后刷新重写规则
 *
 * @return void
 */
function echo_front_post_flush_rewrite() {
	flush_rewrite_rules( false );
	update_option( 'echo_front_post_rewrite_flag', ECHO_VERSION . '|' . echo_front_post_base() );
}
add_action( 'after_switch_theme', 'echo_front_post_flush_rewrite' );

/**
 * 后台加载时按版本 / 前缀补一次刷新
 *
 * @return void
 */
function echo_front_post_maybe_flush() {
	$flag = get_option( 'echo_front_post_rewrite_flag' );
	if ( $flag === ECHO_VERSION . '|' . echo_front_post_base() ) {
		return;
	}
	echo_front_post_flush_rewrite();
}
add_action( 'admin_init', 'echo_front_post_maybe_flush', 21 );

/* ============================================================
 * 权限
 * ============================================================ */

/**
 * 是否有权在前台写文章
 *
 * @return bool
 */
function echo_front_post_can_write() {
	if ( ! echo_front_post_config( 'fp_enabled', true ) ) {
		return false;
	}
	return is_user_logged_in() && current_user_can( 'edit_posts' );
}

/**
 * 是否有权编辑指定文章
 *
 * 仅作者本人，或具备 edit_others_posts 权限者。
 *
 * @param int $post_id 文章 ID。
 * @return bool
 */
function echo_front_post_can_edit( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || ! echo_front_post_can_write() ) {
		return false;
	}
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || 'trash' === $post->post_status ) {
		return false;
	}
	if ( (int) $post->post_author === get_current_user_id() ) {
		return current_user_can( 'edit_post', $post_id );
	}
	return current_user_can( 'edit_others_posts', $post_id ) && current_user_can( 'edit_post', $post_id );
}

/**
 * 是否为前台内容管理员（编辑 / 管理员）
 *
 * 具备「编辑他人文章 + 发布文章 + 删除他人文章」三项能力者，
 * 可在前台管理全站文章：直接发布、编辑、删除。
 *
 * @return bool
 */
function echo_front_post_is_manager() {
	return is_user_logged_in()
		&& current_user_can( 'edit_others_posts' )
		&& current_user_can( 'publish_posts' )
		&& current_user_can( 'delete_others_posts' );
}

/**
 * 是否有权删除指定文章
 *
 * 作者本人可删除自己的文章；管理员可删除他人的文章。
 * 删除采用移入回收站（非强制删除），可在后台还原。
 *
 * @param int $post_id 文章 ID。
 * @return bool
 */
function echo_front_post_can_delete( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || ! echo_front_post_can_write() ) {
		return false;
	}
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || 'trash' === $post->post_status ) {
		return false;
	}
	if ( (int) $post->post_author === get_current_user_id() ) {
		return current_user_can( 'delete_post', $post_id );
	}
	return current_user_can( 'delete_others_posts' ) && current_user_can( 'delete_post', $post_id );
}

/**
 * 当前用户可用的发布状态
 *
 * @return array 状态 => 名称。
 */
function echo_front_post_status_options() {
	$all = array(
		'publish' => __( '直接发布', 'echo' ),
		'pending' => __( '提交审核', 'echo' ),
		'draft'   => __( '存为草稿', 'echo' ),
	);
	$allowed = echo_front_post_config( 'fp_allow_status', array( 'draft', 'pending' ) );
	if ( ! is_array( $allowed ) ) {
		$allowed = array( 'draft', 'pending' );
	}

	// 管理员始终拥有「直接发布」选项（不受后台勾选限制）。
	if ( echo_front_post_is_manager() ) {
		$allowed[] = 'publish';
		$allowed   = array_values( array_unique( $allowed ) );
	}

	$options = array();
	foreach ( $all as $key => $label ) {
		if ( ! in_array( $key, $allowed, true ) ) {
			continue;
		}
		// 直接发布需要对应权限；无权限时该项由提交逻辑降级为「提交审核」。
		$options[ $key ] = $label;
	}
	if ( empty( $options ) ) {
		$options = array( 'pending' => __( '提交审核', 'echo' ) );
	}
	return $options;
}

/* ============================================================
 * 反馈与提交处理
 * ============================================================ */

/**
 * 写入反馈
 *
 * @param string $type error|notice。
 * @param string $text 文案。
 * @return void
 */
function echo_front_post_set_feedback( $type, $text ) {
	if ( ! isset( $GLOBALS['echo_front_post_feedback'] ) || ! is_array( $GLOBALS['echo_front_post_feedback'] ) ) {
		$GLOBALS['echo_front_post_feedback'] = array(
			'error'  => '',
			'notice' => '',
		);
	}
	$GLOBALS['echo_front_post_feedback'][ $type ] = $text;
}

/**
 * 输出反馈
 *
 * @return void
 */
function echo_front_post_render_feedback() {
	$fb = isset( $GLOBALS['echo_front_post_feedback'] ) && is_array( $GLOBALS['echo_front_post_feedback'] )
		? $GLOBALS['echo_front_post_feedback']
		: array( 'error' => '', 'notice' => '' );

	if ( '' !== $fb['error'] ) {
		printf( '<div class="echo-write-alert echo-write-alert--error" role="alert">%s</div>', esc_html( $fb['error'] ) );
	}
	if ( '' !== $fb['notice'] ) {
		printf( '<div class="echo-write-alert echo-write-alert--success" role="status">%s</div>', esc_html( $fb['notice'] ) );
	}
}

/**
 * 处理封面上传
 *
 * @param int $post_id 文章 ID。
 * @return true|string true 表示无需处理或成功；字符串为错误信息。
 */
function echo_front_post_handle_thumbnail( $post_id ) {
	if ( ! echo_front_post_config( 'fp_allow_thumbnail', true ) ) {
		return true;
	}

	// 移除封面
	if ( isset( $_POST['echo_fp_remove_thumb'] ) && '1' === (string) $_POST['echo_fp_remove_thumb'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		delete_post_thumbnail( $post_id );
		return true;
	}

	if ( empty( $_FILES['echo_fp_thumb'] ) || ! isset( $_FILES['echo_fp_thumb']['error'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return true;
	}
	$file = $_FILES['echo_fp_thumb']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
		return true;
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return __( '封面上传失败，请重试。', 'echo' );
	}

	$max_kb = max( 256, min( 8192, absint( echo_front_post_config( 'fp_thumbnail_max_kb', 4096 ) ) ) );
	if ( (int) $file['size'] > $max_kb * 1024 ) {
		/* translators: %d: 最大 KB */
		return sprintf( __( '封面图片超过 %d KB 限制。', 'echo' ), $max_kb );
	}

	if ( ! function_exists( 'media_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	$attach_id = media_handle_upload( 'echo_fp_thumb', $post_id );
	if ( is_wp_error( $attach_id ) ) {
		return wp_strip_all_tags( $attach_id->get_error_message() );
	}

	set_post_thumbnail( $post_id, $attach_id );
	return true;
}

/**
 * 处理文章提交
 *
 * 在模板中、输出前调用。成功时 PRG 重定向并 exit。
 *
 * @return void
 */
function echo_front_post_handle_submission() {
	if ( ! echo_front_post_can_write() ) {
		return;
	}

	if ( ! isset( $_POST['echo_fp_action'] ) || 'save' !== sanitize_key( wp_unslash( $_POST['echo_fp_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	if ( ! isset( $_POST['echo_fp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['echo_fp_nonce'] ) ), 'echo_front_post_save' ) ) {
		echo_front_post_set_feedback( 'error', __( '页面已过期，请刷新后重试。', 'echo' ) );
		return;
	}

	// 蜜罐
	if ( ! empty( $_POST['echo_fp_hp'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		echo_front_post_set_feedback( 'error', __( '提交失败，请稍后重试。', 'echo' ) );
		return;
	}

	// 服务端限制提示（post_max_size 超限会丢弃整个 POST）
	if ( empty( $_POST ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) && (int) $_SERVER['CONTENT_LENGTH'] > 0 ) {
		echo_front_post_set_feedback( 'error', __( '提交内容超过服务器限制，请减少正文或换一张更小的封面后重试。', 'echo' ) );
		return;
	}

	$edit_id   = isset( $_POST['echo_fp_id'] ) ? absint( $_POST['echo_fp_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$is_edit   = (bool) $edit_id;
	$post_id   = 0;

	if ( $is_edit ) {
		if ( ! echo_front_post_can_edit( $edit_id ) ) {
			echo_front_post_set_feedback( 'error', __( '你没有权限编辑这篇文章。', 'echo' ) );
			return;
		}
		$post_id = $edit_id;
	}

	$title   = isset( $_POST['echo_fp_title'] ) ? sanitize_text_field( wp_unslash( $_POST['echo_fp_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$content = isset( $_POST['echo_fp_content'] ) ? wp_kses_post( wp_unslash( $_POST['echo_fp_content'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$excerpt = isset( $_POST['echo_fp_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['echo_fp_excerpt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( '' === trim( $title ) ) {
		echo_front_post_set_feedback( 'error', __( '文章标题不能为空。', 'echo' ) );
		return;
	}
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $title ) > 200 ) {
		echo_front_post_set_feedback( 'error', __( '标题不能超过 200 个字符。', 'echo' ) );
		return;
	}

	$min_content = max( 0, absint( echo_front_post_config( 'fp_content_min', 0 ) ) );
	if ( $min_content > 0 ) {
		$plain = wp_strip_all_tags( $content );
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( trim( $plain ), 'UTF-8' ) : strlen( trim( $plain ) );
		if ( $len < $min_content ) {
			/* translators: %d: 最少字数 */
			echo_front_post_set_feedback( 'error', sprintf( __( '正文至少需要 %d 个字。', 'echo' ), $min_content ) );
			return;
		}
	}

	// 状态：只允许配置中的值；publish 需对应权限，否则降级为 pending。
	$statuses     = echo_front_post_status_options();
	$status       = isset( $_POST['echo_fp_status'] ) ? sanitize_key( wp_unslash( $_POST['echo_fp_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status       = isset( $statuses[ $status ] ) ? $status : echo_front_post_config( 'fp_default_status', 'pending' );
	if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
		$status = 'pending';
	}
	if ( ! isset( $statuses[ $status ] ) ) {
		$status = 'pending';
	}

	$post_args = array(
		'post_title'   => $title,
		'post_content' => $content,
		'post_excerpt' => $excerpt,
		'post_status'  => $status,
		'post_type'    => 'post',
	);

	if ( $is_edit ) {
		$post_args['ID'] = $post_id;
		$result          = wp_update_post( $post_args, true );
	} else {
		$post_args['post_author'] = get_current_user_id();
		$result                   = wp_insert_post( $post_args, true );
	}

	if ( is_wp_error( $result ) ) {
		echo_front_post_set_feedback( 'error', wp_strip_all_tags( $result->get_error_message() ) );
		return;
	}
	$post_id = (int) $result;

	// 分类
	if ( echo_front_post_config( 'fp_allow_category', true ) ) {
		$cats  = isset( $_POST['echo_fp_cats'] ) ? (array) wp_unslash( $_POST['echo_fp_cats'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$valid = array();
		foreach ( $cats as $cat ) {
			$cat = absint( $cat );
			if ( $cat && term_exists( $cat, 'category' ) ) {
				$valid[] = $cat;
			}
		}
		wp_set_post_categories( $post_id, $valid );
	}

	// 标签
	if ( echo_front_post_config( 'fp_allow_tags', true ) ) {
		$tags = isset( $_POST['echo_fp_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['echo_fp_tags'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_set_post_tags( $post_id, $tags );
	}

	// 封面：文章已保存，即使封面上传失败也不中断；用查询参数提示用户重试。
	$thumb_ok = true === echo_front_post_handle_thumbnail( $post_id );

	$redirect_args = array(
		'saved'    => $is_edit ? 'edit' : 'new',
		'thumberr' => $thumb_ok ? '0' : '1',
	);
	wp_safe_redirect( add_query_arg( $redirect_args, echo_front_post_url( $post_id ) ) );
	exit;
}

/**
 * 处理删除文章请求
 *
 * 独立 nonce 与动作名，移入回收站（可在后台还原）。
 * 成功后重定向回列表页并通过查询参数给出提示。
 *
 * @return void
 */
function echo_front_post_handle_deletion() {
	if ( ! echo_front_post_can_write() ) {
		return;
	}
	if ( ! isset( $_POST['echo_fp_action'] ) || 'delete' !== sanitize_key( wp_unslash( $_POST['echo_fp_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! isset( $_POST['echo_fp_del_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['echo_fp_del_nonce'] ) ), 'echo_front_post_delete' ) ) {
		echo_front_post_set_feedback( 'error', __( '删除请求已过期，请刷新后重试。', 'echo' ) );
		return;
	}

	$post_id = isset( $_POST['echo_fp_id'] ) ? absint( $_POST['echo_fp_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! echo_front_post_can_delete( $post_id ) ) {
		echo_front_post_set_feedback( 'error', __( '你没有权限删除这篇文章。', 'echo' ) );
		return;
	}

	$deleted = wp_trash_post( $post_id );
	if ( ! $deleted ) {
		echo_front_post_set_feedback( 'error', __( '删除失败，请稍后重试。', 'echo' ) );
		return;
	}

	wp_safe_redirect( add_query_arg( 'deleted', '1', echo_front_post_url() ) );
	exit;
}

/**
 * 处理一键发布请求（管理员）
 *
 * 将待审核 / 草稿文章直接改为已发布。
 *
 * @return void
 */
function echo_front_post_handle_publish() {
	if ( ! echo_front_post_can_write() || ! echo_front_post_is_manager() ) {
		return;
	}
	if ( ! isset( $_POST['echo_fp_action'] ) || 'publish' !== sanitize_key( wp_unslash( $_POST['echo_fp_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! isset( $_POST['echo_fp_pub_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['echo_fp_pub_nonce'] ) ), 'echo_front_post_publish' ) ) {
		echo_front_post_set_feedback( 'error', __( '发布请求已过期，请刷新后重试。', 'echo' ) );
		return;
	}

	$post_id = isset( $_POST['echo_fp_id'] ) ? absint( $_POST['echo_fp_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! echo_front_post_can_edit( $post_id ) || ! current_user_can( 'publish_posts' ) ) {
		echo_front_post_set_feedback( 'error', __( '你没有权限发布这篇文章。', 'echo' ) );
		return;
	}

	$result = wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		echo_front_post_set_feedback( 'error', wp_strip_all_tags( $result->get_error_message() ) );
		return;
	}

	wp_safe_redirect( add_query_arg( 'published', '1', echo_front_post_url() ) );
	exit;
}

/* ============================================================
 * 前台渲染辅助
 * ============================================================ */

/**
 * 当前正在编辑的文章
 *
 * @return WP_Post|null
 */
function echo_front_post_editing_post() {
	$id = echo_front_post_edit_id();
	if ( ! $id ) {
		return null;
	}
	if ( ! echo_front_post_can_edit( $id ) ) {
		return null;
	}
	return get_post( $id );
}

/**
 * 当前用户的文章列表
 *
 * @param int $limit 数量。
 * @return array
 */
function echo_front_post_my_posts( $limit = 10 ) {
	$args = array(
		'posts_per_page' => max( 1, min( 50, absint( $limit ) ) ),
		'post_type'      => 'post',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	// 管理员可查看全站文章；普通用户仅看自己的。
	if ( ! echo_front_post_is_manager() ) {
		$args['author'] = get_current_user_id();
	}
	return get_posts( $args );
}

/**
 * 状态名称
 *
 * @param string $status 状态。
 * @return string
 */
function echo_front_post_status_label( $status ) {
	$labels = array(
		'publish' => __( '已发布', 'echo' ),
		'pending' => __( '待审核', 'echo' ),
		'draft'   => __( '草稿', 'echo' ),
		'private' => __( '私密', 'echo' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/* ============================================================
 * 后台字段渲染
 * ============================================================ */

/**
 * 发布状态多选
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_fp_status( $args ) {
	$key    = $args['label_for'];
	$value  = echo_get_setting( $key, array( 'draft', 'pending' ) );
	$value  = is_array( $value ) ? $value : array();
	$all    = array(
		'draft'   => __( '存为草稿', 'echo' ),
		'pending' => __( '提交审核', 'echo' ),
		'publish' => __( '直接发布', 'echo' ),
	);
	foreach ( $all as $k => $label ) :
		?>
		<label class="echo-checkbox-row">
			<input type="checkbox" name="echo_settings[<?php echo esc_attr( $key ); ?>][]" value="<?php echo esc_attr( $k ); ?>"
				<?php checked( in_array( $k, $value, true ), true ); ?> />
			<?php echo esc_html( $label ); ?>
		</label>
		<?php
	endforeach;
	printf(
		'<p class="description">%s</p>',
		esc_html__( '勾选用户可在前台选择的状态。「直接发布」仅对具备发布权限的用户生效，其余用户自动降级为「提交审核」。至少保留一项。', 'echo' )
	);
}

/**
 * 页面别名字段（附带当前地址）
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_fp_base( $args ) {
	$key  = $args['label_for'];
	$base = sanitize_title( echo_get_setting( $key, 'write' ) );
	?>
	<input type="text" id="<?php echo esc_attr( $key ); ?>" name="echo_settings[<?php echo esc_attr( $key ); ?>]"
		value="<?php echo esc_attr( $base ); ?>" class="regular-text" />
	<p class="description">
		<?php esc_html_e( '页面地址：', 'echo' ); ?>
		<code><?php echo esc_html( echo_front_post_url() ); ?></code>
	</p>
	<?php
}

/* ============================================================
 * 清洗
 * ============================================================ */

/**
 * 清洗前台发文章设置
 *
 * @param array $input  原始提交。
 * @param array $output 已清洗的输出（按引用追加）。
 * @return void
 */
function echo_sanitize_front_post_settings( $input, &$output ) {
	$defaults = echo_front_post_defaults();

	// 旧备份不含这些字段时沿用当前值，避免误判为关闭。
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

	$output['fp_enabled'] = isset( $input['fp_enabled'] ) && '1' === (string) $input['fp_enabled'];
	$output['fp_nav']     = isset( $input['fp_nav'] ) && '1' === (string) $input['fp_nav'];

	$base = isset( $input['fp_base'] ) ? sanitize_title( $input['fp_base'] ) : '';
	$output['fp_base'] = '' !== $base ? $base : 'write';

	$status_allowed = array( 'draft', 'pending', 'publish' );
	$statuses       = array();
	if ( isset( $input['fp_allow_status'] ) && is_array( $input['fp_allow_status'] ) ) {
		foreach ( $input['fp_allow_status'] as $s ) {
			if ( in_array( $s, $status_allowed, true ) ) {
				$statuses[] = $s;
			}
		}
	}
	$statuses = array_values( array_unique( $statuses ) );
	if ( empty( $statuses ) ) {
		$statuses = array( 'pending' );
	}
	$output['fp_allow_status'] = $statuses;

	$default_status = isset( $input['fp_default_status'] ) ? sanitize_key( $input['fp_default_status'] ) : 'pending';
	$output['fp_default_status'] = in_array( $default_status, $statuses, true ) ? $default_status : $statuses[0];

	foreach ( array( 'fp_allow_category', 'fp_allow_tags', 'fp_allow_thumbnail' ) as $bool_key ) {
		$output[ $bool_key ] = isset( $input[ $bool_key ] ) && '1' === (string) $input[ $bool_key ];
	}

	$output['fp_thumbnail_max_kb'] = max( 256, min( 8192, absint( isset( $input['fp_thumbnail_max_kb'] ) ? $input['fp_thumbnail_max_kb'] : 4096 ) ) );
	$output['fp_content_min']      = max( 0, min( 5000, absint( isset( $input['fp_content_min'] ) ? $input['fp_content_min'] : 0 ) ) );

	$label = isset( $input['fp_nav_label'] ) ? sanitize_text_field( $input['fp_nav_label'] ) : '';
	$output['fp_nav_label'] = '' !== trim( $label ) ? $label : __( '写文章', 'echo' );
}

/* ============================================================
 * 导航栏入口
 * ============================================================ */

/**
 * 导航栏「写文章」入口
 *
 * @return void
 */
function echo_front_post_nav_link() {
	if ( ! echo_front_post_can_write() || ! echo_front_post_config( 'fp_nav', true ) ) {
		return;
	}
	$label = echo_front_post_config( 'fp_nav_label', __( '写文章', 'echo' ) );
	?>
	<a class="echo-write-nav-link" href="<?php echo esc_url( echo_front_post_url() ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php
}
