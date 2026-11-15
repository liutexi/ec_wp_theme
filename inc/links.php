<?php
/**
 * 友情链接
 *
 * - 数据存 option: echo_links（数组，每项 name/url/desc/logo/group/enabled）
 * - 后台在 Echo 设置页底部管理（AJAX 整表保存，支持拖拽排序与批量导入导出）
 * - 前台：短代码 [echo_links]、页面模板「友情链接」、侧边栏小工具
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
function echo_links_defaults() {
	return array(
		'links_cols'         => 3,
		'links_show_desc'    => true,
		'links_show_logo'    => true,
		'links_blank'        => true,
		'links_nofollow'     => true,
		'links_show_sidebar' => false,
	);
}

/**
 * 读取设置
 *
 * @param string $key     键名。
 * @param mixed  $default 缺省值。
 * @return mixed
 */
function echo_links_config( $key, $default = null ) {
	$settings = echo_get_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * 取全部链接（含停用项，后台用）
 *
 * @return array
 */
function echo_links_all() {
	$links = get_option( 'echo_links', array() );
	if ( ! is_array( $links ) ) {
		return array();
	}

	$clean = array();
	$seen  = array();

	foreach ( $links as $link ) {
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			continue;
		}

		$url = trim( (string) $link['url'] );
		if ( '' === $url ) {
			continue;
		}

		// 只接受 http / https，且同一网址只保留第一条
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
			continue;
		}

		$key = strtolower( rtrim( $url, '/' ) );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;

		$raw_name = isset( $link['name'] ) ? trim( (string) $link['name'] ) : '';
		if ( '' === $raw_name ) {
			$raw_name = (string) wp_parse_url( $url, PHP_URL_HOST );
		}

		$clean[] = array(
			'name'    => $raw_name,
			'url'     => $url,
			'desc'    => isset( $link['desc'] ) ? (string) $link['desc'] : '',
			'logo'    => isset( $link['logo'] ) ? (string) $link['logo'] : '',
			'group'   => isset( $link['group'] ) ? (string) $link['group'] : '',
			'enabled' => isset( $link['enabled'] ) ? (bool) $link['enabled'] : true,
		);
	}

	return $clean;
}

/**
 * 只取启用的链接（前台用）
 *
 * @return array
 */
function echo_links_visible() {
	return array_values(
		array_filter(
			echo_links_all(),
			function ( $link ) {
				return ! empty( $link['enabled'] );
			}
		)
	);
}

/**
 * 保存链接（清洗后写入）
 *
 * @param array $links 原始数据。
 * @return int 保存条数。
 */
function echo_links_save( $links ) {
	$clean = array();
	$seen  = array();

	if ( is_array( $links ) ) {
		foreach ( $links as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$url = isset( $link['url'] ) ? esc_url_raw( trim( (string) $link['url'] ) ) : '';
			if ( '' === $url ) {
				continue;
			}

			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			if ( ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
				continue;
			}

			$key = strtolower( rtrim( $url, '/' ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$name = isset( $link['name'] ) ? sanitize_text_field( $link['name'] ) : '';
			if ( '' === $name ) {
				$name = (string) wp_parse_url( $url, PHP_URL_HOST );
			}

			$clean[] = array(
				'name'    => $name,
				'url'     => $url,
				'desc'    => isset( $link['desc'] ) ? sanitize_text_field( $link['desc'] ) : '',
				'logo'    => isset( $link['logo'] ) ? esc_url_raw( trim( (string) $link['logo'] ) ) : '',
				'group'   => isset( $link['group'] ) ? sanitize_text_field( $link['group'] ) : '',
				'enabled' => isset( $link['enabled'] ) ? (bool) $link['enabled'] : true,
			);
		}
	}

	update_option( 'echo_links', $clean );

	return count( $clean );
}

/**
 * 所有分组名
 *
 * @return array
 */
function echo_links_groups() {
	$groups = array();
	foreach ( echo_links_all() as $link ) {
		if ( '' !== $link['group'] ) {
			$groups[ $link['group'] ] = true;
		}
	}
	return array_keys( $groups );
}

/* ============================================================
 * 前台短代码
 * ============================================================ */

/**
 * [echo_links] 短代码
 *
 * 参数：
 * - group    只显示某个分组
 * - cols     列数 2-4
 * - desc     是否显示描述 1/0
 * - logo     是否显示图标 1/0
 * - orderby  排序：default / name / rand
 *
 * @param array $atts 短代码属性。
 * @return string
 */
function echo_links_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'group'   => '',
			'cols'    => '',
			'desc'    => '',
			'logo'    => '',
			'orderby' => '',
		),
		$atts,
		'echo_links'
	);

	$links = echo_links_visible();
	if ( empty( $links ) ) {
		return '';
	}

	$group = sanitize_text_field( (string) $atts['group'] );
	if ( '' !== $group ) {
		$links = array_values(
			array_filter(
				$links,
				function ( $link ) use ( $group ) {
					return $link['group'] === $group;
				}
			)
		);
	}

	if ( empty( $links ) ) {
		return '';
	}

	$orderby = sanitize_key( (string) $atts['orderby'] );
	if ( 'name' === $orderby ) {
		usort(
			$links,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
	} elseif ( 'rand' === $orderby ) {
		shuffle( $links );
	}

	return echo_links_render_grid(
		$links,
		array(
			'cols' => $atts['cols'],
			'desc' => $atts['desc'],
			'logo' => $atts['logo'],
		)
	);
}
add_shortcode( 'echo_links', 'echo_links_shortcode' );

/**
 * 渲染链接网格（短代码 / 页面模板 / 小工具共用）
 *
 * @param array $links 链接。
 * @param array $args  参数。
 * @return string
 */
function echo_links_render_grid( $links, $args = array() ) {
	if ( empty( $links ) ) {
		return '';
	}

	$cols = isset( $args['cols'] ) && '' !== $args['cols'] ? absint( $args['cols'] ) : absint( echo_links_config( 'links_cols', 3 ) );
	$cols = max( 2, min( 4, $cols ) );

	$show_desc = ( isset( $args['desc'] ) && '' !== $args['desc'] )
		? ( '1' === (string) $args['desc'] )
		: (bool) echo_links_config( 'links_show_desc', true );

	$show_logo = ( isset( $args['logo'] ) && '' !== $args['logo'] )
		? ( '1' === (string) $args['logo'] )
		: (bool) echo_links_config( 'links_show_logo', true );

	$blank    = (bool) echo_links_config( 'links_blank', true );
	$nofollow = (bool) echo_links_config( 'links_nofollow', true );

	// 按分组归并（空分组不输出组标题）
	$groups = array();
	foreach ( $links as $link ) {
		$key = '' !== $link['group'] ? $link['group'] : '';
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array();
		}
		$groups[ $key ][] = $link;
	}

	$rel    = $nofollow ? 'nofollow noopener' : 'noopener';
	$target = $blank ? ' target="_blank"' : '';

	ob_start();
	?>
	<div class="echo-links echo-links--cols-<?php echo (int) $cols; ?>">
		<?php foreach ( $groups as $group_name => $group_links ) : ?>
			<section class="echo-links__group">
				<?php if ( '' !== $group_name ) : ?>
					<h2 class="echo-links__title"><?php echo esc_html( $group_name ); ?></h2>
				<?php endif; ?>
				<div class="echo-links__grid">
					<?php foreach ( $group_links as $link ) : ?>
						<?php
						$host = (string) wp_parse_url( $link['url'], PHP_URL_HOST );
						$desc = $link['desc'];
						if ( '' === $desc ) {
							$desc = $host;
						}
						?>
						<a class="echo-link-card" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput ?> rel="<?php echo esc_attr( $rel ); ?>" title="<?php echo esc_attr( $link['name'] . ( '' !== $link['desc'] ? ' — ' . $link['desc'] : '' ) ); ?>">
							<?php if ( $show_logo ) : ?>
								<span class="echo-link-card__logo">
									<?php if ( '' !== $link['logo'] ) : ?>
										<img src="<?php echo esc_url( $link['logo'] ); ?>" alt="" loading="lazy" />
									<?php else : ?>
										<span class="echo-link-card__initial" aria-hidden="true"><?php echo esc_html( mb_substr( $link['name'], 0, 1 ) ); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<span class="echo-link-card__body">
								<span class="echo-link-card__name"><?php echo esc_html( $link['name'] ); ?></span>
								<?php if ( $show_desc && '' !== $desc ) : ?>
									<span class="echo-link-card__desc"><?php echo esc_html( $desc ); ?></span>
								<?php endif; ?>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ============================================================
 * 后台管理
 * ============================================================ */

/**
 * 后台友情链接管理区块
 *
 * @return void
 */
function echo_render_links_manager() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$links  = echo_links_all();
	$groups = echo_links_groups();
	?>
	<div id="echo-links-manager" class="echo-links-manager">
		<h2>
			<?php esc_html_e( '友情链接', 'echo' ); ?>
			<span class="echo-link-count"><?php echo esc_html( sprintf( /* translators: %d: 条数 */ __( '共 %d 条', 'echo' ), count( $links ) ) ); ?></span>
		</h2>
		<p class="description">
			<?php esc_html_e( '短代码：', 'echo' ); ?><code>[echo_links]</code>
			<?php esc_html_e( '，参数 group、cols、desc、logo、orderby，例如', 'echo' ); ?>
			<code>[echo_links group="友链" cols="4"]</code>
			<?php esc_html_e( '；也可新建页面并选「友情链接」模板，或用侧边栏小工具。', 'echo' ); ?>
		</p>

		<datalist id="echo-link-groups">
			<?php foreach ( $groups as $group_name ) : ?>
				<option value="<?php echo esc_attr( $group_name ); ?>"></option>
			<?php endforeach; ?>
		</datalist>

		<table class="widefat striped echo-link-table">
			<thead>
				<tr>
					<th class="echo-link-col-enabled"><?php esc_html_e( '启用', 'echo' ); ?></th>
					<th class="echo-link-col-name"><?php esc_html_e( '名称', 'echo' ); ?></th>
					<th class="echo-link-col-url"><?php esc_html_e( '网址', 'echo' ); ?></th>
					<th class="echo-link-col-desc"><?php esc_html_e( '描述', 'echo' ); ?></th>
					<th class="echo-link-col-group"><?php esc_html_e( '分组', 'echo' ); ?></th>
					<th class="echo-link-col-logo"><?php esc_html_e( '图标', 'echo' ); ?></th>
					<th class="echo-link-col-actions"><?php esc_html_e( '操作', 'echo' ); ?></th>
				</tr>
			</thead>
			<tbody id="echo-link-rows">
				<?php echo echo_links_manager_rows( $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</tbody>
		</table>

		<p class="echo-link-actions">
			<button type="button" class="button" id="echo-link-add"><?php esc_html_e( '添加一条', 'echo' ); ?></button>
			<button type="button" class="button button-primary" id="echo-link-save"><?php esc_html_e( '保存友情链接', 'echo' ); ?></button>
			<span class="description"><?php esc_html_e( '拖拽行可调整顺序；名称留空自动用域名；网址为空或重复的行不会保存。', 'echo' ); ?></span>
		</p>

		<details class="echo-link-bulk">
			<summary><?php esc_html_e( '批量导入 / 导出', 'echo' ); ?></summary>
			<p class="description">
				<?php esc_html_e( '每行一条，用竖线分隔：名称 | 网址 | 描述 | 分组 | 图标（后四项可留空，网址必填）。', 'echo' ); ?>
			</p>
			<textarea id="echo-link-bulk-text" rows="6" class="large-text code" placeholder="WordPress 中文 | https://cn.wordpress.org | 官方中文站 | 友链"></textarea>
			<p>
				<button type="button" class="button" id="echo-link-import"><?php esc_html_e( '导入到列表', 'echo' ); ?></button>
				<button type="button" class="button" id="echo-link-export"><?php esc_html_e( '导出当前列表', 'echo' ); ?></button>
				<span class="description"><?php esc_html_e( '导入后记得点「保存友情链接」。', 'echo' ); ?></span>
			</p>
		</details>

		<template id="echo-link-row-template">
			<?php echo echo_links_manager_rows( array( array( 'name' => '', 'url' => '', 'desc' => '', 'logo' => '', 'group' => '', 'enabled' => true ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</template>
	</div>
	<?php
}

/**
 * 输出链接行
 *
 * @param array $links 链接列表。
 * @return string
 */
function echo_links_manager_rows( $links ) {
	if ( empty( $links ) ) {
		return '<tr class="echo-link-empty"><td colspan="7">' . esc_html__( '还没有友情链接，点下方「添加一条」开始。', 'echo' ) . '</td></tr>';
	}

	$html = '';
	foreach ( $links as $link ) {
		$enabled = ! isset( $link['enabled'] ) || $link['enabled'];

		$html .= sprintf(
			'<tr class="echo-link-row" draggable="true">
				<td class="echo-link-col-enabled"><input type="checkbox" class="echo-link-enabled" value="1" %1$s /></td>
				<td><input type="text" class="echo-link-name regular-text" value="%2$s" placeholder="%8$s" /></td>
				<td><input type="url" class="echo-link-url regular-text" value="%3$s" placeholder="https://" /></td>
				<td><input type="text" class="echo-link-desc regular-text" value="%4$s" placeholder="%9$s" /></td>
				<td><input type="text" class="echo-link-group regular-text" value="%5$s" placeholder="%10$s" list="echo-link-groups" /></td>
				<td class="echo-link-logo-cell">
					<div class="echo-link-logo-row">
						<input type="url" class="echo-link-logo regular-text" value="%6$s" placeholder="https://" />
						<button type="button" class="button echo-link-logo-pick">%11$s</button>
					</div>
				</td>
				<td class="echo-link-actions-cell">
					<span class="echo-link-drag" title="%12$s" aria-hidden="true">⋮⋮</span>
					<button type="button" class="button-link echo-link-up" aria-label="%13$s">↑</button>
					<button type="button" class="button-link echo-link-down" aria-label="%14$s">↓</button>
					<button type="button" class="button-link echo-link-remove" aria-label="%15$s">%15$s</button>
				</td>
			</tr>',
			$enabled ? 'checked' : '',
			esc_attr( $link['name'] ),
			esc_attr( $link['url'] ),
			esc_attr( $link['desc'] ),
			esc_attr( $link['group'] ),
			esc_attr( $link['logo'] ),
			'',
			esc_attr__( '网站名称', 'echo' ),
			esc_attr__( '一句话描述', 'echo' ),
			esc_attr__( '可选，如「友链」', 'echo' ),
			esc_attr__( '选择', 'echo' ),
			esc_attr__( '拖拽排序', 'echo' ),
			esc_attr__( '上移', 'echo' ),
			esc_attr__( '下移', 'echo' ),
			esc_attr__( '删除', 'echo' )
		);
	}

	return $html;
}

/**
 * AJAX：保存友情链接
 *
 * @return void
 */
function echo_ajax_save_links() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( '权限不足。', 'echo' ) ), 403 );
	}

	check_ajax_referer( 'echo_settings_nonce', 'nonce' );

	$raw  = isset( $_POST['links'] ) ? wp_unslash( $_POST['links'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$data = json_decode( (string) $raw, true );

	if ( ! is_array( $data ) ) {
		wp_send_json_error( array( 'message' => __( '数据格式有误。', 'echo' ) ) );
	}

	$count = echo_links_save( $data );

	wp_send_json_success(
		array(
			'message' => sprintf( /* translators: %d: 条数 */ __( '已保存 %d 条友情链接。', 'echo' ), $count ),
			'count'   => $count,
		)
	);
}
add_action( 'wp_ajax_echo_save_links', 'echo_ajax_save_links' );

/* ============================================================
 * 侧边栏小工具
 * ============================================================ */

/**
 * 友情链接小工具
 */
class Echo_Links_Widget extends WP_Widget {

	/**
	 * 构造
	 */
	public function __construct() {
		parent::__construct(
			'echo_links_widget',
			__( 'Echo 友情链接', 'echo' ),
			array( 'description' => __( '以列表形式显示友情链接。', 'echo' ) )
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
		$title = isset( $instance['title'] ) ? $instance['title'] : __( '友情链接', 'echo' );
		$group = isset( $instance['group'] ) ? $instance['group'] : '';
		$limit = isset( $instance['limit'] ) ? max( 0, absint( $instance['limit'] ) ) : 0;

		$links = echo_links_visible();
		if ( '' !== $group ) {
			$links = array_values(
				array_filter(
					$links,
					function ( $link ) use ( $group ) {
						return $link['group'] === $group;
					}
				)
			);
		}
		if ( $limit > 0 ) {
			$links = array_slice( $links, 0, $limit );
		}

		if ( empty( $links ) ) {
			return;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$blank    = (bool) echo_links_config( 'links_blank', true );
		$nofollow = (bool) echo_links_config( 'links_nofollow', true );
		$rel      = $nofollow ? 'nofollow noopener' : 'noopener';
		$target   = $blank ? ' target="_blank"' : '';

		echo '<ul class="echo-links-widget">';
		foreach ( $links as $link ) {
			printf(
				'<li><a href="%1$s"%2$s rel="%3$s">%4$s</a></li>',
				esc_url( $link['url'] ),
				$target, // phpcs:ignore WordPress.Security.EscapeOutput
				esc_attr( $rel ),
				esc_html( $link['name'] )
			);
		}
		echo '</ul>';

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * 后台表单
	 *
	 * @param array $instance 当前设置。
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( '友情链接', 'echo' );
		$group = isset( $instance['group'] ) ? $instance['group'] : '';
		$limit = isset( $instance['limit'] ) ? absint( $instance['limit'] ) : 0;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( '标题：', 'echo' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'group' ) ); ?>"><?php esc_html_e( '分组（留空显示全部）：', 'echo' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'group' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'group' ) ); ?>" type="text"
				value="<?php echo esc_attr( $group ); ?>" list="echo-link-groups" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( '最多显示条数（0 为不限）：', 'echo' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="0" step="1"
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
			'group' => sanitize_text_field( isset( $new_instance['group'] ) ? $new_instance['group'] : '' ),
			'limit' => max( 0, absint( isset( $new_instance['limit'] ) ? $new_instance['limit'] : 0 ) ),
		);
	}
}

/**
 * 注册小工具
 *
 * @return void
 */
function echo_links_register_widget() {
	register_widget( 'Echo_Links_Widget' );
}
add_action( 'widgets_init', 'echo_links_register_widget' );

/* ============================================================
 * 清洗
 * ============================================================ */

/**
 * 清洗友情链接设置
 *
 * @param array $input  原始提交。
 * @param array $output 输出。
 * @return void
 */
function echo_sanitize_links_settings( $input, &$output ) {
	$defaults = echo_links_defaults();

	$has_links = false;
	foreach ( array_keys( $defaults ) as $key ) {
		if ( array_key_exists( $key, $input ) ) {
			$has_links = true;
			break;
		}
	}

	if ( ! $has_links ) {
		foreach ( $defaults as $key => $value ) {
			$output[ $key ] = echo_get_setting( $key, $value );
		}
		return;
	}

	foreach ( array( 'links_show_desc', 'links_show_logo', 'links_blank', 'links_nofollow', 'links_show_sidebar' ) as $bool_key ) {
		$output[ $bool_key ] = isset( $input[ $bool_key ] ) && '1' === (string) $input[ $bool_key ];
	}

	$output['links_cols'] = max( 2, min( 4, absint( isset( $input['links_cols'] ) ? $input['links_cols'] : $defaults['links_cols'] ) ) );
}
