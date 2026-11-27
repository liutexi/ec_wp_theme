<?php
/**
 * 评论互动：点赞、楼层号、作者/置顶标识、热度排序
 *
 * 点赞数据存 comment meta（comment_like 计数 + echo_liked_comments 用户列表），
 * 复用主题的 nonce/AJAX 模式。后台可开关。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 默认设置。
 *
 * @return array
 */
function echo_cl_defaults() {
	return array(
		'cl_enable'       => true,  // 评论点赞总开关
		'cl_floor'        => true,  // 显示楼层号
		'cl_badge'        => true,  // 显示作者/管理员标识
		'cl_hot_sort'     => false, // 默认按热度排序
	);
}

/**
 * 读取设置。
 *
 * @param string $key     键。
 * @param mixed  $default 默认。
 * @return mixed
 */
function echo_cl_config( $key, $default = null ) {
	$all = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_cl_defaults();
	return isset( $all[ $key ] ) ? $all[ $key ] : $default;
}

/**
 * 是否启用评论点赞。
 *
 * @return bool
 */
function echo_cl_is_on() {
	return (bool) echo_cl_config( 'cl_enable', true );
}

/**
 * 清洗设置。
 *
 * @param array $input 原始。
 * @param array $out   输出。
 * @return void
 */
function echo_sanitize_cl_settings( $input, &$out ) {
	$out['cl_enable']   = isset( $input['cl_enable'] ) && '1' === (string) $input['cl_enable'];
	$out['cl_floor']    = isset( $input['cl_floor'] ) && '1' === (string) $input['cl_floor'];
	$out['cl_badge']    = isset( $input['cl_badge'] ) && '1' === (string) $input['cl_badge'];
	$out['cl_hot_sort'] = isset( $input['cl_hot_sort'] ) && '1' === (string) $input['cl_hot_sort'];
}

/**
 * 获取某条评论的点赞数。
 *
 * @param int $comment_id 评论 ID。
 * @return int
 */
function echo_cl_count( $comment_id ) {
	return max( 0, (int) get_comment_meta( $comment_id, 'comment_like', true ) );
}

/**
 * 当前用户是否已赞某条评论。
 *
 * @param int $comment_id 评论 ID。
 * @param int $user_id    用户 ID。
 * @return bool
 */
function echo_cl_liked( $comment_id, $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return false;
	}
	$list = get_user_meta( $user_id, 'echo_liked_comments', true );
	if ( ! is_array( $list ) ) {
		$list = array();
	}
	return in_array( (int) $comment_id, array_map( 'intval', $list ), true );
}

/**
 * 切换评论点赞（供 AJAX 调用）。
 *
 * @param int $comment_id 评论 ID。
 * @param int $user_id    用户 ID。
 * @return array|WP_Error
 */
function echo_cl_toggle( $comment_id, $user_id = 0 ) {
	$comment_id = absint( $comment_id );
	$comment    = $comment_id ? get_comment( $comment_id ) : false;
	if ( ! $comment || '1' !== (string) $comment->comment_approved ) {
		return new WP_Error( 'invalid_comment', __( '评论不存在或未通过审核。', 'echo' ) );
	}
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return new WP_Error( 'not_logged_in', __( '请先登录后再操作。', 'echo' ) );
	}

	$list = get_user_meta( $user_id, 'echo_liked_comments', true );
	if ( ! is_array( $list ) ) {
		$list = array();
	}
	$list     = array_map( 'intval', $list );
	$pos      = array_search( $comment_id, $list, true );
	$count    = echo_cl_count( $comment_id );

	if ( false !== $pos ) {
		unset( $list[ $pos ] );
		$active = false;
		$count  = max( 0, $count - 1 );
	} else {
		$list[] = $comment_id;
		$active = true;
		$count ++;
	}

	update_user_meta( $user_id, 'echo_liked_comments', array_values( $list ) );
	update_comment_meta( $comment_id, 'comment_like', $count );

	return array( 'active' => $active, 'count' => $count );
}

/**
 * AJAX：切换点赞。
 *
 * @return void
 */
function echo_cl_ajax_toggle() {
	check_ajax_referer( 'echo_cl', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( '请先登录后再操作。', 'echo' ) ) );
	}
	$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
	$result     = echo_cl_toggle( $comment_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_echo_cl_toggle', 'echo_cl_ajax_toggle' );

/**
 * 热度排序：按 comment_like meta 降序。
 *
 * @param array $args 评论查询参数。
 * @return array
 */
function echo_cl_comment_query( $args ) {
	if ( ! is_singular() ) {
		return $args;
	}
	$sort = isset( $_GET['corder'] ) ? sanitize_key( wp_unslash( $_GET['corder'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'hot' !== $sort && ! echo_cl_config( 'cl_hot_sort', false ) ) {
		return $args;
	}
	$args['orderby']  = 'meta_value_num';
	$args['meta_key'] = 'comment_like'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$args['order']    = 'DESC';
	return $args;
}
// 仅对前台评论列表生效，优先级低于用户通过 URL 显式切换。
add_filter( 'comments_template_query_args', 'echo_cl_comment_query' );

/**
 * 楼层号计数器：按 wp_list_comments 输出顺序编号。
 *
 * @return int 当前楼层（从 1 开始）。
 */
function echo_cl_floor_number() {
	static $floor = 0;
	$floor ++;
	return $floor;
}

/**
 * 渲染单条评论的点赞按钮（供 echo_comment 调用）。
 *
 * @param WP_Comment $comment 评论对象。
 * @return void
 */
function echo_cl_render_like( $comment ) {
	if ( ! echo_cl_is_on() ) {
		return;
	}
	$cid    = (int) $comment->comment_ID;
	$liked  = echo_cl_liked( $cid );
	$count  = echo_cl_count( $cid );
	$class  = 'echo-cl-like' . ( $liked ? ' is-liked' : '' );
	?>
	<button type="button" class="<?php echo esc_attr( $class ); ?>" data-cid="<?php echo esc_attr( $cid ); ?>"
		aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>"
		title="<?php esc_attr_e( '点赞这条评论', 'echo' ); ?>">
		<svg class="echo-cl-like__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
		<span class="echo-cl-like__count"><?php echo esc_html( $count > 0 ? $count : '' ); ?></span>
	</button>
	<?php
}

/**
 * 渲染楼层 / 作者标识（供 echo_comment 调用）。
 *
 * @param WP_Comment $comment 评论对象。
 * @return void
 */
function echo_cl_render_meta( $comment ) {
	// 楼层号
	if ( echo_cl_config( 'cl_floor', true ) ) {
		$floor = echo_cl_floor_number();
		echo '<span class="echo-cl-floor">#' . esc_html( $floor ) . '</span>';
	}
	// 作者 / 管理员标识
	if ( echo_cl_config( 'cl_badge', true ) ) {
		$post = get_comment( $comment );
		if ( $post && $post->user_id && user_can( $post->user_id, 'manage_options' ) ) {
			echo '<span class="echo-cl-badge echo-cl-badge--admin">' . esc_html__( '管理员', 'echo' ) . '</span>';
		} elseif ( $post && get_post( $post->comment_post_ID ) && $post->user_id === (int) get_post( $post->comment_post_ID )->post_author ) {
			echo '<span class="echo-cl-badge echo-cl-badge--author">' . esc_html__( '作者', 'echo' ) . '</span>';
		}
	}
}
