<?php
/**
 * 「我的」个人中心界面 + 前端细节微调
 *
 * 所有选项都存入 echo_settings，与其他主题设置统一走主表单保存。
 * 本文件负责：默认项、模块定义、前台渲染、后台字段渲染、清洗、动态 CSS。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 附加设置项的默认值
 *
 * 由 echo_default_settings() 合并进主默认值数组。
 *
 * @return array
 */
function echo_extra_defaults() {
	return array(
		// 「我的」界面
		'my_enabled'          => true,
		'my_slug'             => 'my',
		'my_modules'          => array(),
		'my_avatar_size'      => 96,
		'my_recent_posts'     => 5,
		'my_recent_comments'  => 5,
		'my_builtin_links'    => array( 'new_post', 'edit_profile', 'admin', 'logout' ),
		'my_quicklinks'       => '',
		'my_guest_mode'       => 'login_form',
		'my_guest_message'    => '',
		'my_guest_shortcode'  => '',
		'my_show_sidebar'     => false,
		'my_card_style'       => 'auto',
		'my_custom_css'       => '',

		// 前端微调（默认值刻意贴近原有样式，开启主题后外观不变）
		'tw_font_size'        => 17,
		'tw_line_height'      => 1.85,
		'tw_entry_width'      => 0,
		'tw_card_hover'       => true,
		'tw_heading_decor'    => true,
		'tw_reading_progress' => false,
		'tw_back_to_top'      => true,
		'tw_smooth_scroll'    => true,
		'tw_nav_my'           => true,
		'tw_nav_my_label'     => '我的',
		'tw_author_avatar'    => true,
		'tw_lead_paragraph'   => true,
	);
}

/**
 * 可用的个人中心模块定义
 *
 * @return array
 */
function echo_my_modules() {
	return array(
		'profile'         => array(
			'label' => __( '资料卡', 'echo' ),
			'desc'  => __( '头像、昵称、@用户名、个人简介与站点链接', 'echo' ),
			'order' => 10,
		),
		'stats'           => array(
			'label' => __( '数据统计', 'echo' ),
			'desc'  => __( '注册时间、发布文章数、已通过评论数', 'echo' ),
			'order' => 20,
		),
		'quicklinks'      => array(
			'label' => __( '快捷入口', 'echo' ),
			'desc'  => __( '内置入口（写文章 / 编辑资料 / 后台 / 退出）与自定义链接', 'echo' ),
			'order' => 30,
		),
		'recent_posts'    => array(
			'label' => __( '最近文章', 'echo' ),
			'desc'  => __( '当前用户最新发布的文章', 'echo' ),
			'order' => 40,
		),
		'recent_comments' => array(
			'label' => __( '最近评论', 'echo' ),
			'desc'  => __( '当前用户最新发表的评论及所属文章', 'echo' ),
			'order' => 50,
		),
	);
}

/**
 * 内置的快捷入口定义
 *
 * @return array
 */
function echo_my_builtin_links() {
	return array(
		'new_post'     => array( 'label' => __( '写文章', 'echo' ), 'cap' => 'edit_posts' ),
		'edit_profile' => array( 'label' => __( '编辑资料', 'echo' ), 'cap' => 'read' ),
		'admin'        => array( 'label' => __( '后台首页', 'echo' ), 'cap' => 'read' ),
		'logout'       => array( 'label' => __( '退出登录', 'echo' ), 'cap' => 'read' ),
	);
}

/**
 * 读取一项或全部设置
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed      $default 缺省值。
 * @return mixed
 */
function echo_my_config( $key = null, $default = null ) {
	$settings = echo_get_settings();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 各模块的启用状态与排序值
 *
 * @return array
 */
function echo_my_module_state() {
	$saved = echo_my_config( 'my_modules', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$out = array();
	foreach ( echo_my_modules() as $key => $def ) {
		$row  = ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) ? $saved[ $key ] : array();
		$out[ $key ] = array(
			'on'    => array_key_exists( 'on', $row ) ? (bool) $row['on'] : true,
			'order' => isset( $row['order'] ) ? (int) $row['order'] : (int) $def['order'],
		);
	}
	return $out;
}

/**
 * 按排序值返回已启用的模块 key 列表
 *
 * @return array
 */
function echo_my_active_modules() {
	$enabled = array();
	foreach ( echo_my_module_state() as $key => $row ) {
		if ( $row['on'] ) {
			$enabled[ $key ] = $row['order'];
		}
	}
	asort( $enabled, SORT_NUMERIC );
	return array_keys( $enabled );
}

/**
 * 个人中心页面地址（按别名查找实际页面）
 *
 * @return string
 */
function echo_my_page_url() {
	$slug = sanitize_title( echo_my_config( 'my_slug', 'my' ) );
	if ( '' === $slug ) {
		$slug = 'my';
	}

	$page = get_page_by_path( $slug );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}
	return home_url( '/' . $slug . '/' );
}

/**
 * 未登录且选择「跳转登录页」时，在 template_redirect 阶段跳转
 *
 * @return void
 */
function echo_my_maybe_redirect_guest() {
	if ( is_user_logged_in() || ! is_page_template( 'template-my.php' ) ) {
		return;
	}
	if ( ! echo_my_config( 'my_enabled', true ) || 'redirect' !== echo_my_config( 'my_guest_mode', 'login_form' ) ) {
		return;
	}

	// 优先跳到主题自带的登录页，未启用时回退到 wp-login.php
	$login_url = ( function_exists( 'echo_auth_url' ) && echo_auth_config( 'auth_enabled', true ) )
		? echo_auth_url( 'login' )
		: wp_login_url( echo_my_page_url() );

	wp_safe_redirect( $login_url );
	exit;
}
add_action( 'template_redirect', 'echo_my_maybe_redirect_guest' );

/**
 * 个人中心主渲染入口
 *
 * @return void
 */
function echo_my_render() {
	if ( ! echo_my_config( 'my_enabled', true ) ) {
		echo '<p class="echo-my-notice">' . esc_html__( '个人中心已关闭，请在「Echo 设置 → 我的界面」中开启。', 'echo' ) . '</p>';
		return;
	}

	$style = echo_my_config( 'my_card_style', 'auto' );
	?>
	<div class="echo-my echo-my--<?php echo esc_attr( $style ); ?>">
		<?php
		if ( ! is_user_logged_in() ) {
			echo_my_guest();
		} else {
			foreach ( echo_my_active_modules() as $module ) {
				$callback = 'echo_my_module_' . $module;
				if ( function_exists( $callback ) ) {
					call_user_func( $callback );
				}
			}
		}
		?>
	</div>
	<?php
}

/**
 * 未登录区块
 *
 * @return void
 */
function echo_my_guest() {
	$mode      = echo_my_config( 'my_guest_mode', 'login_form' );
	$message   = echo_my_config( 'my_guest_message', '' );
	$shortcode = echo_my_config( 'my_guest_shortcode', '' );
	$url       = echo_my_page_url();
	?>
	<section class="echo-my-card echo-my-guest">
		<h2 class="echo-my-card__title"><?php esc_html_e( '登录后查看', 'echo' ); ?></h2>

		<?php if ( '' !== $message ) : ?>
			<p class="echo-my-guest__message"><?php echo esc_html( $message ); ?></p>
		<?php endif; ?>

		<?php
		if ( 'login_form' === $mode ) {
			echo wp_login_form( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'echo'           => false,
					'redirect'       => $url,
					'label_username' => __( '用户名或邮箱', 'echo' ),
					'label_password' => __( '密码', 'echo' ),
					'label_remember' => __( '记住我', 'echo' ),
					'label_log_in'   => __( '登录', 'echo' ),
					'remember'       => true,
				)
			);
		} elseif ( 'message' === $mode ) {
			$auth_on   = function_exists( 'echo_auth_url' ) && echo_auth_config( 'auth_enabled', true );
			$login_url = $auth_on ? echo_auth_url( 'login' ) : wp_login_url( $url );
			$reg_url   = $auth_on ? echo_auth_url( 'register' ) : wp_registration_url();
			$can_reg   = function_exists( 'echo_auth_registration_allowed' ) ? echo_auth_registration_allowed() : (bool) get_option( 'users_can_register' );
			?>
			<p class="echo-my-guest__actions">
				<a class="echo-btn" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( '前往登录', 'echo' ); ?></a>
				<?php if ( $can_reg ) : ?>
					<a class="echo-btn echo-btn--ghost" href="<?php echo esc_url( $reg_url ); ?>"><?php esc_html_e( '注册账号', 'echo' ); ?></a>
				<?php endif; ?>
			</p>
			<?php
		}

		if ( '' !== trim( $shortcode ) ) {
			echo '<div class="echo-my-guest__shortcode">' . do_shortcode( $shortcode ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</section>
	<?php
}

/**
 * 模块：资料卡
 *
 * @return void
 */
function echo_my_module_profile() {
	$user = wp_get_current_user();
	$size = max( 32, min( 256, absint( echo_my_config( 'my_avatar_size', 96 ) ) ) );
	$bio  = get_user_meta( $user->ID, 'description', true );
	$site = $user->user_url;

	// 公开主页地址（用户主页功能开启时）
	$profile_url = ( function_exists( 'echo_user_url' ) && echo_user_config( 'user_enabled', true ) )
		? echo_user_url( $user->ID )
		: '';
	?>
	<section class="echo-my-card echo-my-profile">
		<div class="echo-my-profile__head">
			<div class="echo-my-profile__avatar" style="--echo-avatar-size:<?php echo (int) $size; ?>px"><?php echo get_avatar( $user->ID, $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="echo-my-profile__id">
				<h2 class="echo-my-profile__name"><?php echo esc_html( $user->display_name ); ?></h2>
				<p class="echo-my-profile__handle">@<?php echo esc_html( $user->user_login ); ?></p>
				<?php if ( $site ) : ?>
					<a class="echo-my-profile__site" href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $site ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $bio ) : ?>
			<p class="echo-my-profile__bio"><?php echo esc_html( $bio ); ?></p>
		<?php endif; ?>
		<?php if ( $profile_url ) : ?>
			<p class="echo-my-profile__links">
				<a class="echo-my-profile__site" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( '查看我的主页', 'echo' ); ?></a>
			</p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * 模块：数据统计
 *
 * @return void
 */
function echo_my_module_stats() {
	$user     = wp_get_current_user();
	$posts    = (int) count_user_posts( $user->ID, 'post', true );
	$comments = (int) get_comments(
		array(
			'user_id' => $user->ID,
			'status'  => 'approve',
			'count'   => true,
		)
	);
	$joined   = date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) );

	$items = array(
		array( __( '文章', 'echo' ), $posts ),
		array( __( '评论', 'echo' ), $comments ),
		array( __( '加入时间', 'echo' ), $joined ),
	);
	?>
	<section class="echo-my-card echo-my-stats">
		<h2 class="echo-my-card__title"><?php esc_html_e( '数据统计', 'echo' ); ?></h2>
		<div class="echo-my-stats__grid">
			<?php foreach ( $items as $item ) : ?>
				<div class="echo-my-stats__item">
					<span class="echo-my-stats__value"><?php echo esc_html( (string) $item[1] ); ?></span>
					<span class="echo-my-stats__label"><?php echo esc_html( $item[0] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * 解析自定义快捷链接（每行「名称 | URL」）
 *
 * @return array
 */
function echo_my_parse_quicklinks() {
	$raw   = (string) echo_my_config( 'my_quicklinks', '' );
	$lines = preg_split( '/\r\n|\r|\n/', $raw );
	$out   = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}
		$parts = explode( '|', $line, 2 );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$label = trim( $parts[0] );
		$url   = trim( $parts[1] );
		if ( '' === $label || '' === $url ) {
			continue;
		}
		// 以 / 开头视为站内路径
		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}
		$out[] = array( 'label' => $label, 'url' => $url );
	}
	return $out;
}

/**
 * 模块：快捷入口
 *
 * @return void
 */
function echo_my_module_quicklinks() {
	$enabled = echo_my_config( 'my_builtin_links', array() );
	if ( ! is_array( $enabled ) ) {
		$enabled = array();
	}

	$defs  = echo_my_builtin_links();
	$links = array();

	foreach ( $defs as $key => $def ) {
		if ( ! in_array( $key, $enabled, true ) || ! current_user_can( $def['cap'] ) ) {
			continue;
		}
		switch ( $key ) {
			case 'new_post':
				$url = admin_url( 'post-new.php' );
				break;
			case 'edit_profile':
				$url = admin_url( 'profile.php' );
				break;
			case 'admin':
				$url = admin_url();
				break;
			case 'logout':
				$url = wp_logout_url( echo_my_page_url() );
				break;
			default:
				$url = '';
		}
		$links[] = array( 'label' => $def['label'], 'url' => $url );
	}

	$links = array_merge( $links, echo_my_parse_quicklinks() );

	if ( empty( $links ) ) {
		return;
	}
	?>
	<section class="echo-my-card echo-my-quicklinks">
		<h2 class="echo-my-card__title"><?php esc_html_e( '快捷入口', 'echo' ); ?></h2>
		<div class="echo-my-quicklinks__grid">
			<?php foreach ( $links as $link ) : ?>
				<a class="echo-my-quicklink" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * 模块：最近文章
 *
 * @return void
 */
function echo_my_module_recent_posts() {
	$count = max( 0, min( 20, absint( echo_my_config( 'my_recent_posts', 5 ) ) ) );
	if ( 0 === $count ) {
		return;
	}

	$posts = get_posts(
		array(
			'author'      => get_current_user_id(),
			'numberposts' => $count,
			'post_type'   => 'post',
			'post_status' => 'publish',
		)
	);
	?>
	<section class="echo-my-card echo-my-list">
		<h2 class="echo-my-card__title"><?php esc_html_e( '最近文章', 'echo' ); ?></h2>
		<?php if ( empty( $posts ) ) : ?>
			<p class="echo-my-empty"><?php esc_html_e( '还没有发布文章。', 'echo' ); ?></p>
		<?php else : ?>
			<ul class="echo-my-list__items">
				<?php foreach ( $posts as $post ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<span class="echo-my-list__date"><?php echo esc_html( get_the_date( '', $post ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * 模块：最近评论
 *
 * @return void
 */
function echo_my_module_recent_comments() {
	$count = max( 0, min( 20, absint( echo_my_config( 'my_recent_comments', 5 ) ) ) );
	if ( 0 === $count ) {
		return;
	}

	$comments = get_comments(
		array(
			'user_id' => get_current_user_id(),
			'status'  => 'approve',
			'number'  => $count,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		)
	);
	?>
	<section class="echo-my-card echo-my-list">
		<h2 class="echo-my-card__title"><?php esc_html_e( '最近评论', 'echo' ); ?></h2>
		<?php if ( empty( $comments ) ) : ?>
			<p class="echo-my-empty"><?php esc_html_e( '还没有发表评论。', 'echo' ); ?></p>
		<?php else : ?>
			<ul class="echo-my-list__items echo-my-list__items--comments">
				<?php foreach ( $comments as $comment ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $comment->comment_post_ID ) ); ?>"><?php echo esc_html( get_the_title( $comment->comment_post_ID ) ); ?></a>
						<span class="echo-my-list__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 24, '…' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
}

/* ============================================================
 * 后台字段渲染
 * ============================================================ */

/**
 * 通用文本字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_text( $args ) {
	$key   = $args['label_for'];
	$value = echo_get_setting( $key, '' );
	printf(
		'<input type="text" id="%1$s" name="echo_settings[%1$s]" value="%2$s" class="regular-text" />',
		esc_attr( $key ),
		esc_attr( $value )
	);
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 通用多行文本字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_textarea( $args ) {
	$key   = $args['label_for'];
	$value = echo_get_setting( $key, '' );
	$rows  = isset( $args['rows'] ) ? absint( $args['rows'] ) : 4;
	printf(
		'<textarea id="%1$s" name="echo_settings[%1$s]" rows="%2$d" class="large-text %3$s">%4$s</textarea>',
		esc_attr( $key ),
		$rows,
		esc_attr( isset( $args['class'] ) ? $args['class'] : '' ),
		esc_textarea( $value )
	);
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 通用数字字段（可选滑块）
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_number( $args ) {
	$key   = $args['label_for'];
	$value = echo_get_setting( $key, isset( $args['default'] ) ? $args['default'] : 0 );
	$min   = isset( $args['min'] ) ? $args['min'] : 0;
	$max   = isset( $args['max'] ) ? $args['max'] : 100;
	$step  = isset( $args['step'] ) ? $args['step'] : 1;
	$suffix = isset( $args['suffix'] ) ? $args['suffix'] : '';
	?>
	<input type="number" id="<?php echo esc_attr( $key ); ?>"
		name="echo_settings[<?php echo esc_attr( $key ); ?>]"
		value="<?php echo esc_attr( $value ); ?>"
		min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>"
		step="<?php echo esc_attr( $step ); ?>" class="small-text" />
	<?php if ( $suffix ) : ?>
		<span class="echo-number-suffix"><?php echo esc_html( $suffix ); ?></span>
	<?php endif; ?>
	<?php
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 通用下拉选择字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_select( $args ) {
	$key     = $args['label_for'];
	$value   = echo_get_setting( $key, '' );
	$options = isset( $args['options'] ) ? $args['options'] : array();
	?>
	<select id="<?php echo esc_attr( $key ); ?>" name="echo_settings[<?php echo esc_attr( $key ); ?>]">
		<?php foreach ( $options as $opt_value => $opt_label ) : ?>
			<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 「我的」模块清单：逐个开关 + 排序值
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_my_modules( $args ) {
	$state = echo_my_module_state();
	?>
	<div class="echo-my-modules">
		<?php foreach ( echo_my_modules() as $key => $def ) : ?>
			<?php $row = $state[ $key ]; ?>
			<div class="echo-my-modules__row">
				<label class="echo-switch-label">
					<input type="checkbox" class="echo-switch-input"
						name="echo_settings[my_modules][<?php echo esc_attr( $key ); ?>][on]" value="1"
						<?php checked( $row['on'], true ); ?> />
					<span class="echo-switch" aria-hidden="true"><span class="echo-switch__knob"></span></span>
				</label>
				<span class="echo-my-modules__name"><?php echo esc_html( $def['label'] ); ?></span>
				<span class="echo-my-modules__desc"><?php echo esc_html( $def['desc'] ); ?></span>
				<label class="echo-my-modules__order">
					<?php esc_html_e( '排序', 'echo' ); ?>
					<input type="number" class="small-text"
						name="echo_settings[my_modules][<?php echo esc_attr( $key ); ?>][order]"
						value="<?php echo esc_attr( $row['order'] ); ?>" min="0" max="999" step="1" />
				</label>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="description"><?php esc_html_e( '数字越小越靠前；取消勾选即隐藏该模块。数量填 0 可隐藏「最近文章 / 最近评论」的列表。', 'echo' ); ?></p>
	<?php
}

/**
 * 内置快捷入口多选
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_my_builtin_links( $args ) {
	$saved = echo_my_config( 'my_builtin_links', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	foreach ( echo_my_builtin_links() as $key => $def ) :
		?>
		<label class="echo-checkbox-row">
			<input type="checkbox" name="echo_settings[my_builtin_links][]" value="<?php echo esc_attr( $key ); ?>"
				<?php checked( in_array( $key, $saved, true ), true ); ?> />
			<?php echo esc_html( $def['label'] ); ?>
		</label>
		<?php
	endforeach;
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 页面别名字段（附带当前地址与创建页面提示）
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_my_slug( $args ) {
	$key   = $args['label_for'];
	$slug  = sanitize_title( echo_get_setting( $key, 'my' ) );
	$page  = get_page_by_path( $slug );
	?>
	<input type="text" id="<?php echo esc_attr( $key ); ?>" name="echo_settings[<?php echo esc_attr( $key ); ?>]"
		value="<?php echo esc_attr( $slug ); ?>" class="regular-text" />
	<p class="description">
		<?php esc_html_e( '页面地址：', 'echo' ); ?>
		<code><?php echo esc_html( echo_my_page_url() ); ?></code>
	</p>
	<?php if ( $page ) : ?>
		<p class="description">
			<?php esc_html_e( '已找到同名页面。', 'echo' ); ?>
			<a href="<?php echo esc_url( get_edit_post_link( $page ) ); ?>"><?php esc_html_e( '编辑该页面', 'echo' ); ?></a>
			<?php esc_html_e( '，并在「页面属性 → 模板」中选择「个人中心」。', 'echo' ); ?>
		</p>
	<?php else : ?>
		<p class="description echo-my-slug-warn">
			<?php esc_html_e( '尚未找到该别名的页面。请新建页面、把别名设为上面的值，并在「页面属性 → 模板」中选择「个人中心」。', 'echo' ); ?>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( '新建页面', 'echo' ); ?></a>
		</p>
	<?php endif;
}

/* ============================================================
 * 清洗
 * ============================================================ */

/**
 * 清洗「我的」与「前端微调」的提交数据
 *
 * @param array $input  原始提交。
 * @param array $output 已清洗的输出（按引用追加）。
 * @return void
 */
function echo_sanitize_extra_settings( $input, &$output ) {
	$defaults = echo_extra_defaults();

	// 旧版本备份里没有这些字段。为避免导入旧备份时把新开关误判为「未勾选」而全部关闭，
	// 完全不含附加字段时直接沿用当前值。
	$has_extra = false;
	foreach ( array_keys( $defaults ) as $extra_key ) {
		if ( array_key_exists( $extra_key, $input ) ) {
			$has_extra = true;
			break;
		}
	}
	if ( ! $has_extra ) {
		foreach ( $defaults as $extra_key => $extra_value ) {
			$output[ $extra_key ] = echo_get_setting( $extra_key, $extra_value );
		}
		return;
	}

	$output['my_enabled']      = isset( $input['my_enabled'] ) && '1' === (string) $input['my_enabled'];
	$output['my_show_sidebar'] = isset( $input['my_show_sidebar'] ) && '1' === (string) $input['my_show_sidebar'];

	$slug = isset( $input['my_slug'] ) ? sanitize_title( $input['my_slug'] ) : '';
	$output['my_slug'] = '' !== $slug ? $slug : 'my';

	// 模块开关 + 排序
	$modules = array();
	$submitted = ( isset( $input['my_modules'] ) && is_array( $input['my_modules'] ) ) ? $input['my_modules'] : array();
	foreach ( echo_my_modules() as $key => $def ) {
		$row   = isset( $submitted[ $key ] ) && is_array( $submitted[ $key ] ) ? $submitted[ $key ] : array();
		$order = isset( $row['order'] ) ? (int) $row['order'] : (int) $def['order'];
		$modules[ $key ] = array(
			'on'    => isset( $row['on'] ) && '1' === (string) $row['on'],
			'order' => max( 0, min( 999, $order ) ),
		);
	}
	$output['my_modules'] = $modules;

	$clamp = function ( $value, $min, $max ) {
		$n = absint( $value );
		if ( 0 === $n ) {
			return 0;
		}
		return max( $min, min( $max, $n ) );
	};

	$output['my_avatar_size']     = max( 32, min( 256, absint( isset( $input['my_avatar_size'] ) ? $input['my_avatar_size'] : $defaults['my_avatar_size'] ) ) );
	$output['my_recent_posts']    = $clamp( isset( $input['my_recent_posts'] ) ? $input['my_recent_posts'] : 5, 0, 20 );
	$output['my_recent_comments'] = $clamp( isset( $input['my_recent_comments'] ) ? $input['my_recent_comments'] : 5, 0, 20 );

	$builtin = array();
	if ( isset( $input['my_builtin_links'] ) && is_array( $input['my_builtin_links'] ) ) {
		foreach ( $input['my_builtin_links'] as $item ) {
			if ( array_key_exists( $item, echo_my_builtin_links() ) ) {
				$builtin[] = $item;
			}
		}
	}
	$output['my_builtin_links'] = array_values( array_unique( $builtin ) );

	$output['my_quicklinks']      = isset( $input['my_quicklinks'] ) ? sanitize_textarea_field( $input['my_quicklinks'] ) : '';
	$output['my_guest_message']   = isset( $input['my_guest_message'] ) ? sanitize_textarea_field( $input['my_guest_message'] ) : '';
	$output['my_guest_shortcode'] = isset( $input['my_guest_shortcode'] ) ? sanitize_text_field( $input['my_guest_shortcode'] ) : '';

	$guest_mode = isset( $input['my_guest_mode'] ) ? $input['my_guest_mode'] : 'login_form';
	$output['my_guest_mode'] = in_array( $guest_mode, array( 'login_form', 'message', 'redirect' ), true ) ? $guest_mode : 'login_form';

	$card_style = isset( $input['my_card_style'] ) ? $input['my_card_style'] : 'auto';
	$output['my_card_style'] = in_array( $card_style, array( 'auto', 'classic', 'miuix' ), true ) ? $card_style : 'auto';

	// 自定义 CSS：去标签，避免闭合 style 标签注入
	$custom_css = isset( $input['my_custom_css'] ) ? wp_strip_all_tags( $input['my_custom_css'] ) : '';
	$custom_css = preg_replace( '/<\s*\/?\s*style\b[^>]*>/i', '', $custom_css );
	$output['my_custom_css'] = $custom_css;

	// 前端微调
	$output['tw_font_size']   = max( 13, min( 22, absint( isset( $input['tw_font_size'] ) ? $input['tw_font_size'] : $defaults['tw_font_size'] ) ) );
	// 0 = 不限制正文宽度，保持主题原有布局
	$entry_width = absint( isset( $input['tw_entry_width'] ) ? $input['tw_entry_width'] : $defaults['tw_entry_width'] );
	$output['tw_entry_width'] = 0 === $entry_width ? 0 : max( 520, min( 1100, $entry_width ) );

	$line_height = isset( $input['tw_line_height'] ) ? (float) $input['tw_line_height'] : $defaults['tw_line_height'];
	$output['tw_line_height'] = round( max( 1.4, min( 2.4, $line_height ) ), 2 );

	foreach ( array( 'tw_card_hover', 'tw_heading_decor', 'tw_reading_progress', 'tw_back_to_top', 'tw_smooth_scroll', 'tw_nav_my', 'tw_author_avatar', 'tw_lead_paragraph' ) as $bool_key ) {
		$output[ $bool_key ] = isset( $input[ $bool_key ] ) && '1' === (string) $input[ $bool_key ];
	}

	$output['tw_nav_my_label'] = isset( $input['tw_nav_my_label'] ) && '' !== trim( $input['tw_nav_my_label'] )
		? sanitize_text_field( $input['tw_nav_my_label'] )
		: $defaults['tw_nav_my_label'];
}

/* ============================================================
 * 前台输出
 * ============================================================ */

/**
 * 生成微调相关的动态 CSS
 *
 * @return string
 */
function echo_extra_css() {
	$css  = ':root{';
	$css .= '--echo-entry-font-size:' . absint( echo_my_config( 'tw_font_size', 17 ) ) . 'px;';
	$css .= '--echo-entry-line-height:' . (float) echo_my_config( 'tw_line_height', 1.85 ) . ';';
	$entry_width = absint( echo_my_config( 'tw_entry_width', 0 ) );
	if ( $entry_width > 0 ) {
		$css .= '--echo-entry-max-width:' . $entry_width . 'px;';
	}
	$css .= '}';

	if ( ! echo_my_config( 'tw_card_hover', true ) ) {
		$css .= '.echo-card:hover{transform:none !important;box-shadow:var(--echo-shadow) !important;}';
	}
	if ( ! echo_my_config( 'tw_heading_decor', true ) ) {
		$css .= '.echo-entry-content h2{border-left:0 !important;padding-left:0 !important;}';
	}
	if ( ! echo_my_config( 'tw_lead_paragraph', true ) ) {
		$css .= '.echo-entry-content > p:first-of-type{font-size:inherit !important;color:inherit !important;}';
	}
	if ( ! echo_my_config( 'tw_author_avatar', true ) ) {
		$css .= '.echo-single__author .avatar,.echo-card__author .avatar{display:none !important;}';
	}
	if ( ! echo_my_config( 'tw_smooth_scroll', true ) ) {
		$css .= 'html{scroll-behavior:auto !important;}';
	}

	$custom = trim( (string) echo_my_config( 'my_custom_css', '' ) );
	if ( '' !== $custom ) {
		$css .= "\n" . $custom;
	}
	return $css;
}

/**
 * 追加微调相关的 body class
 *
 * @param array $classes 已有 class。
 * @return array
 */
function echo_extra_body_class( $classes ) {
	if ( echo_my_config( 'my_enabled', true ) ) {
		$classes[] = 'echo-my-enabled';
	}
	$style = echo_my_config( 'my_card_style', 'auto' );
	if ( in_array( $style, array( 'classic', 'miuix' ), true ) ) {
		$classes[] = 'echo-my-card-' . $style;
	}
	return $classes;
}

/**
 * 页脚输出阅读进度条与返回顶部按钮的容器
 *
 * @return void
 */
function echo_extra_footer_html() {
	if ( echo_my_config( 'tw_reading_progress', false ) && is_singular( 'post' ) ) {
		echo '<div class="echo-reading-progress" aria-hidden="true"><span class="echo-reading-progress__bar"></span></div>';
	}
	if ( echo_my_config( 'tw_back_to_top', true ) ) {
		echo '<button type="button" class="echo-back-to-top" aria-label="' . esc_attr__( '返回顶部', 'echo' ) . '">';
		echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>';
		echo '</button>';
	}
}
add_action( 'wp_footer', 'echo_extra_footer_html' );

/**
 * 导航栏的「我的」入口
 *
 * @return void
 */
function echo_my_nav_link() {
	if ( ! echo_my_config( 'my_enabled', true ) || ! echo_my_config( 'tw_nav_my', true ) ) {
		return;
	}

	// 未登录且已启用登录 / 注册入口时，由登录 / 注册链接代替「我的」，避免两个入口重复
	if (
		! is_user_logged_in()
		&& function_exists( 'echo_auth_config' )
		&& echo_auth_config( 'auth_enabled', true )
		&& echo_auth_config( 'auth_show_nav', true )
	) {
		return;
	}

	$label = echo_my_config( 'tw_nav_my_label', '我的' );
	?>
	<a class="echo-my-nav-link" href="<?php echo esc_url( echo_my_page_url() ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php
}
