<?php
/**
 * Echo 主题函数文件
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 主题常量
define( 'ECHO_VERSION', '0.0.1' );

// 主题仓库地址：前台 / 后台页脚与 Theme URI 统一引用，避免写错
define( 'ECHO_REPO_URL', 'https://github.com/liutexi/ec_wp_theme' );

// 引入「我的」界面与前端微调（需在 customizer 之前加载，默认值要合并进设置）
require get_template_directory() . '/inc/my-page.php';

// 引入登录 / 注册界面（同上，默认值需在 customizer 之前合并）
require get_template_directory() . '/inc/auth.php';

// 引入用户主页（/uid/{id}）与自助资料编辑
require get_template_directory() . '/inc/user-page.php';

// 引入主题自定义器
require get_template_directory() . '/inc/customizer.php';

// 引入后台文章功能增强
require get_template_directory() . '/inc/admin-posts.php';

// 引入配置备份 / 导出 / 导入
require get_template_directory() . '/inc/backup.php';

/**
 * 主题初始化设置
 */
function echo_setup() {
	// 加载翻译
	load_theme_textdomain( 'echo', get_template_directory() . '/languages' );

	// 让 WordPress 管理 <title> 标签
	add_theme_support( 'title-tag' );

	// 特色图片（文章缩略图）
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 1200, 630, true );

	// 自定义 logo
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 60,
			'width'       => 200,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// 自动 feed 链接
	add_theme_support( 'automatic-feed-links' );

	// HTML5 标记
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);

	// 自定义背景与头部（可选）
	add_theme_support( 'custom-background' );

	// 注册导航菜单
	register_nav_menus(
		array(
			'primary' => __( '主菜单', 'echo' ),
			'footer'  => __( '页脚菜单', 'echo' ),
		)
	);

	// 内容宽度
	$GLOBALS['content_width'] = 1180;
}
add_action( 'after_setup_theme', 'echo_setup' );

/**
 * 注册小工具区域（侧边栏）
 */
function echo_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( '侧边栏', 'echo' ),
			'id'            => 'sidebar-1',
			'description'   => __( '显示在文章/页面右侧的小工具区域。', 'echo' ),
			'before_widget' => '<section id="%1$s" class="echo-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="echo-widget__title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'echo_widgets_init' );

/**
 * 加载主题样式与脚本
 */
function echo_scripts() {
	// 缓存参数带上文件修改时间，改样式后浏览器立即拉新（无需手动清缓存）
	$style_path = get_stylesheet_directory() . '/style.css';
	$style_ver  = file_exists( $style_path ) ? ECHO_VERSION . '.' . filemtime( $style_path ) : ECHO_VERSION;
	wp_enqueue_style( 'echo-style', get_stylesheet_uri(), array(), $style_ver );

	// 交互脚本
	$main_path = get_template_directory() . '/assets/js/main.js';
	$main_ver  = file_exists( $main_path ) ? ECHO_VERSION . '.' . filemtime( $main_path ) : ECHO_VERSION;
	wp_enqueue_script( 'echo-main', get_template_directory_uri() . '/assets/js/main.js', array(), $main_ver, true );

	// 脚本文案与开关
	wp_localize_script(
		'echo-main',
		'echoL10n',
		array(
			'codeCopy' => function_exists( 'echo_my_config' ) ? (bool) echo_my_config( 'tw_code_copy', true ) : false,
			'copy'     => __( '复制', 'echo' ),
			'copied'   => __( '已复制', 'echo' ),
			'copyFail' => __( '复制失败', 'echo' ),
		)
	);

	// 评论回复脚本
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'echo_scripts' );

/**
 * 文章摘要：控制长度并保留换行
 *
 * @param int $length 摘要字数。
 * @return int
 */
function echo_excerpt_length( $length ) {
	return 28;
}
add_filter( 'excerpt_length', 'echo_excerpt_length' );

/**
 * 修改摘要结尾符号
 *
 * @param string $more 默认的 [...]
 * @return string
 */
function echo_excerpt_more( $more ) {
	return '…';
}
add_filter( 'excerpt_more', 'echo_excerpt_more' );

/**
 * 给正文图片添加 loading="lazy"
 */
function echo_add_lazy_loading( $attr ) {
	if ( ! is_admin() ) {
		$attr['loading'] = 'lazy';
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'echo_add_lazy_loading' );

/**
 * 自定义头部 class，方便样式控制
 *
 * @param array $classes 已有的 class。
 * @return array
 */
function echo_body_classes( $classes ) {
	if ( ! is_singular() ) {
		$classes[] = 'echo-archive';
	}
	if ( is_home() || is_front_page() ) {
		$classes[] = 'echo-home';
	}
	return $classes;
}
add_filter( 'body_class', 'echo_body_classes' );

/**
 * 获取文章分类标签（用于卡片展示）
 *
 * @return string
 */
function echo_get_categories() {
	// 尊重「显示分类徽章」开关
	if ( function_exists( 'echo_get_setting' ) && ! echo_get_setting( 'show_category_badge', true ) ) {
		return '';
	}

	$categories = get_the_category();
	if ( empty( $categories ) ) {
		return '';
	}

	$output = '';
	foreach ( $categories as $category ) {
		$output .= '<a class="echo-category-badge" href="' . esc_url( get_category_link( $category->term_id ) ) . '">'
			. esc_html( $category->name ) . '</a>';
	}
	return $output;
}

/**
 * 打印站点 Logo 或站点名
 */
function echo_site_logo() {
	if ( has_custom_logo() ) {
		the_custom_logo();
	} else {
		echo '<span class="echo-brand__dot"></span>';
		echo '<a class="echo-brand" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . get_bloginfo( 'name' ) . '</a>';
	}
}

/**
 * 分页导航
 */
function echo_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 2,
			'prev_text' => __( '上一页', 'echo' ),
			'next_text' => __( '下一页', 'echo' ),
		)
	);
}

/**
 * 菜单回退函数：当未设置菜单时显示页面列表
 */
function echo_menu_fallback() {
	echo '<ul id="echo-primary-menu">';
	wp_list_pages(
		array(
			'title_li' => '',
			'depth'    => 1,
		)
	);
	echo '</ul>';
}

/**
 * 自定义评论回调：输出卡片式评论结构
 *
 * @param WP_Comment $comment 评论对象。
 * @param array      $args    参数。
 * @param int        $depth   深度。
 * @return void
 */
function echo_comment( $comment, $args, $depth ) {
	?>
	<li id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
		<div id="div-comment-<?php comment_ID(); ?>" class="comment-body">
			<?php echo get_avatar( $comment, 48 ); ?>
			<div class="comment-content">
				<div class="comment-author vcard">
					<?php
					printf(
						'<span class="fn">%s</span>',
						get_comment_author_link()
					);
					?>
				</div>
				<div class="comment-metadata">
					<a href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
						<time datetime="<?php comment_time( 'c' ); ?>">
							<?php
							printf(
								/* translators: 1: 日期，2: 时间 */
								esc_html__( '%1$s %2$s', 'echo' ),
								esc_html( get_comment_date() ),
								esc_html( get_comment_time() )
							);
							?>
						</time>
					</a>
					<?php
					if ( '0' === $comment->comment_approved ) {
						echo '<span class="comment-awaiting-moderation">' . esc_html__( '评论待审核', 'echo' ) . '</span>';
					}
					?>
				</div>
				<div class="comment-text">
					<?php comment_text(); ?>
				</div>
				<div class="reply">
					<?php
					comment_reply_link(
						array_merge(
							$args,
							array(
								'add_below' => 'div-comment',
								'depth'     => $depth,
								'max_depth' => $args['max_depth'],
							)
						)
					);
					?>
				</div>
			</div>
		</div>
	<?php
}

/**
 * 后台页脚致谢文字
 *
 * 把 WordPress 默认的「感谢使用 WordPress 进行创作。」
 * 改为「感谢使用 WordPress 和 Echo 主题协作。」，
 * 其中 WordPress 与 Echo 主题均为可点击链接。
 *
 * 仓库地址可通过 echo_footer_repo_url 过滤器覆盖。
 *
 * @param string $text 原有的页脚文字。
 * @return string
 */
function echo_admin_footer_text( $text ) {
	$wp_url   = 'https://wordpress.org/';
	$repo_url = apply_filters( 'echo_footer_repo_url', ECHO_REPO_URL );

	return '感谢使用 <a href="' . esc_url( $wp_url ) . '" target="_blank" rel="noopener noreferrer">WordPress</a>'
		. ' 和 <a href="' . esc_url( $repo_url ) . '" target="_blank" rel="noopener noreferrer">Echo 主题</a> 协作。';
}
add_filter( 'admin_footer_text', 'echo_admin_footer_text' );
