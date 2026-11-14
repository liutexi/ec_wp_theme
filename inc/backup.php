<?php
/**
 * Echo 配置备份 / 导出 / 导入 / 定时备份
 *
 * 配置快照存到独立选项 echo_backup_options，避免被主设置表单的
 * sanitize 回调重建时冲掉。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ECHO_BACKUP_OPTION', 'echo_backup_options' );
define( 'ECHO_BACKUP_HOOK', 'echo_auto_backup_hook' );
define( 'ECHO_BACKUP_TYPE', 'echo-theme-settings' );
define( 'ECHO_BACKUP_MAX_BYTES', 1048576 ); // 1MB

/* ============================================================
 * 1. 备份选项
 * ============================================================ */

/**
 * 读取备份设置
 *
 * @return array
 */
function echo_get_backup_options() {
	$defaults = array(
		'enabled'   => false,
		'frequency' => 'daily',
		'keep'      => 10,
	);

	$saved = get_option( ECHO_BACKUP_OPTION, array() );
	$saved = is_array( $saved ) ? $saved : array();

	$opts = wp_parse_args( $saved, $defaults );

	$opts['enabled']   = (bool) $opts['enabled'];
	$opts['frequency'] = in_array( $opts['frequency'], array( 'twicedaily', 'daily', 'weekly' ), true ) ? $opts['frequency'] : 'daily';
	$opts['keep']      = max( 1, min( 50, absint( $opts['keep'] ) ) );

	return $opts;
}

/* ============================================================
 * 2. 备份目录
 * ============================================================ */

/**
 * 取得（并按需创建）备份目录
 *
 * @return string|false
 */
function echo_backup_dir() {
	$upload = wp_upload_dir();

	if ( empty( $upload['basedir'] ) ) {
		return false;
	}

	$dir = trailingslashit( $upload['basedir'] ) . 'echo-backups/';

	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return false;
	}

	// 禁止 Web 直接访问备份文件
	if ( ! file_exists( $dir . '.htaccess' ) ) {
		file_put_contents( $dir . '.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if ( ! file_exists( $dir . 'index.php' ) ) {
		file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	return $dir;
}

/**
 * 校验备份文件名，防路径穿越
 *
 * @param string $name 文件名。
 * @return bool
 */
function echo_is_valid_backup_name( $name ) {
	return (bool) preg_match( '/^echo-backup-(auto|manual|preimport)-\d{8}-\d{6}(-\d+)?\.json$/', (string) $name );
}

/* ============================================================
 * 3. 快照数据
 * ============================================================ */

/**
 * 生成配置快照
 *
 * @return array
 */
function echo_build_export_data() {
	return array(
		'type'        => ECHO_BACKUP_TYPE,
		'version'     => ECHO_VERSION,
		'exported_at' => current_time( 'mysql' ),
		'site'        => home_url(),
		'settings'    => echo_get_settings(),
		'wp_options'  => array(
			'blogname'            => get_option( 'blogname' ),
			'blogdescription'     => get_option( 'blogdescription' ),
			'permalink_structure' => get_option( 'permalink_structure' ),
			'default_category'    => absint( get_option( 'default_category' ) ),
		),
		'theme_mods'  => array(
			'custom_logo' => absint( get_theme_mod( 'custom_logo' ) ),
		),
	);
}

/**
 * 应用配置快照（导入 / 恢复共用）
 *
 * @param array $data 快照数据。
 * @return true|WP_Error
 */
function echo_apply_export_data( $data ) {
	if ( ! is_array( $data ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
		return new WP_Error( 'echo_bad_data', __( '配置数据格式不正确', 'echo' ) );
	}

	if ( isset( $data['type'] ) && ECHO_BACKUP_TYPE !== $data['type'] ) {
		return new WP_Error( 'echo_bad_type', __( '这不是 Echo 主题的配置文件', 'echo' ) );
	}

	// 先备份当前配置，导入失败也能回滚
	echo_write_backup( echo_build_export_data(), 'preimport' );

	// 主题设置：走统一的 sanitize，过滤掉白名单外的键
	$clean = echo_sanitize_settings( $data['settings'] );
	update_option( ECHO_OPTION, $clean );

	// WordPress 核心选项：严格白名单
	$allowed_options = array( 'blogname', 'blogdescription', 'permalink_structure', 'default_category' );

	if ( ! empty( $data['wp_options'] ) && is_array( $data['wp_options'] ) ) {
		$permalink_changed = false;

		foreach ( $allowed_options as $key ) {
			if ( ! array_key_exists( $key, $data['wp_options'] ) ) {
				continue;
			}

			$value = $data['wp_options'][ $key ];

			switch ( $key ) {
				case 'blogname':
				case 'blogdescription':
					update_option( $key, sanitize_text_field( $value ) );
					break;

				case 'default_category':
					$cat_id = absint( $value );
					if ( $cat_id && term_exists( $cat_id, 'category' ) ) {
						update_option( $key, $cat_id );
					}
					break;

				case 'permalink_structure':
					// 恢复备份要原样还原，只做字符安全校验，不改写结构
					$struct = is_string( $value ) ? trim( $value ) : '';
					if ( '' === $struct ) {
						break;
					}

					$tags = array( '%post_id%', '%postname%', '%year%', '%monthnum%', '%day%', '%category%', '%author%' );
					$rest = str_replace( $tags, '', $struct );

					if ( preg_match( '#[^a-zA-Z0-9/_\-\.]#', $rest ) ) {
						break;
					}

					if ( get_option( 'permalink_structure' ) !== $struct ) {
						update_option( $key, $struct );
						$permalink_changed = true;
					}
					break;
			}
		}

		if ( $permalink_changed ) {
			set_transient( 'echo_flush_rewrite', 1, 60 );
		}
	}

	// 站点 Logo
	if ( ! empty( $data['theme_mods']['custom_logo'] ) ) {
		set_theme_mod( 'custom_logo', absint( $data['theme_mods']['custom_logo'] ) );
	}

	return true;
}

/* ============================================================
 * 4. 备份文件读写
 * ============================================================ */

/**
 * 写入一份备份
 *
 * @param array  $data   快照数据。
 * @param string $prefix auto | manual | preimport。
 * @return string|false 文件名。
 */
function echo_write_backup( $data, $prefix = 'manual' ) {
	$dir = echo_backup_dir();

	if ( ! $dir ) {
		return false;
	}

	$prefix = in_array( $prefix, array( 'auto', 'manual', 'preimport' ), true ) ? $prefix : 'manual';
	$base   = 'echo-backup-' . $prefix . '-' . gmdate( 'Ymd-His' );
	$name   = $base . '.json';
	$i      = 1;

	while ( file_exists( $dir . $name ) ) {
		$name = $base . '-' . $i . '.json';
		++$i;
	}

	$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

	if ( ! $json ) {
		return false;
	}

	$written = file_put_contents( $dir . $name, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	return $written ? $name : false;
}

/**
 * 按保留份数清理旧备份
 *
 * @param int $keep 保留份数。
 * @return int 删除数量。
 */
function echo_prune_backups( $keep ) {
	$dir = echo_backup_dir();

	if ( ! $dir ) {
		return 0;
	}

	$files = glob( $dir . 'echo-backup-*.json' );

	if ( empty( $files ) ) {
		return 0;
	}

	usort(
		$files,
		function ( $a, $b ) {
			return filemtime( $b ) - filemtime( $a );
		}
	);

	$deleted = 0;

	foreach ( array_slice( $files, max( 1, absint( $keep ) ) ) as $file ) {
		if ( unlink( $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			++$deleted;
		}
	}

	return $deleted;
}

/**
 * 列出备份文件
 *
 * @return array
 */
function echo_list_backups() {
	$dir = echo_backup_dir();

	if ( ! $dir ) {
		return array();
	}

	$files = glob( $dir . 'echo-backup-*.json' );
	$out   = array();

	foreach ( (array) $files as $file ) {
		$name = basename( $file );

		if ( ! echo_is_valid_backup_name( $name ) ) {
			continue;
		}

		$out[] = array(
			'name' => $name,
			'size' => filesize( $file ),
			'time' => filemtime( $file ),
		);
	}

	usort(
		$out,
		function ( $a, $b ) {
			return $b['time'] - $a['time'];
		}
	);

	return $out;
}

/**
 * 生成备份列表表格行
 *
 * @return string
 */
function echo_render_backup_rows() {
	$backups = echo_list_backups();

	ob_start();

	if ( empty( $backups ) ) {
		echo '<tr class="echo-backup-empty"><td colspan="4">' . esc_html__( '暂无备份，点击「立即备份」创建一份。', 'echo' ) . '</td></tr>';
	}

	foreach ( $backups as $item ) {
		$label = '备份';
		if ( false !== strpos( $item['name'], '-auto-' ) ) {
			$label = '自动';
		} elseif ( false !== strpos( $item['name'], '-preimport-' ) ) {
			$label = '导入前';
		} else {
			$label = '手动';
		}
		?>
		<tr class="echo-backup-row" data-file="<?php echo esc_attr( $item['name'] ); ?>">
			<td><code><?php echo esc_html( $item['name'] ); ?></code></td>
			<td><span class="echo-backup-kind"><?php echo esc_html( $label ); ?></span></td>
			<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['time'] ) ); ?></td>
			<td class="echo-cat-actions">
				<button type="button" class="button button-small echo-backup-download"><?php esc_html_e( '下载', 'echo' ); ?></button>
				<button type="button" class="button button-small echo-backup-restore"><?php esc_html_e( '恢复', 'echo' ); ?></button>
				<button type="button" class="button button-small echo-backup-delete"><?php esc_html_e( '删除', 'echo' ); ?></button>
			</td>
		</tr>
		<?php
	}

	return ob_get_clean();
}

/* ============================================================
 * 5. 定时备份（WP-Cron）
 * ============================================================ */

/**
 * 注册 weekly 频率
 *
 * @param array $schedules 已有频率。
 * @return array
 */
function echo_cron_schedules( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display'  => __( '每周一次', 'echo' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'echo_cron_schedules' );

/**
 * 重新排程（设置变更 / 主题激活时调用）
 *
 * @return void
 */
function echo_resync_backup_schedule() {
	wp_clear_scheduled_hook( ECHO_BACKUP_HOOK );

	$opts = echo_get_backup_options();

	if ( ! $opts['enabled'] ) {
		return;
	}

	// 从下一小时开始，避免站点访问时立刻触发
	$start = time() + HOUR_IN_SECONDS;
	wp_schedule_event( $start, $opts['frequency'], ECHO_BACKUP_HOOK );
}

/**
 * 定时备份回调
 *
 * @return void
 */
function echo_do_auto_backup() {
	$opts = echo_get_backup_options();

	if ( ! $opts['enabled'] ) {
		return;
	}

	echo_write_backup( echo_build_export_data(), 'auto' );
	echo_prune_backups( $opts['keep'] );
}
add_action( ECHO_BACKUP_HOOK, 'echo_do_auto_backup' );
add_action( 'after_switch_theme', 'echo_resync_backup_schedule' );
add_action( 'switch_theme', 'echo_resync_backup_schedule_clear' );

/**
 * 切换主题时清除排程
 *
 * @return void
 */
function echo_resync_backup_schedule_clear() {
	wp_clear_scheduled_hook( ECHO_BACKUP_HOOK );
}

/* ============================================================
 * 6. 设置页区块
 * ============================================================ */

/**
 * 渲染「备份与导入」区块
 *
 * @return void
 */
function echo_render_backup_manager() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$opts  = echo_get_backup_options();
	$next  = wp_next_scheduled( ECHO_BACKUP_HOOK );
	?>
	<div id="echo-backup-manager" class="echo-backup-manager">
		<h2><?php esc_html_e( '备份与导入', 'echo' ); ?></h2>
		<p class="description"><?php esc_html_e( '导出主题配置为 JSON 文件，或从文件恢复。备份保存在 uploads/echo-backups/；Apache 已禁止直接访问，Nginx 建议额外限制该目录。', 'echo' ); ?></p>

		<div class="echo-backup-cards">
			<div class="echo-backup-card">
				<h3><?php esc_html_e( '导出配置', 'echo' ); ?></h3>
				<p class="description"><?php esc_html_e( '包含主题设置、站点标题/副标题、固定链接结构、默认分类与 Logo。', 'echo' ); ?></p>
				<button type="button" class="button button-primary echo-backup-export"><?php esc_html_e( '导出 JSON', 'echo' ); ?></button>
			</div>

			<div class="echo-backup-card">
				<h3><?php esc_html_e( '导入配置', 'echo' ); ?></h3>
				<p class="description"><?php esc_html_e( '导入前会自动备份当前配置，方便回滚。', 'echo' ); ?></p>
				<form class="echo-backup-import-form">
					<input type="file" class="echo-backup-import-file" accept="application/json,.json" required />
					<button type="submit" class="button"><?php esc_html_e( '导入并覆盖', 'echo' ); ?></button>
				</form>
			</div>
		</div>

		<div class="echo-backup-schedule">
			<h3><?php esc_html_e( '定时备份', 'echo' ); ?></h3>
			<div class="echo-backup-schedule-grid">
				<label class="echo-switch-label">
					<input type="checkbox" class="echo-switch-input echo-backup-enabled" <?php checked( $opts['enabled'], true ); ?> />
					<span class="echo-switch"><span class="echo-switch__knob"></span></span>
					<span class="echo-switch-state"><?php echo $opts['enabled'] ? esc_html__( '已开启', 'echo' ) : esc_html__( '已关闭', 'echo' ); ?></span>
				</label>
				<label>
					<span><?php esc_html_e( '频率', 'echo' ); ?></span>
					<select class="echo-backup-frequency">
						<option value="twicedaily" <?php selected( $opts['frequency'], 'twicedaily' ); ?>><?php esc_html_e( '每 12 小时', 'echo' ); ?></option>
						<option value="daily" <?php selected( $opts['frequency'], 'daily' ); ?>><?php esc_html_e( '每天', 'echo' ); ?></option>
						<option value="weekly" <?php selected( $opts['frequency'], 'weekly' ); ?>><?php esc_html_e( '每周', 'echo' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( '保留份数', 'echo' ); ?></span>
					<input type="number" class="echo-backup-keep small-text" min="1" max="50" value="<?php echo esc_attr( $opts['keep'] ); ?>" />
				</label>
				<button type="button" class="button echo-backup-save-options"><?php esc_html_e( '保存定时设置', 'echo' ); ?></button>
				<button type="button" class="button button-primary echo-backup-now"><?php esc_html_e( '立即备份', 'echo' ); ?></button>
			</div>
			<p class="description echo-backup-next">
				<?php
				if ( $opts['enabled'] && $next ) {
					echo esc_html( sprintf( __( '下次自动备份：%s', 'echo' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) ) );
				} else {
					esc_html_e( '定时备份未启用。WP-Cron 依赖站点访问触发，低频站点可能延迟。', 'echo' );
				}
				?>
			</p>
		</div>

		<h3 class="echo-backup-list-title"><?php esc_html_e( '备份记录', 'echo' ); ?></h3>
		<table class="widefat striped echo-backup-list">
			<thead>
				<tr>
					<th><?php esc_html_e( '文件', 'echo' ); ?></th>
					<th><?php esc_html_e( '类型', 'echo' ); ?></th>
					<th><?php esc_html_e( '时间', 'echo' ); ?></th>
					<th><?php esc_html_e( '操作', 'echo' ); ?></th>
				</tr>
			</thead>
			<tbody id="echo-backup-rows"><?php echo echo_render_backup_rows(); // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody>
		</table>
	</div>
	<?php
}

/* ============================================================
 * 7. AJAX
 * ============================================================ */

/**
 * AJAX：备份 / 导出 / 导入 / 恢复 / 删除 / 保存定时设置
 *
 * @return void
 */
function echo_ajax_backup_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( '无权限', 'echo' ) ), 403 );
	}

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'echo_settings_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( '安全校验失败，请刷新页面后重试', 'echo' ) ), 403 );
	}

	$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

	// 保存定时设置
	if ( 'save_options' === $op ) {
		$opts = array(
			'enabled'   => isset( $_POST['enabled'] ) && '1' === (string) $_POST['enabled'],
			'frequency' => isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'daily',
			'keep'      => isset( $_POST['keep'] ) ? max( 1, min( 50, absint( $_POST['keep'] ) ) ) : 10,
		);

		if ( ! in_array( $opts['frequency'], array( 'twicedaily', 'daily', 'weekly' ), true ) ) {
			$opts['frequency'] = 'daily';
		}

		update_option( ECHO_BACKUP_OPTION, $opts );
		echo_resync_backup_schedule();

		$next = wp_next_scheduled( ECHO_BACKUP_HOOK );
		wp_send_json_success(
			array(
				'message' => __( '定时备份设置已保存', 'echo' ),
				'next'    => ( $opts['enabled'] && $next ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) : '',
			)
		);
	}

	// 立即备份
	if ( 'backup_now' === $op ) {
		$name = echo_write_backup( echo_build_export_data(), 'manual' );

		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( '备份失败，请检查 uploads 目录权限', 'echo' ) ), 500 );
		}

		$opts = echo_get_backup_options();
		echo_prune_backups( $opts['keep'] );

		wp_send_json_success(
			array(
				'message' => __( '备份已创建', 'echo' ),
				'rows'    => echo_render_backup_rows(),
			)
		);
	}

	// 导出
	if ( 'export' === $op ) {
		wp_send_json_success(
			array(
				'message'  => __( '配置已导出', 'echo' ),
				'filename' => 'echo-settings-' . gmdate( 'Ymd-His' ) . '.json',
				'content'  => wp_json_encode( echo_build_export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			)
		);
	}

	// 下载指定备份
	if ( 'download' === $op ) {
		$name = isset( $_POST['file'] ) ? basename( wp_unslash( $_POST['file'] ) ) : '';

		if ( ! echo_is_valid_backup_name( $name ) ) {
			wp_send_json_error( array( 'message' => __( '非法文件名', 'echo' ) ), 400 );
		}

		$dir  = echo_backup_dir();
		$path = $dir ? $dir . $name : '';

		if ( ! $path || ! file_exists( $path ) ) {
			wp_send_json_error( array( 'message' => __( '备份文件不存在', 'echo' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'filename' => $name,
				'content'  => file_get_contents( $path ), // phpcs:ignore WordPress.WP.AlternativeFunctions
			)
		);
	}

	// 从备份恢复
	if ( 'restore' === $op ) {
		$name = isset( $_POST['file'] ) ? basename( wp_unslash( $_POST['file'] ) ) : '';

		if ( ! echo_is_valid_backup_name( $name ) ) {
			wp_send_json_error( array( 'message' => __( '非法文件名', 'echo' ) ), 400 );
		}

		$dir  = echo_backup_dir();
		$path = $dir ? $dir . $name : '';

		if ( ! $path || ! file_exists( $path ) ) {
			wp_send_json_error( array( 'message' => __( '备份文件不存在', 'echo' ) ), 404 );
		}

		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( '备份文件已损坏', 'echo' ) ), 400 );
		}

		$result = echo_apply_export_data( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => __( '配置已恢复，部分设置可能需要刷新页面', 'echo' ),
				'rows'    => echo_render_backup_rows(),
			)
		);
	}

	// 删除备份
	if ( 'delete' === $op ) {
		$name = isset( $_POST['file'] ) ? basename( wp_unslash( $_POST['file'] ) ) : '';

		if ( ! echo_is_valid_backup_name( $name ) ) {
			wp_send_json_error( array( 'message' => __( '非法文件名', 'echo' ) ), 400 );
		}

		$dir  = echo_backup_dir();
		$path = $dir ? $dir . $name : '';

		if ( ! $path || ! file_exists( $path ) ) {
			wp_send_json_error( array( 'message' => __( '备份文件不存在', 'echo' ) ), 404 );
		}

		if ( ! unlink( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			wp_send_json_error( array( 'message' => __( '删除失败', 'echo' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message' => __( '备份已删除', 'echo' ),
				'rows'    => echo_render_backup_rows(),
			)
		);
	}

	// 导入上传的配置文件
	if ( 'import' === $op ) {
		if ( empty( $_FILES['import_file'] ) || ! isset( $_FILES['import_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( '请选择配置文件', 'echo' ) ), 400 );
		}

		$file = $_FILES['import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_send_json_error( array( 'message' => __( '文件上传失败', 'echo' ) ), 400 );
		}

		if ( (int) $file['size'] > ECHO_BACKUP_MAX_BYTES ) {
			wp_send_json_error( array( 'message' => __( '配置文件过大（上限 1MB）', 'echo' ) ), 400 );
		}

		$raw  = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || ! isset( $data['type'] ) || ECHO_BACKUP_TYPE !== $data['type'] ) {
			wp_send_json_error( array( 'message' => __( '这不是有效的 Echo 配置文件', 'echo' ) ), 400 );
		}

		$result = echo_apply_export_data( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => __( '配置已导入，部分设置可能需要刷新页面', 'echo' ),
				'rows'    => echo_render_backup_rows(),
			)
		);
	}

	wp_send_json_error( array( 'message' => __( '未知操作', 'echo' ) ), 400 );
}
add_action( 'wp_ajax_echo_backup_action', 'echo_ajax_backup_action' );
