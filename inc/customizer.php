<?php
/**
 * Echo 独立主题设置页（后台「Echo 设置」菜单）
 *
 * 基于 WordPress Settings API 实现，选项统一存入 echo_settings。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 选项名
 */
define( 'ECHO_OPTION', 'echo_settings' );

/**
 * 默认设置
 *
 * @return array
 */
function echo_default_settings() {
	return array_merge(
		array(
		'primary_color'    => '#2563eb',
		'sidebar_position' => 'right',
		'content_width'    => 1180,
		'footer_text'      => '',
		'footer_credit'    => true,
		'hide_tagline'     => false,
		'custom_logo'      => 0,
		'hide_admin_bar'   => false,
		'exclude_categories' => array(),
		'show_category_badge' => true,
		'ui_style'         => 'classic',
		'ascii_slug'      => false,
		'admin_post_columns' => true,
		),
		echo_extra_defaults(),
		echo_auth_defaults(),
		echo_user_defaults()
	);
}

/**
 * 读取全部设置（合并默认值）
 *
 * @return array
 */
function echo_get_settings() {
	$saved = get_option( ECHO_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, echo_default_settings() );
}

/**
 * 读取单个设置
 *
 * @param string $key     设置键。
 * @param mixed  $default 默认值。
 * @return mixed
 */
function echo_get_setting( $key, $default = null ) {
	$settings = echo_get_settings();

	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 注册后台菜单与设置
 *
 * @return void
 */
function echo_settings_menu() {
	add_menu_page(
		__( 'Echo 设置', 'echo' ),   // 页面标题
		__( 'Echo 设置', 'echo' ),   // 菜单标题
		'manage_options',            // 权限
		'echo-settings',             // 菜单 slug
		'echo_settings_page_html',   // 渲染回调
		'dashicons-admin-appearance', // 图标
		61                           // 位置（在「外观」之后）
	);
}
add_action( 'admin_menu', 'echo_settings_menu' );

/**
 * 注册设置字段
 *
 * @return void
 */
function echo_settings_init() {
	register_setting(
		'echo_settings_group',
		ECHO_OPTION,
		array(
			'sanitize_callback' => 'echo_sanitize_settings',
		)
	);

	// 固定链接
	add_settings_section(
		'echo_section_permalink',
		__( '固定链接', 'echo' ),
		'echo_section_permalink_desc',
		'echo-settings'
	);

	add_settings_field(
		'echo_permalink_structure',
		__( '链接结构', 'echo' ),
		'echo_field_permalink_structure',
		'echo-settings',
		'echo_section_permalink',
		array(
			'label_for' => 'echo_permalink_structure',
		)
	);

	add_settings_field(
		'echo_rewrite_help',
		__( '服务器重写配置', 'echo' ),
		'echo_field_rewrite_help',
		'echo-settings',
		'echo_section_permalink',
		array(
			'label_for' => 'echo_rewrite_help',
		)
	);

	add_settings_field(
		'echo_ascii_slug',
		__( '新文章使用纯数字 slug', 'echo' ),
		'echo_field_checkbox',
		'echo-settings',
		'echo_section_permalink',
		array(
			'label_for' => 'ascii_slug',
			'desc'      => __( '中文标题生成的 slug 会被百分号编码。开启后，新文章的 slug 自动改用文章 ID（纯数字），链接永远干净。已有文章不受影响。', 'echo' ),
		)
	);

	// 界面风格
	add_settings_section(
		'echo_section_style',
		__( '界面风格', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	add_settings_field(
		'echo_ui_style',
		__( '外观风格', 'echo' ),
		'echo_field_ui_style',
		'echo-settings',
		'echo_section_style',
		array(
			'label_for' => 'ui_style',
			'desc'      => __( 'Miuix 风格复刻小米 HyperOS 设计语言：大圆角、浅灰 Surface 分层、柔和不刺眼的阴影。', 'echo' ),
		)
	);

	add_settings_field(
		'echo_admin_post_columns',
		__( '后台文章列表增强', 'echo' ),
		'echo_field_checkbox',
		'echo-settings',
		'echo_section_style',
		array(
			'label_for' => 'admin_post_columns',
			'desc'      => __( '在后台「文章」列表显示封面、ID、字数、链接列。关闭则恢复 WordPress 原生列。', 'echo' ),
		)
	);

	// 「我的」界面
	add_settings_section(
		'echo_section_my',
		__( '我的界面', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	$echo_my_fields = array(
		array( 'my_enabled', __( '启用个人中心', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '关闭后页面只显示提示，导航栏也不再显示「我的」入口。', 'echo' ) ) ),
		array( 'my_slug', __( '页面别名', 'echo' ), 'echo_field_my_slug', array() ),
		array( 'my_modules', __( '显示模块与排序', 'echo' ), 'echo_field_my_modules', array() ),
		array( 'my_avatar_size', __( '头像尺寸', 'echo' ), 'echo_field_number', array( 'min' => 32, 'max' => 256, 'step' => 8, 'suffix' => 'px', 'desc' => __( '资料卡头像边长，32–256px。', 'echo' ) ) ),
		array( 'my_recent_posts', __( '最近文章数量', 'echo' ), 'echo_field_number', array( 'min' => 0, 'max' => 20, 'step' => 1, 'desc' => __( '填 0 隐藏列表。', 'echo' ) ) ),
		array( 'my_recent_comments', __( '最近评论数量', 'echo' ), 'echo_field_number', array( 'min' => 0, 'max' => 20, 'step' => 1, 'desc' => __( '填 0 隐藏列表。', 'echo' ) ) ),
		array( 'my_builtin_links', __( '内置快捷入口', 'echo' ), 'echo_field_my_builtin_links', array( 'desc' => __( '按当前用户权限自动过滤，无权限的入口不会显示。', 'echo' ) ) ),
		array( 'my_quicklinks', __( '自定义快捷链接', 'echo' ), 'echo_field_textarea', array( 'rows' => 4, 'class' => 'echo-code-textarea', 'desc' => __( '每行一条，格式「名称 | URL」。以 / 开头视为站内路径，# 开头为注释。例如：关于我 | /about', 'echo' ) ) ),
		array( 'my_guest_mode', __( '未登录时', 'echo' ), 'echo_field_select', array( 'options' => array( 'login_form' => __( '显示登录表单', 'echo' ), 'message' => __( '显示提示文案与按钮', 'echo' ), 'redirect' => __( '直接跳转登录页', 'echo' ) ) ) ),
		array( 'my_guest_message', __( '未登录提示文案', 'echo' ), 'echo_field_textarea', array( 'rows' => 2, 'desc' => __( '留空则不显示。三种模式都会显示这段文案。', 'echo' ) ) ),
		array( 'my_guest_shortcode', __( '第三方登录短代码', 'echo' ), 'echo_field_text', array( 'desc' => __( '可选。填入第三方登录插件提供的短代码，会渲染在登录区块下方；由管理员填写，请确认短代码来源可信。', 'echo' ) ) ),
		array( 'my_show_sidebar', __( '显示侧边栏', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '默认关闭，个人中心整页宽度展示模块。', 'echo' ) ) ),
		array( 'my_card_style', __( '卡片风格', 'echo' ), 'echo_field_select', array( 'options' => array( 'auto' => __( '跟随全局风格', 'echo' ), 'classic' => __( '强制经典', 'echo' ), 'miuix' => __( '强制 Miuix', 'echo' ) ), 'desc' => __( '可让个人中心独立于全站风格单独设定。', 'echo' ) ) ),
		array( 'my_custom_css', __( '自定义 CSS', 'echo' ), 'echo_field_textarea', array( 'rows' => 6, 'class' => 'echo-code-textarea', 'desc' => __( '仅在此处填写 CSS，会追加到前台样式末尾，可用于覆盖任意细节。不要写 <style> 标签。', 'echo' ) ) ),
	);

	foreach ( $echo_my_fields as $field ) {
		$args = isset( $field[3] ) ? $field[3] : array();
		$args['label_for'] = $field[0];
		add_settings_field( 'echo_' . $field[0], $field[1], $field[2], 'echo-settings', 'echo_section_my', $args );
	}

	// 前端微调
	add_settings_section(
		'echo_section_tweaks',
		__( '前端微调', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	$echo_tw_fields = array(
		array( 'tw_font_size', __( '正文字号', 'echo' ), 'echo_field_number', array( 'min' => 13, 'max' => 22, 'step' => 1, 'suffix' => 'px' ) ),
		array( 'tw_line_height', __( '正文行高', 'echo' ), 'echo_field_number', array( 'min' => 1.4, 'max' => 2.4, 'step' => 0.05 ) ),
		array( 'tw_entry_width', __( '正文最大宽度', 'echo' ), 'echo_field_number', array( 'min' => 0, 'max' => 1100, 'step' => 20, 'suffix' => 'px', 'desc' => __( '填 0 表示不限制（默认，保持原有布局）。', 'echo' ) ) ),
		array( 'tw_card_hover', __( '卡片悬停反馈', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'tw_heading_decor', __( '标题竖条装饰', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'tw_lead_paragraph', __( '首段放大', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'tw_author_avatar', __( '文章元信息头像', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'tw_reading_progress', __( '文章阅读进度条', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '仅在单篇文章页顶部显示。', 'echo' ) ) ),
		array( 'tw_back_to_top', __( '返回顶部按钮', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'tw_smooth_scroll', __( '平滑滚动', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'tw_nav_my', __( '导航栏「我的」入口', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'tw_nav_my_label', __( '入口文字', 'echo' ), 'echo_field_text', array( 'desc' => __( '默认「我的」。', 'echo' ) ) ),
	);

	foreach ( $echo_tw_fields as $field ) {
		$args = isset( $field[3] ) ? $field[3] : array();
		$args['label_for'] = $field[0];
		add_settings_field( 'echo_' . $field[0], $field[1], $field[2], 'echo-settings', 'echo_section_tweaks', $args );
	}

	// 登录 / 注册
	add_settings_section(
		'echo_section_auth',
		__( '登录 / 注册', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	$echo_auth_fields = array(
		array( 'auth_enabled', __( '启用登录 / 注册页', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '关闭后页面只显示提示，导航栏也不再显示登录 / 注册入口。', 'echo' ) ) ),
		array( 'auth_login_slug', __( '页面别名', 'echo' ), 'echo_field_auth_slug', array( 'mode' => 'login', 'desc' => __( '登录 / 注册 / 找回密码共用这一个页面，用 ?view= 切换，不需要再建第二个页面。', 'echo' ) ) ),
		array( 'auth_show_nav', __( '导航栏入口', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '未登录时在导航栏显示「登录 / 注册」。', 'echo' ) ) ),
		array( 'auth_nav_login_label', __( '登录入口文字', 'echo' ), 'echo_field_text', array( 'desc' => __( '默认「登录」。', 'echo' ) ) ),
		array( 'auth_nav_register_label', __( '注册入口文字', 'echo' ), 'echo_field_text', array( 'desc' => __( '默认「注册」。', 'echo' ) ) ),
		array( 'auth_show_tabs', __( '显示登录 / 注册标签', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '在页面顶部显示「登录 / 注册」切换；找回密码不占标签位，而是登录表单里的「忘记密码？」链接。', 'echo' ) ) ),
		array( 'auth_allow_register', __( '允许前台注册', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '需同时开启 WordPress「设置 → 常规 → 任何人都可以注册」。', 'echo' ) ) ),
		array( 'auth_register_password', __( '注册时自设密码', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '开启：用户自己填写密码；关闭：由 WordPress 生成随机密码并邮件发送。', 'echo' ) ) ),
		array( 'auth_password_min', __( '密码最小长度', 'echo' ), 'echo_field_number', array( 'min' => 6, 'max' => 32, 'step' => 1, 'suffix' => '位' ) ),
		array( 'auth_auto_login', __( '注册后自动登录', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '仅在「注册时自设密码」开启时生效；生成密码模式会提示查收邮件。', 'echo' ) ) ),
		array( 'auth_redirect_logged_in', __( '已登录访问时跳转', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '已登录用户打开登录 / 注册页时，直接跳到「登录后跳转」目标。', 'echo' ) ) ),
		array( 'auth_after_login', __( '登录后跳转', 'echo' ), 'echo_field_select', array( 'options' => array( 'my' => __( '个人中心', 'echo' ), 'home' => __( '首页', 'echo' ), 'custom' => __( '自定义地址', 'echo' ) ) ) ),
		array( 'auth_after_login_url', __( '自定义跳转地址', 'echo' ), 'echo_field_text', array( 'desc' => __( '仅在「登录后跳转」选择「自定义地址」时生效。', 'echo' ) ) ),
		array( 'auth_show_remember', __( '显示「记住我」', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'auth_show_lostpassword', __( '启用找回密码', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '关闭后不显示「找回密码」标签和登录表单里的「忘记密码？」链接。', 'echo' ) ) ),
		array( 'auth_terms_enabled', __( '注册需同意条款', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'auth_terms_text', __( '条款文字', 'echo' ), 'echo_field_textarea', array( 'rows' => 2, 'desc' => __( '支持基础 HTML，例如链接。留空则显示默认文案。', 'echo' ) ) ),
		array( 'auth_login_title', __( '登录页标题', 'echo' ), 'echo_field_text', array( 'desc' => __( '同时作为登录按钮文字。默认「登录」。', 'echo' ) ) ),
		array( 'auth_register_title', __( '注册页标题', 'echo' ), 'echo_field_text', array( 'desc' => __( '同时作为注册按钮文字。默认「注册」。', 'echo' ) ) ),
		array( 'auth_lostpassword_title', __( '找回密码标题', 'echo' ), 'echo_field_text', array( 'desc' => __( '找回密码视图的标题文字。默认「找回密码」。', 'echo' ) ) ),
		array( 'auth_welcome', __( '欢迎文案', 'echo' ), 'echo_field_textarea', array( 'rows' => 2, 'desc' => __( '显示在标题下方，留空则不显示。', 'echo' ) ) ),
		array( 'auth_shortcode', __( '第三方登录短代码', 'echo' ), 'echo_field_text', array( 'desc' => __( '可选。填入第三方登录插件提供的短代码，会渲染在表单下方。', 'echo' ) ) ),
		array( 'auth_layout', __( '卡片风格', 'echo' ), 'echo_field_select', array( 'options' => array( 'auto' => __( '跟随全局风格', 'echo' ), 'classic' => __( '强制经典', 'echo' ), 'miuix' => __( '强制 Miuix', 'echo' ) ) ) ),
		array( 'auth_show_sidebar', __( '显示侧边栏', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '默认关闭，登录 / 注册页整页居中展示。', 'echo' ) ) ),
		array( 'auth_throttle', __( '提交频率限制', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '同一 IP 15 分钟内登录失败 12 次、注册 6 次、找回密码 8 次后暂停提交，防止暴力尝试与邮件轰炸。', 'echo' ) ) ),
		array( 'auth_custom_css', __( '自定义 CSS', 'echo' ), 'echo_field_textarea', array( 'rows' => 5, 'class' => 'echo-code-textarea', 'desc' => __( '仅填写 CSS，会追加到前台样式末尾。不要写 <style> 标签。', 'echo' ) ) ),
	);

	foreach ( $echo_auth_fields as $field ) {
		$args = isset( $field[3] ) ? $field[3] : array();
		$args['label_for'] = $field[0];
		add_settings_field( 'echo_' . $field[0], $field[1], $field[2], 'echo-settings', 'echo_section_auth', $args );
	}

	// 用户主页（UID）
	add_settings_section(
		'echo_section_user',
		__( '用户主页', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	$echo_user_fields = array(
		array( 'user_enabled', __( '启用用户主页', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '启用后可通过 /uid/{用户ID} 访问公开主页；关闭后返回 404。', 'echo' ) ) ),
		array( 'user_base', __( '链接前缀', 'echo' ), 'echo_field_text', array( 'desc' => sprintf( /* translators: %s: 示例地址 */ __( '默认 uid，示例：%s。修改后若出现 404，请到「设置 → 固定链接」点一次保存。', 'echo' ), home_url( '/' . echo_user_base() . '/1/' ) ) ) ),
		array( 'user_allow_edit', __( '允许自助编辑', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '用户在自己的主页可以修改昵称、简介与个人网站。', 'echo' ) ) ),
		array( 'user_allow_password', __( '允许修改密码', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '需要验证当前密码；密码长度沿用登录 / 注册里的最小长度设置。', 'echo' ) ) ),
		array( 'user_avatar_size', __( '头像尺寸', 'echo' ), 'echo_field_number', array( 'min' => 32, 'max' => 256, 'step' => 8, 'suffix' => 'px' ) ),
		array( 'user_show_stats', __( '显示数据统计', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '文章数、评论数与用户编号。', 'echo' ) ) ),
		array( 'user_show_posts', __( '显示最近文章', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'user_show_comments', __( '显示最近评论', 'echo' ), 'echo_field_checkbox', array( 'instant' => false ) ),
		array( 'user_recent_posts', __( '最近文章数量', 'echo' ), 'echo_field_number', array( 'min' => 0, 'max' => 20, 'step' => 1, 'desc' => __( '填 0 不显示。', 'echo' ) ) ),
		array( 'user_recent_comments', __( '最近评论数量', 'echo' ), 'echo_field_number', array( 'min' => 0, 'max' => 20, 'step' => 1, 'desc' => __( '填 0 不显示。', 'echo' ) ) ),
		array( 'user_show_sidebar', __( '显示侧边栏', 'echo' ), 'echo_field_checkbox', array( 'instant' => false, 'desc' => __( '默认关闭，整页展示资料。', 'echo' ) ) ),
		array( 'user_card_style', __( '卡片风格', 'echo' ), 'echo_field_select', array( 'options' => array( 'auto' => __( '跟随全局风格', 'echo' ), 'classic' => __( '强制经典', 'echo' ), 'miuix' => __( '强制 Miuix', 'echo' ) ) ) ),
		array( 'user_custom_css', __( '自定义 CSS', 'echo' ), 'echo_field_textarea', array( 'rows' => 5, 'class' => 'echo-code-textarea', 'desc' => __( '仅填写 CSS，会追加到前台样式末尾。不要写 <style> 标签。', 'echo' ) ) ),
	);

	foreach ( $echo_user_fields as $field ) {
		$args = isset( $field[3] ) ? $field[3] : array();
		$args['label_for'] = $field[0];
		add_settings_field( 'echo_' . $field[0], $field[1], $field[2], 'echo-settings', 'echo_section_user', $args );
	}

	// 站点标识（Logo、站点标题、副标题）
	add_settings_section(
		'echo_section_identity',
		__( '站点标识', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	add_settings_field(
		'echo_custom_logo',
		__( 'Logo', 'echo' ),
		'echo_field_logo',
		'echo-settings',
		'echo_section_identity',
		array(
			'label_for' => 'echo_custom_logo',
			'desc'      => __( '上传后显示在网站顶部导航栏，建议使用透明背景的 PNG。', 'echo' ),
		)
	);

	add_settings_field(
		'echo_site_title',
		__( '站点标题', 'echo' ),
		'echo_field_site_title',
		'echo-settings',
		'echo_section_identity',
		array(
			'label_for' => 'echo_site_title',
		)
	);

	add_settings_field(
		'echo_site_tagline',
		__( '站点副标题', 'echo' ),
		'echo_field_site_tagline',
		'echo-settings',
		'echo_section_identity',
		array(
			'label_for' => 'echo_site_tagline',
		)
	);

	// 颜色
	add_settings_section(
		'echo_section_colors',
		__( '颜色', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	add_settings_field(
		'echo_primary_color',
		__( '主题色', 'echo' ),
		'echo_field_color',
		'echo-settings',
		'echo_section_colors',
		array(
			'label_for' => 'echo_primary_color',
			'desc'      => __( '用于链接、按钮、分类标签等强调色。', 'echo' ),
		)
	);

	// 布局
	add_settings_section(
		'echo_section_layout',
		__( '布局', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	add_settings_field(
		'echo_sidebar_position',
		__( '侧边栏位置', 'echo' ),
		'echo_field_sidebar_position',
		'echo-settings',
		'echo_section_layout',
		array(
			'label_for' => 'echo_sidebar_position',
		)
	);

	add_settings_field(
		'echo_content_width',
		__( '内容区最大宽度', 'echo' ),
		'echo_field_content_width',
		'echo-settings',
		'echo_section_layout',
		array(
			'label_for' => 'echo_content_width',
			'desc'      => __( '整个页面内容的最大宽度（像素），默认 1180。', 'echo' ),
		)
	);

	add_settings_field(
		'echo_hide_admin_bar',
		__( '隐藏前台管理员工具栏', 'echo' ),
		'echo_field_checkbox',
		'echo-settings',
		'echo_section_layout',
		array(
			'label_for' => 'hide_admin_bar',
			'desc'      => __( '登录后访问前台时，隐藏页面顶部那条黑色管理员工具栏。', 'echo' ),
		)
	);

	// 页脚
	add_settings_section(
		'echo_section_footer',
		__( '页脚', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	// 文章分类
	add_settings_section(
		'echo_section_categories',
		__( '文章分类', 'echo' ),
		'__return_false',
		'echo-settings'
	);

	add_settings_field(
		'echo_exclude_categories',
		__( '首页排除的分类', 'echo' ),
		'echo_field_exclude_categories',
		'echo-settings',
		'echo_section_categories',
		array(
			'label_for' => 'exclude_categories',
			'desc'      => __( '勾选的分类将不会显示在首页文章列表中。不勾选任何项即显示全部分类。', 'echo' ),
		)
	);

	add_settings_field(
		'echo_show_category_badge',
		__( '显示分类徽章', 'echo' ),
		'echo_field_checkbox',
		'echo-settings',
		'echo_section_categories',
		array(
			'label_for' => 'show_category_badge',
			'desc'      => __( '在文章列表和文章页顶部显示分类徽章。', 'echo' ),
		)
	);

	add_settings_field(
		'echo_footer_text',
		__( '页脚版权文字', 'echo' ),
		'echo_field_footer_text',
		'echo-settings',
		'echo_section_footer',
		array(
			'label_for' => 'echo_footer_text',
			'desc'      => __( '显示在页脚的自定义文字，留空则显示默认版权信息。', 'echo' ),
		)
	);

	add_settings_field(
		'echo_footer_credit',
		__( '显示主题署名', 'echo' ),
		'echo_field_checkbox',
		'echo-settings',
		'echo_section_footer',
		array(
			'label_for' => 'footer_credit',
			'desc'      => __( '在页脚显示「使用 Echo 主题」的署名链接。', 'echo' ),
		)
	);

	add_settings_field(
		'echo_hide_tagline',
		__( '隐藏站点副标题', 'echo' ),
		'echo_field_checkbox',
		'echo-settings',
		'echo_section_footer',
		array(
			'label_for' => 'hide_tagline',
			'desc'      => __( '在页脚隐藏站点描述文字。', 'echo' ),
		)
	);
}
add_action( 'admin_init', 'echo_settings_init' );

/**
 * 清洗并保存设置
 *
 * @param array $input 提交的原始数据。
 * @return array
 */
function echo_sanitize_settings( $input ) {
	$defaults = echo_default_settings();
	$output   = array();

	// Logo：存附件 ID，并同步到 WordPress 的 custom_logo 选项
	$output['custom_logo'] = isset( $input['custom_logo'] ) ? absint( $input['custom_logo'] ) : 0;
	set_theme_mod( 'custom_logo', $output['custom_logo'] );

	// 站点标题 / 副标题：同步到 WordPress 标准选项
	if ( isset( $input['site_title'] ) ) {
		$site_title = sanitize_text_field( $input['site_title'] );
		if ( '' !== $site_title ) {
			update_option( 'blogname', $site_title );
		}
	}

	if ( isset( $input['site_tagline'] ) ) {
		$site_tagline = sanitize_text_field( $input['site_tagline'] );
		update_option( 'blogdescription', $site_tagline );
	}

	$output['primary_color'] = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : $defaults['primary_color'];
	if ( '' === $output['primary_color'] ) {
		$output['primary_color'] = $defaults['primary_color'];
	}

	$output['sidebar_position'] = ( isset( $input['sidebar_position'] ) && 'left' === $input['sidebar_position'] ) ? 'left' : 'right';

	// 界面风格：仅允许 classic / miuix
	$output['ui_style'] = ( isset( $input['ui_style'] ) && 'miuix' === $input['ui_style'] ) ? 'miuix' : 'classic';

	$width                   = isset( $input['content_width'] ) ? absint( $input['content_width'] ) : $defaults['content_width'];
	$output['content_width'] = max( 800, min( 1600, $width ) );

	$output['footer_text'] = isset( $input['footer_text'] ) ? wp_kses_post( $input['footer_text'] ) : '';

	// 复选框：勾选时值为 "1"，未勾选时字段不提交，需分别处理
	$output['footer_credit']  = isset( $input['footer_credit'] ) && '1' === (string) $input['footer_credit'];
	$output['hide_tagline']   = isset( $input['hide_tagline'] ) && '1' === (string) $input['hide_tagline'];
	$output['hide_admin_bar'] = isset( $input['hide_admin_bar'] ) && '1' === (string) $input['hide_admin_bar'];
	$output['show_category_badge'] = isset( $input['show_category_badge'] ) && '1' === (string) $input['show_category_badge'];
	$output['ascii_slug']          = isset( $input['ascii_slug'] ) && '1' === (string) $input['ascii_slug'];
	$output['admin_post_columns']  = isset( $input['admin_post_columns'] ) && '1' === (string) $input['admin_post_columns'];

	// 固定链接结构：写入 WP 核心选项（仅在变化时）
	if ( isset( $input['permalink_structure'] ) ) {
		$structure = echo_sanitize_permalink_structure( $input['permalink_structure'] );
		if ( '' !== $structure && get_option( 'permalink_structure' ) !== $structure ) {
			update_option( 'permalink_structure', $structure );
			// 标记需要刷新重写规则（在 admin_init 之后执行）
			set_transient( 'echo_flush_rewrite', 1, 60 );
		}
	}

	// 排除分类：数组，每项为分类 ID（正整数）
	$output['exclude_categories'] = array();
	if ( isset( $input['exclude_categories'] ) && is_array( $input['exclude_categories'] ) ) {
		foreach ( $input['exclude_categories'] as $cat_id ) {
			$cat_id = absint( $cat_id );
			if ( $cat_id > 0 ) {
				$output['exclude_categories'][] = $cat_id;
			}
		}
		$output['exclude_categories'] = array_values( array_unique( $output['exclude_categories'] ) );
	}

	// 「我的」界面与前端微调
	echo_sanitize_extra_settings( $input, $output );

	// 登录 / 注册
	echo_sanitize_auth_settings( $input, $output );

	// 用户主页
	echo_sanitize_user_settings( $input, $output );

	return $output;
}

/**
 * 设置页 HTML
 *
 * @return void
 */
function echo_settings_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap echo-settings-wrap">
		<h1 class="echo-settings-title"><?php esc_html_e( 'Echo 主题设置', 'echo' ); ?></h1>
		<p class="echo-settings-intro"><?php esc_html_e( '自定义站点外观与显示选项。', 'echo' ); ?></p>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'echo_settings_group' );
			do_settings_sections( 'echo-settings' );
			submit_button( __( '保存设置', 'echo' ) );
			?>
		</form>

		<?php echo_render_category_manager(); ?>
		<?php echo_render_post_manager(); ?>
		<?php echo_render_backup_manager(); ?>

		<!-- 即时保存用的 AJAX 数据（兜底，避免 localize 失效） -->
		<div id="echo-ajax-data" style="display:none;"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'echo_settings_nonce' ) ); ?>"></div>

		<?php echo_permalink_admin_script(); ?>
	</div>
	<?php
}

/* ============================================================
 * 分类管理（独立于 options.php 主表单，走 AJAX）
 * ============================================================ */

/**
 * 取全部文章分类（含空分类）
 *
 * @return array
 */
function echo_get_all_categories() {
	return get_categories(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
}

/**
 * 生成父级分类下拉选项
 *
 * @param int $selected   当前选中项。
 * @param int $exclude_id 需要排除的分类 ID（编辑时排除自身）。
 * @return string
 */
function echo_render_parent_options( $selected = 0, $exclude_id = 0 ) {
	$html = '<option value="0">' . esc_html__( '— 无父级 —', 'echo' ) . '</option>';

	foreach ( echo_get_all_categories() as $cat ) {
		if ( (int) $cat->term_id === (int) $exclude_id ) {
			continue;
		}

		$html .= sprintf(
			'<option value="%1$s" %3$s>%2$s</option>',
			esc_attr( $cat->term_id ),
			esc_html( $cat->name ),
			selected( (int) $selected, (int) $cat->term_id, false )
		);
	}

	return $html;
}

/**
 * 生成分类表格的全部行（主行 + 隐藏的编辑行）
 *
 * @return string
 */
function echo_render_category_rows() {
	$categories   = echo_get_all_categories();
	$default_cat  = absint( get_option( 'default_category' ) );

	ob_start();

	if ( empty( $categories ) ) {
		echo '<tr class="echo-cat-empty"><td colspan="4">' . esc_html__( '暂无分类，请在上方添加。', 'echo' ) . '</td></tr>';
	}

	foreach ( $categories as $cat ) {
		$id         = (int) $cat->term_id;
		$is_default = ( $id === $default_cat );
		?>
		<tr class="echo-cat-row" data-id="<?php echo esc_attr( $id ); ?>">
			<td class="echo-cat-name">
				<?php echo esc_html( $cat->name ); ?>
				<?php if ( $is_default ) : ?>
					<span class="echo-cat-default"><?php esc_html_e( '默认', 'echo' ); ?></span>
				<?php endif; ?>
			</td>
			<td><code><?php echo esc_html( $cat->slug ); ?></code></td>
			<td><?php echo esc_html( absint( $cat->count ) ); ?></td>
			<td class="echo-cat-actions">
				<button type="button" class="button button-small echo-cat-edit"><?php esc_html_e( '编辑', 'echo' ); ?></button>
				<?php if ( ! $is_default ) : ?>
					<button type="button" class="button button-small echo-cat-set-default"><?php esc_html_e( '设为默认', 'echo' ); ?></button>
					<button type="button" class="button button-small echo-cat-del"><?php esc_html_e( '删除', 'echo' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
		<tr class="echo-cat-edit-row" data-id="<?php echo esc_attr( $id ); ?>" style="display:none;">
			<td colspan="4">
				<div class="echo-cat-edit-form">
					<label>
						<span><?php esc_html_e( '名称', 'echo' ); ?></span>
						<input type="text" class="echo-cat-f-name regular-text" value="<?php echo esc_attr( $cat->name ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Slug', 'echo' ); ?></span>
						<input type="text" class="echo-cat-f-slug regular-text" value="<?php echo esc_attr( $cat->slug ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( '父级', 'echo' ); ?></span>
						<select class="echo-cat-f-parent"><?php echo echo_render_parent_options( $cat->parent, $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
					</label>
					<label>
						<span><?php esc_html_e( '描述', 'echo' ); ?></span>
						<textarea class="echo-cat-f-desc large-text" rows="2"><?php echo esc_textarea( $cat->description ); ?></textarea>
					</label>
					<div class="echo-cat-edit-actions">
						<button type="button" class="button button-primary echo-cat-save"><?php esc_html_e( '保存修改', 'echo' ); ?></button>
						<button type="button" class="button echo-cat-cancel"><?php esc_html_e( '取消', 'echo' ); ?></button>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	return ob_get_clean();
}

/**
 * 渲染分类管理区块
 *
 * @return void
 */
function echo_render_category_manager() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	?>
	<div id="echo-cat-manager" class="echo-cat-manager">
		<h2><?php esc_html_e( '分类管理', 'echo' ); ?></h2>
		<p class="description"><?php esc_html_e( '等同于后台「文章 → 分类」，此处可直接新增、编辑、删除分类，操作即时生效。', 'echo' ); ?></p>

		<form class="echo-cat-add-form">
			<div class="echo-cat-add-grid">
				<label>
					<span><?php esc_html_e( '名称', 'echo' ); ?></span>
					<input type="text" class="echo-cat-add-name regular-text" required placeholder="<?php esc_attr_e( '新分类名称', 'echo' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Slug', 'echo' ); ?></span>
					<input type="text" class="echo-cat-add-slug regular-text" placeholder="<?php esc_attr_e( '留空自动生成', 'echo' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( '父级', 'echo' ); ?></span>
					<select class="echo-cat-add-parent"><?php echo echo_render_parent_options( 0, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
				</label>
				<label>
					<span><?php esc_html_e( '描述', 'echo' ); ?></span>
					<input type="text" class="echo-cat-add-desc regular-text" placeholder="<?php esc_attr_e( '可选', 'echo' ); ?>" />
				</label>
			</div>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( '添加分类', 'echo' ); ?></button></p>
		</form>

		<table class="widefat striped echo-cat-list">
			<thead>
				<tr>
					<th><?php esc_html_e( '名称', 'echo' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'echo' ); ?></th>
					<th><?php esc_html_e( '文章数', 'echo' ); ?></th>
					<th><?php esc_html_e( '操作', 'echo' ); ?></th>
				</tr>
			</thead>
			<tbody id="echo-cat-rows"><?php echo echo_render_category_rows(); // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody>
		</table>

		<p class="description echo-cat-note">
			<?php esc_html_e( '删除分类不会删除文章，该分类下的文章会移动到默认分类。默认分类不可删除。', 'echo' ); ?>
		</p>
	</div>
	<?php
}

/**
 * AJAX：新增 / 编辑 / 删除 / 设为默认分类
 *
 * @return void
 */
function echo_ajax_manage_category() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_send_json_error( array( 'message' => __( '无权限', 'echo' ) ), 403 );
	}

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'echo_settings_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( '安全校验失败，请刷新页面后重试', 'echo' ) ), 403 );
	}

	$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

	$respond = function ( $message ) {
		wp_send_json_success(
			array(
				'message' => $message,
				'rows'    => echo_render_category_rows(),
				'options' => echo_render_parent_options( 0, 0 ),
			)
		);
	};

	// 删除
	if ( 'delete' === $op ) {
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( '缺少分类 ID', 'echo' ) ), 400 );
		}
		if ( $id === absint( get_option( 'default_category' ) ) ) {
			wp_send_json_error( array( 'message' => __( '默认分类不能删除', 'echo' ) ), 400 );
		}
		$result = wp_delete_term( $id, 'category' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$respond( __( '分类已删除', 'echo' ) );
	}

	// 设为默认
	if ( 'set_default' === $op ) {
		if ( ! $id || ! term_exists( $id, 'category' ) ) {
			wp_send_json_error( array( 'message' => __( '分类不存在', 'echo' ) ), 400 );
		}
		update_option( 'default_category', $id );
		$respond( __( '已设为默认分类', 'echo' ) );
	}

	// 新增 / 编辑
	$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$slug   = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
	$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
	$desc   = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

	if ( '' === $name ) {
		wp_send_json_error( array( 'message' => __( '分类名称不能为空', 'echo' ) ), 400 );
	}

	$args = array(
		'name'        => $name,
		'parent'      => $parent,
		'description' => $desc,
	);
	if ( '' !== $slug ) {
		$args['slug'] = $slug;
	}

	if ( 'create' === $op ) {
		$result = wp_insert_term( $name, 'category', $args );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$respond( __( '分类已创建', 'echo' ) );
	}

	if ( 'update' === $op ) {
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( '缺少分类 ID', 'echo' ) ), 400 );
		}
		// 防止把父级设为自己
		if ( $parent === $id ) {
			$args['parent'] = 0;
		}
		$result = wp_update_term( $id, 'category', $args );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$respond( __( '分类已更新', 'echo' ) );
	}

	wp_send_json_error( array( 'message' => __( '未知操作', 'echo' ) ), 400 );
}
add_action( 'wp_ajax_echo_manage_category', 'echo_ajax_manage_category' );

/* ============================================================
 * 固定链接
 * ============================================================ */

/**
 * 「固定链接」分组说明
 *
 * @return void
 */
function echo_section_permalink_desc() {
	$current = get_option( 'permalink_structure' );
	$has_index = ( is_string( $current ) && 0 === stripos( $current, '/index.php' ) );

	echo '<p class="description" style="margin:0 0 10px;">';
	esc_html_e( '文章地址由 WordPress 的固定链接结构决定。带 /index.php/ 前缀说明服务器尚未启用 URL 重写，请先按下方配置服务器。', 'echo' );
	echo '</p>';

	if ( $has_index ) {
		echo '<div class="notice notice-warning inline" style="margin:0 0 10px;"><p>';
		esc_html_e( '检测到当前结构包含 /index.php，需配置服务器重写规则后才能彻底去掉。', 'echo' );
		echo '</p></div>';
	}
}

/**
 * 清洗固定链接结构
 *
 * @param string $raw 原始结构。
 * @return string
 */
function echo_sanitize_permalink_structure( $raw ) {
	$structure = trim( (string) $raw );

	// 去掉 index.php 前缀
	$structure = preg_replace( '#^/?index\.php/?#i', '/', $structure );
	$structure = '/' . ltrim( $structure, '/' );

	// 允许的结构标签
	$tags = array( '%post_id%', '%postname%', '%year%', '%monthnum%', '%day%', '%category%', '%author%' );
	$rest = str_replace( $tags, '', $structure );

	// 去掉标签后只允许字母数字与 / - _
	if ( preg_match( '#[^a-zA-Z0-9/_\-]#', $rest ) ) {
		return '';
	}

	if ( '/' !== substr( $structure, -1 ) ) {
		$structure .= '/';
	}

	// 至少包含一个可唯一标识文章的标签
	if ( false === strpos( $structure, '%post_id%' ) && false === strpos( $structure, '%postname%' ) ) {
		return '';
	}

	return $structure;
}

/**
 * 链接结构字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_permalink_structure( $args ) {
	$current = (string) get_option( 'permalink_structure' );
	$home    = untrailingslashit( home_url() );

	$presets = array(
		'/archives/%post_id%'                  => array( __( '文章 ID（推荐中文站）', 'echo' ), '/archives/123' ),
		'/%postname%'                          => array( __( '文章名', 'echo' ), '/a-random-welcome-api' ),
		'/%year%/%monthnum%/%day%/%post_id%'   => array( __( '日期 + ID', 'echo' ), '/2026/10/16/123' ),
		'/%post_id%'                           => array( __( '纯 ID', 'echo' ), '/123' ),
	);

	// 自定义结构：不在预设里时作为当前值
	$is_custom = ( '' !== $current && ! isset( $presets[ $current ] ) );
	?>
	<p style="margin:0 0 12px;">
		<?php esc_html_e( '当前结构：', 'echo' ); ?>
		<code style="padding:2px 6px;background:#f0f0f1;border-radius:2px;"><?php echo esc_html( $current ? $current : __( '（朴素）', 'echo' ) ); ?></code>
	</p>

	<fieldset class="echo-permalink-choices">
		<?php foreach ( $presets as $struct => $info ) : ?>
			<label>
				<input type="radio" name="echo_settings[permalink_structure]" value="<?php echo esc_attr( $struct ); ?>"
					<?php checked( $current, $struct ); ?> />
				<strong><?php echo esc_html( $info[0] ); ?></strong>
				<code><?php echo esc_html( $home . $info[1] ); ?></code>
			</label>
		<?php endforeach; ?>

		<?php
		$custom_value = $is_custom ? $current : '/archives/%post_id%';
		?>
		<label>
			<input type="radio" class="echo-permalink-custom-radio" name="echo_settings[permalink_structure]"
				value="<?php echo esc_attr( $custom_value ); ?>" <?php checked( $is_custom, true ); ?> />
			<strong><?php esc_html_e( '自定义', 'echo' ); ?></strong>
			<input type="text" class="echo-permalink-custom regular-text" style="width:260px;"
				placeholder="/archives/%post_id%"
				value="<?php echo esc_attr( $is_custom ? $current : '' ); ?>" />
		</label>
	</fieldset>

	<p class="description">
		<?php esc_html_e( '可用标签：%post_id% %postname% %year% %monthnum% %day% %category% %author%。修改后旧链接会失效，建议配置 301 重定向。', 'echo' ); ?>
	</p>
	<?php
}

/**
 * 服务器重写配置字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_rewrite_help( $args ) {
	$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
	$is_nginx = ( false !== stripos( $software, 'nginx' ) );

	if ( $is_nginx ) {
		$title = __( 'Nginx（在 server 块中添加）', 'echo' );
		$code  = "location / {\n    try_files \$uri \$uri/ /index.php?\$args;\n}";
	} else {
		$title = __( 'Apache（网站根目录 .htaccess）', 'echo' );
		$code  = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php\$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n# END WordPress";
	}
	?>
	<p class="description" style="margin:0 0 6px;">
		<?php echo esc_html( $title ); ?>
		<?php if ( '' !== $software ) : ?>
			· <?php echo esc_html( sprintf( __( '检测到：%s', 'echo' ), $software ) ); ?>
		<?php endif; ?>
	</p>
	<textarea readonly rows="9" class="large-text echo-rewrite-code" style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $code ); ?></textarea>
	<p class="description">
		<?php esc_html_e( '配置好服务器后，回到本页选择不带 index.php 的结构并保存即可。', 'echo' ); ?>
	</p>
	<?php
}

/**
 * 自定义结构选中时，把输入值同步到单选按钮
 *
 * @return void
 */
function echo_permalink_admin_script() {
	?>
	<script>
	(function () {
		var custom = document.querySelector('.echo-permalink-custom');
		var radio = document.querySelector('.echo-permalink-custom-radio');
		if (!custom || !radio) { return; }
		var fallback = radio.value;
		custom.addEventListener('focus', function () { radio.checked = true; });
		custom.addEventListener('input', function () {
			radio.value = custom.value || fallback;
			radio.checked = true;
		});
	})();
	</script>
	<?php
}

/**
 * 界面风格字段（可视化选择）
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_ui_style( $args ) {
	$value = echo_get_setting( 'ui_style', 'classic' );
	?>
	<div class="echo-style-choices">
		<label class="echo-style-choice">
			<input type="radio" name="echo_settings[ui_style]" value="classic" <?php checked( $value, 'classic' ); ?> />
			<span class="echo-style-choice__box echo-style-choice__box--classic">
				<span class="echo-style-mock">
					<span class="echo-style-mock__bar"></span>
					<span class="echo-style-mock__card"></span>
					<span class="echo-style-mock__card"></span>
				</span>
				<span class="echo-style-choice__label"><?php esc_html_e( '经典', 'echo' ); ?></span>
				<span class="echo-style-choice__sub"><?php esc_html_e( '细边框 · 紧凑', 'echo' ); ?></span>
			</span>
		</label>

		<label class="echo-style-choice">
			<input type="radio" name="echo_settings[ui_style]" value="miuix" <?php checked( $value, 'miuix' ); ?> />
			<span class="echo-style-choice__box echo-style-choice__box--miuix">
				<span class="echo-style-mock">
					<span class="echo-style-mock__bar"></span>
					<span class="echo-style-mock__card"></span>
					<span class="echo-style-mock__card"></span>
				</span>
				<span class="echo-style-choice__label"><?php esc_html_e( 'Miuix', 'echo' ); ?></span>
				<span class="echo-style-choice__sub"><?php esc_html_e( 'HyperOS · 大圆角', 'echo' ); ?></span>
			</span>
		</label>
	</div>
	<?php
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * Logo 上传字段（使用 WordPress 媒体库）
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_logo( $args ) {
	$logo_id = absint( echo_get_setting( 'custom_logo', get_theme_mod( 'custom_logo', 0 ) ) );
	$preview = $logo_id ? wp_get_attachment_image( $logo_id, 'medium', false, array( 'class' => 'echo-logo-preview' ) ) : '';
	?>
	<div class="echo-logo-field">
		<div class="echo-logo-preview-wrap">
			<?php
			if ( '' !== $preview ) {
				echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<span class="echo-logo-placeholder">' . esc_html__( '尚未上传 Logo', 'echo' ) . '</span>';
			}
			?>
		</div>
		<input type="hidden" id="echo_custom_logo" name="echo_settings[custom_logo]" value="<?php echo esc_attr( $logo_id ); ?>" />
		<p>
			<button type="button" class="button echo-logo-upload"><?php esc_html_e( '选择 / 上传 Logo', 'echo' ); ?></button>
			<button type="button" class="button button-link-delete echo-logo-remove" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>>
				<?php esc_html_e( '移除', 'echo' ); ?>
			</button>
		</p>
		<?php if ( ! empty( $args['desc'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * 站点标题字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_site_title( $args ) {
	printf(
		'<input type="text" id="echo_site_title" name="echo_settings[site_title]" value="%s" class="regular-text" />',
		esc_attr( get_bloginfo( 'name' ) )
	);
}

/**
 * 站点副标题字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_site_tagline( $args ) {
	printf(
		'<input type="text" id="echo_site_tagline" name="echo_settings[site_tagline]" value="%s" class="regular-text" />',
		esc_attr( get_bloginfo( 'description' ) )
	);
}

/**
 * 主题色字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_color( $args ) {
	$value = echo_get_setting( 'primary_color', '#2563eb' );

	// 预设配色
	$presets = array(
		'#2563eb' => __( '经典蓝', 'echo' ),
		'#7c3aed' => __( '紫罗兰', 'echo' ),
		'#db2777' => __( '玫瑰红', 'echo' ),
		'#ea580c' => __( '活力橙', 'echo' ),
		'#16a34a' => __( '森林绿', 'echo' ),
		'#0891b2' => __( '青蓝', 'echo' ),
		'#475569' => __( '石墨灰', 'echo' ),
		'#111827' => __( '墨黑', 'echo' ),
	);
	?>
	<div class="echo-color-presets">
		<?php foreach ( $presets as $hex => $name ) : ?>
			<button type="button"
				class="echo-color-preset <?php echo ( strtolower( $value ) === strtolower( $hex ) ) ? 'is-active' : ''; ?>"
				data-color="<?php echo esc_attr( $hex ); ?>"
				style="background:<?php echo esc_attr( $hex ); ?>"
				title="<?php echo esc_attr( $name ); ?>"
				aria-label="<?php echo esc_attr( $name ); ?>"></button>
		<?php endforeach; ?>
	</div>
	<?php
	printf(
		'<input type="text" id="echo_primary_color" name="echo_settings[primary_color]" value="%s" class="echo-color-field" data-default-color="#2563eb" />',
		esc_attr( $value )
	);

	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 侧边栏位置字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_sidebar_position( $args ) {
	$value = echo_get_setting( 'sidebar_position', 'right' );
	?>
	<div class="echo-layout-choices">
		<label class="echo-layout-choice">
			<input type="radio" name="echo_settings[sidebar_position]" value="right" <?php checked( $value, 'right' ); ?> />
			<span class="echo-layout-choice__box">
				<span class="echo-layout-preview">
					<span class="echo-layout-preview__main"></span>
					<span class="echo-layout-preview__side"></span>
				</span>
				<span class="echo-layout-choice__label"><?php esc_html_e( '侧边栏在右', 'echo' ); ?></span>
			</span>
		</label>

		<label class="echo-layout-choice">
			<input type="radio" name="echo_settings[sidebar_position]" value="left" <?php checked( $value, 'left' ); ?> />
			<span class="echo-layout-choice__box">
				<span class="echo-layout-preview">
					<span class="echo-layout-preview__side"></span>
					<span class="echo-layout-preview__main"></span>
				</span>
				<span class="echo-layout-choice__label"><?php esc_html_e( '侧边栏在左', 'echo' ); ?></span>
			</span>
		</label>
	</div>
	<?php
}

/**
 * 内容宽度字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_content_width( $args ) {
	$value = absint( echo_get_setting( 'content_width', 1180 ) );
	?>
	<div class="echo-width-control">
		<input type="range" id="echo_content_width_range" min="800" max="1600" step="20" value="<?php echo esc_attr( $value ); ?>" />
		<span class="echo-width-value">
			<span id="echo_content_width_display"><?php echo esc_html( $value ); ?></span> px
		</span>
		<input type="hidden" id="echo_content_width" name="echo_settings[content_width]" value="<?php echo esc_attr( $value ); ?>" />
	</div>
	<?php
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 页脚文字字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_footer_text( $args ) {
	$value = echo_get_setting( 'footer_text', '' );
	printf(
		'<textarea id="echo_footer_text" name="echo_settings[footer_text]" rows="3" class="large-text">%s</textarea>',
		esc_textarea( $value )
	);

	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 复选框字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_checkbox( $args ) {
	$key     = $args['label_for'];
	$value   = echo_get_setting( $key, false );
	// 默认即时保存；传 'instant' => false 则随「保存设置」按钮一起提交
	$instant = ! isset( $args['instant'] ) || ! empty( $args['instant'] );
	?>
	<label class="echo-switch-label">
		<input type="checkbox" class="echo-switch-input" id="<?php echo esc_attr( $key ); ?>"
			name="echo_settings[<?php echo esc_attr( $key ); ?>]" value="1"
			<?php checked( $value, true ); ?>
			<?php if ( $instant ) : ?>data-instant="1" data-key="<?php echo esc_attr( $key ); ?>"<?php endif; ?> />
		<span class="echo-switch" aria-hidden="true"><span class="echo-switch__knob"></span></span>
		<span class="echo-switch-state"><?php echo $value ? esc_html__( '已开启', 'echo' ) : esc_html__( '已关闭', 'echo' ); ?></span>
	</label>
	<?php
	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 排除分类多选框字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_exclude_categories( $args ) {
	$excluded = echo_get_setting( 'exclude_categories', array() );
	$excluded = is_array( $excluded ) ? $excluded : array();

	$categories = get_categories(
		array(
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);

	if ( empty( $categories ) ) {
		echo '<p class="description">' . esc_html__( '当前站点还没有任何分类，请先在「文章 → 分类」中创建。', 'echo' ) . '</p>';
		return;
	}

	echo '<fieldset class="echo-category-list">';
	foreach ( $categories as $cat ) {
		printf(
			'<label><input type="checkbox" name="echo_settings[exclude_categories][]" value="%1$s" %2$s /><span>%3$s</span></label>',
			esc_attr( $cat->term_id ),
			checked( in_array( (int) $cat->term_id, array_map( 'intval', $excluded ), true ), true, false ),
			esc_html( $cat->name )
		);
	}
	echo '</fieldset>';

	if ( ! empty( $args['desc'] ) ) {
		printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
	}
}

/**
 * 在设置页加载 WordPress 取色器（iris）
 *
 * @param string $hook 当前页面 hook。
 * @return void
 */
function echo_settings_enqueue( $hook ) {
	// 仅在本主题设置页加载（兼容不同 hook 名）
	if ( false === strpos( $hook, 'echo-settings' ) ) {
		return;
	}

	wp_enqueue_media(); // 媒体上传器
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script(
		'echo-settings-js',
		get_template_directory_uri() . '/assets/js/settings.js',
		array( 'wp-color-picker', 'jquery' ),
		ECHO_VERSION,
		true
	);

	// 传递 AJAX 数据给前端脚本
	wp_localize_script(
		'echo-settings-js',
		'echoSettingsData',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'echo_settings_nonce' ),
		)
	);

	// 设置页专用样式
	wp_enqueue_style(
		'echo-admin-settings',
		get_template_directory_uri() . '/assets/css/admin-settings.css',
		array( 'wp-color-picker' ),
		ECHO_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'echo_settings_enqueue' );

/**
 * 输出前端动态 CSS 变量
 *
 * @return void
 */
function echo_settings_css() {
	$primary_color = echo_get_setting( 'primary_color', '#2563eb' );
	$content_width = absint( echo_get_setting( 'content_width', 1180 ) );
	$ui_style      = echo_get_setting( 'ui_style', 'classic' );

	// Miuix 风格下，若未自定义主题色，采用 HyperOS 蓝
	if ( 'miuix' === $ui_style && '#2563eb' === strtolower( (string) $primary_color ) ) {
		$primary_color = '#3482ff';
	}

	$primary_hover = echo_darken_hex( $primary_color, -18 );

	$css = ':root{';
	$css .= '--echo-primary:' . sanitize_hex_color( $primary_color ) . ';';
	$css .= '--echo-primary-hover:' . sanitize_hex_color( $primary_hover ) . ';';
	$css .= '--echo-content-width:' . $content_width . 'px;';
	$css .= '}';

	if ( 'left' === echo_get_setting( 'sidebar_position', 'right' ) ) {
		$css .= '.echo-layout{grid-template-columns:320px minmax(0,1fr);}';
		$css .= '.echo-sidebar{order:1;}';
		$css .= '.echo-content{order:2;}';
	}

	// 正文字号 / 行高 / 宽度、微调开关与自定义 CSS
	$css .= echo_extra_css();

	// 登录 / 注册页的自定义 CSS
	$css .= echo_auth_css();

	// 用户主页的自定义 CSS
	$css .= echo_user_css();

	wp_register_style( 'echo-settings-inline', false, array( 'echo-style' ), ECHO_VERSION );
	wp_enqueue_style( 'echo-settings-inline' );
	wp_add_inline_style( 'echo-settings-inline', $css );
}
add_action( 'wp_enqueue_scripts', 'echo_settings_css', 20 );

/**
 * 加载 Miuix 风格样式（仅在选用 miuix 时加载）
 *
 * @return void
 */
function echo_enqueue_style_layer() {
	if ( 'miuix' !== echo_get_setting( 'ui_style', 'classic' ) ) {
		return;
	}

	wp_enqueue_style(
		'echo-miuix',
		get_template_directory_uri() . '/assets/css/miuix.css',
		array( 'echo-style' ),
		ECHO_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'echo_enqueue_style_layer', 25 );

/**
 * 为 body 添加风格 class
 *
 * @param array $classes 已有 class。
 * @return array
 */
function echo_style_body_class( $classes ) {
	if ( 'miuix' === echo_get_setting( 'ui_style', 'classic' ) ) {
		$classes[] = 'echo-style-miuix';
	}
	return echo_extra_body_class( $classes );
}
add_filter( 'body_class', 'echo_style_body_class' );

/**
 * 固定链接变更后刷新重写规则
 *
 * sanitize 回调里先打标记，等重定向回后台页时再 flush，避免时机过早。
 *
 * @return void
 */
function echo_maybe_flush_rewrite() {
	if ( ! get_transient( 'echo_flush_rewrite' ) ) {
		return;
	}

	delete_transient( 'echo_flush_rewrite' );
	flush_rewrite_rules();
}
add_action( 'admin_init', 'echo_maybe_flush_rewrite', 999 );

/**
 * 新文章：中文 slug 自动改用文章 ID
 *
 * 仅处理含非 ASCII 字符的 slug，用户手动指定的英文 slug 保持不变。
 *
 * @param string $slug        原 slug。
 * @param int    $post_ID     文章 ID。
 * @param string $post_status 状态。
 * @param string $post_type   类型。
 * @return string
 */
function echo_ascii_post_slug( $slug, $post_ID, $post_status, $post_type ) {
	if ( 'post' !== $post_type || ! echo_get_setting( 'ascii_slug', false ) ) {
		return $slug;
	}

	if ( '' !== $slug && preg_match( '/[^\x20-\x7E]/', $slug ) ) {
		return (string) absint( $post_ID );
	}

	return $slug;
}
add_filter( 'wp_unique_post_slug', 'echo_ascii_post_slug', 10, 4 );

/**
 * 首页排除指定分类
 *
 * @param WP_Query $query 主查询对象。
 * @return void
 */
function echo_exclude_categories_from_home( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// 仅首页（博客文章列表）生效
	if ( ! $query->is_home() ) {
		return;
	}

	$excluded = echo_get_setting( 'exclude_categories', array() );
	$excluded = is_array( $excluded ) ? array_filter( array_map( 'absint', $excluded ) ) : array();

	if ( empty( $excluded ) ) {
		return;
	}

	$query->set( 'category__not_in', $excluded );
}
add_action( 'pre_get_posts', 'echo_exclude_categories_from_home' );

/**
 * 隐藏前台管理员工具栏（Admin Bar）
 *
 * 仅在启用设置时隐藏前台的黑条；后台不受影响。
 *
 * @param bool $show 是否显示。
 * @return bool
 */
function echo_hide_admin_bar( $show ) {
	if ( is_admin() ) {
		return $show;
	}

	if ( echo_get_setting( 'hide_admin_bar', false ) ) {
		return false;
	}

	return $show;
}
add_filter( 'show_admin_bar', 'echo_hide_admin_bar' );

/**
 * AJAX：即时保存单个设置项（用于开关等即时生效字段）
 *
 * @return void
 */
function echo_ajax_save_setting() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( '无权限', 'echo' ) ), 403 );
	}

	// 手动校验 nonce，失败时返回 JSON 而非 die(-1)，避免前端解析出错
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'echo_settings_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( '安全校验失败，请刷新页面后重试', 'echo' ) ), 403 );
	}

	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$val = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	// 仅允许白名单内的键，防止任意写入
	$allowed = array( 'hide_admin_bar', 'footer_credit', 'hide_tagline', 'show_category_badge', 'ascii_slug', 'admin_post_columns', 'primary_color', 'sidebar_position', 'content_width' );

	if ( ! in_array( $key, $allowed, true ) ) {
		wp_send_json_error( array( 'message' => __( '非法的设置项', 'echo' ) ), 400 );
	}

	$settings = echo_get_settings();

	// 按类型清洗
	switch ( $key ) {
		case 'hide_admin_bar':
		case 'footer_credit':
		case 'hide_tagline':
		case 'show_category_badge':
		case 'ascii_slug':
		case 'admin_post_columns':
			$settings[ $key ] = ( '1' === (string) $val || 'true' === (string) $val || true === $val );
			break;

		case 'primary_color':
			$clean = sanitize_hex_color( $val );
			if ( '' !== $clean ) {
				$settings[ $key ] = $clean;
			}
			break;

		case 'sidebar_position':
			$settings[ $key ] = ( 'left' === $val ) ? 'left' : 'right';
			break;

		case 'content_width':
			$settings[ $key ] = max( 800, min( 1600, absint( $val ) ) );
			break;
	}

	update_option( ECHO_OPTION, $settings );

	wp_send_json_success(
		array(
			'message' => __( '已保存', 'echo' ),
			'key'     => $key,
			'value'   => $settings[ $key ],
		)
	);
}
add_action( 'wp_ajax_echo_save_setting', 'echo_ajax_save_setting' );

/**
 * 调整十六进制颜色亮度（负值加深，正值提亮）
 *
 * @param string $hex   十六进制颜色，如 #2563eb。
 * @param int    $steps -255 ~ 255 的调整量。
 * @return string
 */
function echo_darken_hex( $hex, $steps ) {
	$hex = sanitize_hex_color( $hex );

	if ( '' === $hex ) {
		return '#1d4ed8';
	}

	$hex = ltrim( $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	$r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + $steps ) );
	$g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + $steps ) );
	$b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + $steps ) );

	return sprintf( '#%02x%02x%02x', $r, $g, $b );
}
