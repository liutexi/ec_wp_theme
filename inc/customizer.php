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
	return array(
		'primary_color'    => '#2563eb',
		'sidebar_position' => 'right',
		'content_width'    => 1180,
		'footer_text'      => '',
		'footer_credit'    => true,
		'hide_tagline'     => false,
		'custom_logo'      => 0,
		'hide_admin_bar'   => false,
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

	$width                   = isset( $input['content_width'] ) ? absint( $input['content_width'] ) : $defaults['content_width'];
	$output['content_width'] = max( 800, min( 1600, $width ) );

	$output['footer_text'] = isset( $input['footer_text'] ) ? wp_kses_post( $input['footer_text'] ) : '';

	// 复选框：勾选时值为 "1"，未勾选时字段不提交，需分别处理
	$output['footer_credit']  = isset( $input['footer_credit'] ) && '1' === (string) $input['footer_credit'];
	$output['hide_tagline']   = isset( $input['hide_tagline'] ) && '1' === (string) $input['hide_tagline'];
	$output['hide_admin_bar'] = isset( $input['hide_admin_bar'] ) && '1' === (string) $input['hide_admin_bar'];

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
	<div class="wrap">
		<h1><?php esc_html_e( 'Echo 主题设置', 'echo' ); ?></h1>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'echo_settings_group' );
			do_settings_sections( 'echo-settings' );
			submit_button( __( '保存设置', 'echo' ) );
			?>
		</form>

		<!-- 即时保存用的 AJAX 数据（兜底，避免 localize 失效） -->
		<div id="echo-ajax-data" style="display:none;"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'echo_settings_nonce' ) ); ?>"></div>
	</div>
	<?php
}

/* ============================================================
 * 字段渲染
 * ============================================================ */

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
	<fieldset>
		<label>
			<input type="radio" name="echo_settings[sidebar_position]" value="right" <?php checked( $value, 'right' ); ?> />
			<?php esc_html_e( '右侧（默认）', 'echo' ); ?>
		</label><br />
		<label>
			<input type="radio" name="echo_settings[sidebar_position]" value="left" <?php checked( $value, 'left' ); ?> />
			<?php esc_html_e( '左侧', 'echo' ); ?>
		</label>
	</fieldset>
	<?php
}

/**
 * 内容宽度字段
 *
 * @param array $args 字段参数。
 * @return void
 */
function echo_field_content_width( $args ) {
	$value = echo_get_setting( 'content_width', 1180 );
	printf(
		'<input type="number" id="echo_content_width" name="echo_settings[content_width]" value="%s" min="800" max="1600" step="20" class="small-text" /> ',
		esc_attr( $value )
	);
	esc_html_e( 'px', 'echo' );

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
	$key   = $args['label_for'];
	$value = echo_get_setting( $key, false );
	printf(
		'<input type="checkbox" id="%1$s" name="echo_settings[%1$s]" value="1" %2$s data-instant="1" data-key="%1$s" />',
		esc_attr( $key ),
		checked( $value, true, false )
	);

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

	// 设置页内联样式（Logo 预览框 + 右下角 toast）
	$admin_css = '
		.echo-logo-preview-wrap {
			margin-bottom: 10px;
			padding: 12px;
			border: 1px dashed #c3c4c7;
			border-radius: 4px;
			background: #fff;
			min-height: 60px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
		}
		.echo-logo-preview {
			max-height: 60px;
			max-width: 200px;
			width: auto;
			height: auto;
			display: block;
		}
		.echo-logo-placeholder {
			color: #8c8f94;
		}
		.echo-toast {
			position: fixed;
			right: 24px;
			bottom: 24px;
			z-index: 999999;
			padding: 12px 20px;
			border-radius: 8px;
			font-size: 14px;
			line-height: 1.4;
			color: #fff;
			box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
			opacity: 0;
			transform: translateY(16px);
			pointer-events: none;
			transition: opacity 0.25s ease, transform 0.25s ease;
		}
		.echo-toast--show {
			opacity: 1;
			transform: translateY(0);
		}
		.echo-toast--success {
			background: #16a34a;
		}
		.echo-toast--error {
			background: #dc2626;
		}
	';
	wp_add_inline_style( 'wp-color-picker', $admin_css );
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

	wp_register_style( 'echo-settings-inline', false, array( 'echo-style' ), ECHO_VERSION );
	wp_enqueue_style( 'echo-settings-inline' );
	wp_add_inline_style( 'echo-settings-inline', $css );
}
add_action( 'wp_enqueue_scripts', 'echo_settings_css', 20 );

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
	$allowed = array( 'hide_admin_bar', 'footer_credit', 'hide_tagline', 'primary_color', 'sidebar_position', 'content_width' );

	if ( ! in_array( $key, $allowed, true ) ) {
		wp_send_json_error( array( 'message' => __( '非法的设置项', 'echo' ) ), 400 );
	}

	$settings = echo_get_settings();

	// 按类型清洗
	switch ( $key ) {
		case 'hide_admin_bar':
		case 'footer_credit':
		case 'hide_tagline':
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
