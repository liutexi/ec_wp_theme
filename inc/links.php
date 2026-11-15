<?php
/**
 * 友情链接
 *
 * - 数据存 option: echo_links（数组，每项 name/url/desc/logo/group）
 * - 后台在 Echo 设置页底部管理（AJAX 整表保存）
 * - 前台短代码 [echo_links]，支持分组与列数
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
		'links_cols'      => 3,
		'links_show_desc' => true,
		'links_show_logo' => true,
		'links_blank'     => true,
		'links_nofollow'  => true,
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
 * 取全部链接
 *
 * @return array
 */
function echo_links_all() {
	$links = get_option( 'echo_links', array() );
	if ( ! is_array( $links ) ) {
		return array();
	}

	$clean = array();
	foreach ( $links as $link ) {
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			continue;
		}
		$raw_name = isset( $link['name'] ) ? trim( (string) $link['name'] ) : '';
		if ( '' === $raw_name ) {
			$raw_name = (string) wp_parse_url( isset( $link['url'] ) ? (string) $link['url'] : '', PHP_URL_HOST );
		}

		$clean[] = array(
			'name'  => $raw_name,
			'url'   => isset( $link['url'] ) ? (string) $link['url'] : '',
			'desc'  => isset( $link['desc'] ) ? (string) $link['desc'] : '',
			'logo'  => isset( $link['logo'] ) ? (string) $link['logo'] : '',
			'group' => isset( $link['group'] ) ? (string) $link['group'] : '',
		);
	}

	return $clean;
}

/**
 * 保存链接（清洗后写入）
 *
 * @param array $links 原始数据。
 * @return int 保存条数。
 */
function echo_links_save( $links ) {
	$clean = array();

	if ( is_array( $links ) ) {
		foreach ( $links as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$url = isset( $link['url'] ) ? esc_url_raw( trim( (string) $link['url'] ) ) : '';
			if ( '' === $url ) {
				continue;
			}

			$name = isset( $link['name'] ) ? sanitize_text_field( $link['name'] ) : '';
			if ( '' === $name ) {
				$name = wp_parse_url( $url, PHP_URL_HOST );
			}

			$clean[] = array(
				'name'  => $name,
				'url'   => $url,
				'desc'  => isset( $link['desc'] ) ? sanitize_text_field( $link['desc'] ) : '',
				'logo'  => isset( $link['logo'] ) ? esc_url_raw( trim( (string) $link['logo'] ) ) : '',
				'group' => isset( $link['group'] ) ? sanitize_text_field( $link['group'] ) : '',
			);
		}
	}

	update_option( 'echo_links', $clean );

	return count( $clean );
}

/* ============================================================
 * 前台短代码
 * ============================================================ */

/**
 * [echo_links] 短代码
 *
 * 参数：
 * - group   只显示某个分组
 * - cols    列数 2-4
 * - desc    是否显示描述 1/0
 * - logo    是否显示图标 1/0
 *
 * @param array $atts 短代码属性。
 * @return string
 */
function echo_links_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'group' => '',
			'cols'  => '',
			'desc'  => '',
			'logo'  => '',
		),
		$atts,
		'echo_links'
	);

	$links = echo_links_all();
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

	$cols = '' !== $atts['cols'] ? absint( $atts['cols'] ) : absint( echo_links_config( 'links_cols', 3 ) );
	$cols = max( 2, min( 4, $cols ) );

	$show_desc = '' !== $atts['desc']
		? ( '1' === (string) $atts['desc'] )
		: (bool) echo_links_config( 'links_show_desc', true );
	$show_logo = '' !== $atts['logo']
		? ( '1' === (string) $atts['logo'] )
		: (bool) echo_links_config( 'links_show_logo', true );

	$blank    = (bool) echo_links_config( 'links_blank', true );
	$nofollow = (bool) echo_links_config( 'links_nofollow', true );

	// 按分组归并（空分组归到「友情链接」）
	$groups = array();
	foreach ( $links as $link ) {
		$key = '' !== $link['group'] ? $link['group'] : '';
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array();
		}
		$groups[ $key ][] = $link;
	}

	$rel   = $nofollow ? 'nofollow noopener' : 'noopener';
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
						<a class="echo-link-card" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput ?> rel="<?php echo esc_attr( $rel ); ?>">
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
								<?php if ( $show_desc && '' !== $link['desc'] ) : ?>
									<span class="echo-link-card__desc"><?php echo esc_html( $link['desc'] ); ?></span>
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
add_shortcode( 'echo_links', 'echo_links_shortcode' );

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

	$links = echo_links_all();
	?>
	<div id="echo-links-manager" class="echo-links-manager">
		<h2><?php esc_html_e( '友情链接', 'echo' ); ?></h2>
		<p class="description">
			<?php esc_html_e( '在页面或文章中插入短代码即可显示：', 'echo' ); ?>
			<code>[echo_links]</code>
			<?php esc_html_e( '，可用参数 group、cols、desc、logo，例如', 'echo' ); ?>
			<code>[echo_links group="友链" cols="4"]</code>
		</p>

		<table class="widefat striped echo-link-table">
			<thead>
				<tr>
					<th class="echo-link-col-name"><?php esc_html_e( '名称', 'echo' ); ?></th>
					<th class="echo-link-col-url"><?php esc_html_e( '网址', 'echo' ); ?></th>
					<th class="echo-link-col-desc"><?php esc_html_e( '描述', 'echo' ); ?></th>
					<th class="echo-link-col-group"><?php esc_html_e( '分组', 'echo' ); ?></th>
					<th class="echo-link-col-logo"><?php esc_html_e( '图标地址', 'echo' ); ?></th>
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
			<span class="description"><?php esc_html_e( '名称留空会自动使用域名；网址为空的行不会保存。', 'echo' ); ?></span>
		</p>

		<template id="echo-link-row-template">
			<?php echo echo_links_manager_rows( array( array( 'name' => '', 'url' => '', 'desc' => '', 'logo' => '', 'group' => '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
		return '<tr class="echo-link-empty"><td colspan="6">' . esc_html__( '还没有友情链接，点下方「添加一条」开始。', 'echo' ) . '</td></tr>';
	}

	$html = '';
	foreach ( $links as $link ) {
		$html .= sprintf(
			'<tr class="echo-link-row">
				<td><input type="text" class="echo-link-name regular-text" value="%1$s" placeholder="%7$s" /></td>
				<td><input type="url" class="echo-link-url regular-text" value="%2$s" placeholder="https://" /></td>
				<td><input type="text" class="echo-link-desc regular-text" value="%3$s" placeholder="%8$s" /></td>
				<td><input type="text" class="echo-link-group regular-text" value="%4$s" placeholder="%9$s" /></td>
				<td><input type="url" class="echo-link-logo regular-text" value="%5$s" placeholder="https://" /></td>
				<td class="echo-link-actions-cell">
					<button type="button" class="button-link echo-link-up" aria-label="%10$s">↑</button>
					<button type="button" class="button-link echo-link-down" aria-label="%11$s">↓</button>
					<button type="button" class="button-link echo-link-remove" aria-label="%12$s">%12$s</button>
				</td>
			</tr>',
			esc_attr( $link['name'] ),
			esc_attr( $link['url'] ),
			esc_attr( $link['desc'] ),
			esc_attr( $link['group'] ),
			esc_attr( $link['logo'] ),
			'',
			esc_attr__( '网站名称', 'echo' ),
			esc_attr__( '一句话描述', 'echo' ),
			esc_attr__( '可选，如「友链」', 'echo' ),
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

	$raw = isset( $_POST['links'] ) ? wp_unslash( $_POST['links'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
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

	foreach ( array( 'links_show_desc', 'links_show_logo', 'links_blank', 'links_nofollow' ) as $bool_key ) {
		$output[ $bool_key ] = isset( $input[ $bool_key ] ) && '1' === (string) $input[ $bool_key ];
	}

	$output['links_cols'] = max( 2, min( 4, absint( isset( $input['links_cols'] ) ? $input['links_cols'] : $defaults['links_cols'] ) ) );
}
