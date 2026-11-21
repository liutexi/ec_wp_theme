<?php
/**
 * 阅读互动模块：阅读量统计 / 点赞 / 收藏 / 热门排行
 *
 * - 阅读量存 post meta（_echo_views），按访客 cookie 或登录用户限时去重，防刷新刷量。
 * - 点赞 / 收藏存 user meta（echo_liked_posts / echo_favorite_posts），仅登录用户可用；
 *   文章侧计数（_echo_likes / _echo_favorites）与用户记录双向同步，取消时回退。
 * - 提供互动条、卡片阅读量、热门排行小工具与短代码，全部受后台开关控制。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 默认设置
 * ============================================================ */

/**
 * 阅读互动默认设置（合并进主题设置）。
 *
 * @return array
 */
function echo_eng_defaults() {
	return array(
		'eng_views_enable'    => true,   // 启用阅读量统计与显示
		'eng_like_enable'     => true,   // 启用点赞
		'eng_favorite_enable' => true,   // 启用收藏
		'eng_view_dedup_h'    => 12,     // 阅读量去重窗口（小时），0 表示不去重
		'eng_hot_count'       => 5,      // 热门文章短代码默认数量
		'eng_bar_position'    => 'after', // 互动条位置：before/after 正文
	);
}

/**
 * 读取阅读互动配置。
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_eng_config( $key = null, $default = null ) {
	$settings = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_eng_defaults();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/* ============================================================
 * 设置清洗
 * ============================================================ */

/**
 * 清洗阅读互动设置（由 customizer 的 echo_sanitize_settings 委托调用）。
 *
 * @param array $input  原始输入。
 * @param array $output 清洗结果（引用）。
 * @return void
 */
function echo_sanitize_eng_settings( $input, &$output ) {
	$output['eng_views_enable']    = isset( $input['eng_views_enable'] ) && '1' === (string) $input['eng_views_enable'];
	$output['eng_like_enable']     = isset( $input['eng_like_enable'] ) && '1' === (string) $input['eng_like_enable'];
	$output['eng_favorite_enable'] = isset( $input['eng_favorite_enable'] ) && '1' === (string) $input['eng_favorite_enable'];

	$dedup                    = isset( $input['eng_view_dedup_h'] ) ? absint( $input['eng_view_dedup_h'] ) : 12;
	$output['eng_view_dedup_h'] = min( 720, $dedup );

	$hot                     = isset( $input['eng_hot_count'] ) ? absint( $input['eng_hot_count'] ) : 5;
	$output['eng_hot_count'] = max( 1, min( 20, $hot ) );
}

/* ============================================================
 * 图标
 * ============================================================ */

/**
 * 输出互动相关内联 SVG 图标。
 *
 * @param string $name 图标名：eye / heart / bookmark / fire / users。
 * @return void
 */
function echo_eng_icon( $name ) {
	static $paths = array(
		'eye'      => '<path d="M1.5 12S5 5.5 12 5.5 22.5 12 22.5 12 19 18.5 12 18.5 1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/>',
		'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/>',
		'bookmark' => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
		'fire'     => '<path d="M12 2c1 3-1 4-1 6 0 1.2.8 2 2 2 1.5 0 2-1.5 2-3 2 2 3 4 3 6a6 6 0 0 1-12 0c0-3 2-5 3-7 .5 1.5 1.5 2 2 2 1 0 1.5-1 1-2-.5-1-1-2-0-4z"/>',
		'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return;
	}
	echo '<svg class="echo-eng-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/* ============================================================
 * 阅读量
 * ============================================================ */

/**
 * 获取文章阅读量。
 *
 * @param int $post_id 文章 ID。
 * @return int
 */
function echo_eng_get_views( $post_id ) {
	return max( 0, (int) get_post_meta( $post_id, '_echo_views', true ) );
}

/**
 * 访客唯一标识：登录用户用 ID，访客用随机串存 cookie。
 *
 * @return string
 */
function echo_eng_visitor_key() {
	if ( is_user_logged_in() ) {
		return 'u_' . get_current_user_id();
	}
	$cookie_name = 'echo_eng_vid';
	if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
		$vid = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		if ( preg_match( '/^[a-f0-9]{32}$/', $vid ) ) {
			return 'g_' . $vid;
		}
	}
	$vid = md5( wp_generate_uuid4() . wp_salt( 'auth' ) );
	// 立即写入 $_COOKIE，保证同一请求内多次调用也使用同一访客标识
	$_COOKIE[ $cookie_name ] = $vid;
	if ( ! headers_sent() ) {
		$expire = time() + DAY_IN_SECONDS * 180;
		setcookie( $cookie_name, $vid, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl() );
	}
	return 'g_' . $vid;
}

/**
 * 记录一次阅读（带去重，防刷新刷量）。
 *
 * @param int $post_id 文章 ID。
 * @return void
 */
function echo_eng_record_view( $post_id ) {
	if ( ! echo_eng_config( 'eng_views_enable', true ) ) {
		return;
	}
	$post_id = absint( $post_id );
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return;
	}

	$dedup_h = (int) echo_eng_config( 'eng_view_dedup_h', 12 );
	if ( $dedup_h > 0 ) {
		$key = 'echo_eng_view_' . md5( echo_eng_visitor_key() . '|' . $post_id );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, $dedup_h * HOUR_IN_SECONDS );
	}

	update_post_meta( $post_id, '_echo_views', echo_eng_get_views( $post_id ) + 1 );
}

/**
 * 单篇文章页记录阅读量。
 *
 * @return void
 */
function echo_eng_maybe_record_view() {
	if ( ! is_singular( 'post' ) || is_admin() ) {
		return;
	}
	echo_eng_record_view( get_queried_object_id() );
}
add_action( 'template_redirect', 'echo_eng_maybe_record_view' );

/* ============================================================
 * 点赞 / 收藏：用户维度存储
 * ============================================================ */

/**
 * 读取某用户的互动 ID 列表。
 *
 * @param string $type    类型：liked 或 favorite。
 * @param int    $user_id 用户 ID，0 表示当前用户。
 * @return array 文章 ID 数组。
 */
function echo_eng_user_list( $type, $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return array();
	}
	$key = 'liked' === $type ? 'echo_liked_posts' : 'echo_favorite_posts';
	$raw = get_user_meta( $user_id, $key, true );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'absint', $raw ) ) );
}

/**
 * 判断当前用户是否已点赞 / 收藏某文章。
 *
 * @param int    $post_id 文章 ID。
 * @param string $type    类型：liked 或 favorite。
 * @return bool
 */
function echo_eng_user_has( $post_id, $type ) {
	return in_array( absint( $post_id ), echo_eng_user_list( $type ), true );
}

/**
 * 获取文章侧互动计数。
 *
 * @param int    $post_id 文章 ID。
 * @param string $type    类型：likes 或 favorites。
 * @return int
 */
function echo_eng_post_count( $post_id, $type ) {
	$key = 'likes' === $type ? '_echo_likes' : '_echo_favorites';
	return max( 0, (int) get_post_meta( $post_id, $key, true ) );
}

/**
 * 切换点赞 / 收藏状态，返回最新状态与计数。
 *
 * @param int    $post_id 文章 ID。
 * @param string $type    类型：like 或 favorite。
 * @param int    $user_id 用户 ID。
 * @return array|WP_Error 成功返回 array('active'=>bool,'count'=>int)。
 */
function echo_eng_toggle( $post_id, $type, $user_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
		return new WP_Error( 'invalid_post', __( '文章不存在或未发布。', 'echo' ) );
	}
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return new WP_Error( 'not_logged_in', __( '请先登录后再操作。', 'echo' ) );
	}
	if ( 'like' !== $type && 'favorite' !== $type ) {
		return new WP_Error( 'invalid_type', __( '无效的互动类型。', 'echo' ) );
	}

	$list_key  = 'like' === $type ? 'echo_liked_posts' : 'echo_favorite_posts';
	$count_key = 'like' === $type ? '_echo_likes' : '_echo_favorites';
	$list      = echo_eng_user_list( 'like' === $type ? 'liked' : 'favorite', $user_id );
	$pos       = array_search( $post_id, $list, true );
	$count     = echo_eng_post_count( $post_id, 'like' === $type ? 'likes' : 'favorites' );

	if ( false !== $pos ) {
		unset( $list[ $pos ] );
		$active = false;
		$count  = max( 0, $count - 1 );
	} else {
		$list[] = $post_id;
		$active = true;
		$count  = $count + 1;
	}

	update_user_meta( $user_id, $list_key, array_values( $list ) );
	update_post_meta( $post_id, $count_key, $count );

	return array( 'active' => $active, 'count' => $count );
}

/* ============================================================
 * AJAX 端点
 * ============================================================ */

/**
 * 处理点赞 / 收藏 AJAX 请求。
 *
 * @return void
 */
function echo_eng_ajax_toggle() {
	check_ajax_referer( 'echo_eng', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( '请先登录后再操作。', 'echo' ) ) );
	}
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';

	$result = echo_eng_toggle( $post_id, $type );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_echo_eng_toggle', 'echo_eng_ajax_toggle' );

/* ============================================================
 * 渲染函数
 * ============================================================ */

/**
 * 文章卡片 meta 行的阅读量片段（供模板调用）。
 *
 * @param int $post_id 文章 ID，默认当前文章。
 * @return void
 */
function echo_eng_card_meta( $post_id = 0 ) {
	if ( ! echo_eng_config( 'eng_views_enable', true ) ) {
		return;
	}
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	$views   = echo_eng_get_views( $post_id );
	?>
	<span class="echo-eng-views" title="<?php esc_attr_e( '阅读量', 'echo' ); ?>">
		<?php echo_eng_icon( 'eye' ); ?>
		<?php echo esc_html( number_format_i18n( $views ) ); ?>
	</span>
	<?php
}

/**
 * 文章页互动条：阅读量 + 点赞 + 收藏。
 *
 * @param int $post_id 文章 ID，默认当前文章。
 * @return void
 */
function echo_eng_bar( $post_id = 0 ) {
	$post_id     = $post_id ? absint( $post_id ) : get_the_ID();
	$show_views  = (bool) echo_eng_config( 'eng_views_enable', true );
	$show_like   = (bool) echo_eng_config( 'eng_like_enable', true );
	$show_fav    = (bool) echo_eng_config( 'eng_favorite_enable', true );
	$logged      = is_user_logged_in();

	if ( ! $show_views && ! $show_like && ! $show_fav ) {
		return;
	}

	$views    = echo_eng_get_views( $post_id );
	$likes    = echo_eng_post_count( $post_id, 'likes' );
	$favs     = echo_eng_post_count( $post_id, 'favorites' );
	$liked    = $logged && echo_eng_user_has( $post_id, 'liked' );
	$faved    = $logged && echo_eng_user_has( $post_id, 'favorite' );
	$loginUrl = function_exists( 'echo_auth_url' ) ? echo_auth_url( 'login' ) : wp_login_url();
	?>
	<div class="echo-eng-bar" data-echo-eng data-post-id="<?php echo (int) $post_id; ?>">
		<?php if ( $show_views ) : ?>
			<span class="echo-eng-bar__stat echo-eng-views" title="<?php esc_attr_e( '阅读量', 'echo' ); ?>">
				<?php echo_eng_icon( 'eye' ); ?>
				<span><?php echo esc_html( number_format_i18n( $views ) ); ?></span>
				<span class="echo-eng-bar__unit"><?php esc_html_e( '阅读', 'echo' ); ?></span>
			</span>
		<?php endif; ?>

		<div class="echo-eng-bar__actions">
			<?php if ( $show_like ) : ?>
				<button type="button"
					class="echo-eng-btn echo-eng-btn--like<?php echo $liked ? ' is-active' : ''; ?>"
					data-eng-type="like" data-eng-active="<?php echo $liked ? '1' : '0'; ?>"
					data-eng-count="<?php echo (int) $likes; ?>"
					data-login-url="<?php echo esc_url( $loginUrl ); ?>"
					aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>">
					<?php echo_eng_icon( 'heart' ); ?>
					<span class="echo-eng-btn__label"><?php esc_html_e( '点赞', 'echo' ); ?></span>
					<span class="echo-eng-btn__count"><?php echo (int) $likes; ?></span>
				</button>
			<?php endif; ?>

			<?php if ( $show_fav ) : ?>
				<button type="button"
					class="echo-eng-btn echo-eng-btn--fav<?php echo $faved ? ' is-active' : ''; ?>"
					data-eng-type="favorite" data-eng-active="<?php echo $faved ? '1' : '0'; ?>"
					data-eng-count="<?php echo (int) $favs; ?>"
					data-login-url="<?php echo esc_url( $loginUrl ); ?>"
					aria-pressed="<?php echo $faved ? 'true' : 'false'; ?>">
					<?php echo_eng_icon( 'bookmark' ); ?>
					<span class="echo-eng-btn__label"><?php echo $faved ? esc_html__( '已收藏', 'echo' ) : esc_html__( '收藏', 'echo' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/* ============================================================
 * 热门排行
 * ============================================================ */

/**
 * 查询热门文章（按阅读量降序）。
 *
 * @param int $limit 数量。
 * @return array WP_Post 数组。
 */
function echo_eng_hot_posts( $limit = 5 ) {
	$limit = max( 1, min( 20, absint( $limit ) ) );
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'meta_key'            => '_echo_views', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'             => 'meta_value_num',
			'order'               => 'DESC',
		)
	);
	$posts = $query->posts;
	wp_reset_postdata();
	return $posts;
}

/**
 * 热门排行小工具。
 */
class Echo_Eng_Hot_Widget extends WP_Widget {

	/** 构造 */
	public function __construct() {
		parent::__construct(
			'echo_eng_hot_widget',
			__( 'Echo 热门文章', 'echo' ),
			array( 'description' => __( '按阅读量显示最热门的文章。', 'echo' ) )
		);
	}

	/**
	 * 前台输出
	 *
	 * @param array $args     侧边栏参数。
	 * @param array $instance 小工具设置。
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( ! echo_eng_config( 'eng_views_enable', true ) ) {
			return;
		}
		$title = isset( $instance['title'] ) ? $instance['title'] : __( '热门文章', 'echo' );
		$limit = isset( $instance['limit'] ) ? max( 1, min( 20, absint( $instance['limit'] ) ) ) : 5;
		$posts = echo_eng_hot_posts( $limit );
		if ( empty( $posts ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<ol class="echo-eng-hot">';
		foreach ( $posts as $post ) {
			printf(
				'<li class="echo-eng-hot__item"><a href="%1$s">%2$s</a><span class="echo-eng-hot__views">%3$s</span></li>',
				esc_url( get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				/* translators: %s: 阅读量 */
				esc_html( sprintf( __( '%s 阅读', 'echo' ), number_format_i18n( echo_eng_get_views( $post->ID ) ) ) )
			);
		}
		echo '</ol>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * 后台表单
	 *
	 * @param array $instance 当前设置。
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( '热门文章', 'echo' );
		$limit = isset( $instance['limit'] ) ? absint( $instance['limit'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( '标题：', 'echo' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( '显示条数（1–20）：', 'echo' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" max="20" step="1"
				value="<?php echo esc_attr( (string) $limit ); ?>" />
		</p>
		<?php
	}

	/**
	 * 保存
	 *
	 * @param array $new_instance 新值。
	 * @param array $old_instance 旧值。
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'limit' => max( 1, min( 20, absint( isset( $new_instance['limit'] ) ? $new_instance['limit'] : 5 ) ) ),
		);
	}
}

/**
 * 注册热门文章小工具。
 *
 * @return void
 */
function echo_eng_register_widget() {
	register_widget( 'Echo_Eng_Hot_Widget' );
}
add_action( 'widgets_init', 'echo_eng_register_widget' );

/**
 * 热门文章短代码：[echo_hot_posts count="5" title="热门文章"]。
 *
 * @param array $atts 短代码属性。
 * @return string
 */
function echo_eng_hot_shortcode( $atts ) {
	if ( ! echo_eng_config( 'eng_views_enable', true ) ) {
		return '';
	}
	$atts  = shortcode_atts(
		array(
			'count' => (int) echo_eng_config( 'eng_hot_count', 5 ),
			'title' => __( '热门文章', 'echo' ),
		),
		$atts,
		'echo_hot_posts'
	);
	$posts = echo_eng_hot_posts( $atts['count'] );
	if ( empty( $posts ) ) {
		return '';
	}
	$out = '<div class="echo-eng-hot-shortcode">';
	if ( $atts['title'] ) {
		$out .= '<h3 class="echo-eng-hot__title">' . esc_html( $atts['title'] ) . '</h3>';
	}
	$out .= '<ol class="echo-eng-hot">';
	foreach ( $posts as $post ) {
		$out .= sprintf(
			'<li class="echo-eng-hot__item"><a href="%1$s">%2$s</a><span class="echo-eng-hot__views">%3$s</span></li>',
			esc_url( get_permalink( $post ) ),
			esc_html( get_the_title( $post ) ),
			esc_html( sprintf( __( '%s 阅读', 'echo' ), number_format_i18n( echo_eng_get_views( $post->ID ) ) ) )
		);
	}
	$out .= '</ol></div>';
	return $out;
}
add_shortcode( 'echo_hot_posts', 'echo_eng_hot_shortcode' );
