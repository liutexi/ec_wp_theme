<?php
/**
 * Echo 后台「文章」功能增强
 *
 * - 文章列表：封面 / ID / 字数 / 链接列
 * - 编辑器：副标题、SEO 描述、隐藏侧边栏
 * - 前台应用：副标题、meta description、单篇侧边栏开关
 * - Echo 设置页：快捷文章管理（新建 / 删除 / 批量改分类）
 * - 后台 UI 美化（克制，遵循 WP 原生视觉）
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 0. 后台样式
 * ============================================================ */

/**
 * 在文章相关页面加载后台增强样式
 *
 * @param string $hook 当前页面 hook。
 * @return void
 */
function echo_admin_posts_enqueue( $hook ) {
	$targets = array( 'edit.php', 'post.php', 'post-new.php', 'toplevel_page_echo-settings' );

	if ( ! in_array( $hook, $targets, true ) ) {
		return;
	}

	// 列表页：增强关闭时不加载，让 WP 原生样式完全接管
	if ( 'edit.php' === $hook && function_exists( 'echo_get_setting' ) && ! echo_get_setting( 'admin_post_columns', true ) ) {
		return;
	}

	wp_enqueue_style(
		'echo-admin-posts',
		get_template_directory_uri() . '/assets/css/admin-posts.css',
		array(),
		ECHO_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'echo_admin_posts_enqueue' );

/* ============================================================
 * 1. 文章列表自定义列
 * ============================================================ */

/**
 * 注册列表列
 *
 * @param array $columns 已有列。
 * @return array
 */
function echo_posts_columns( $columns ) {
	// 总开关：关闭时恢复 WordPress 原生列
	if ( function_exists( 'echo_get_setting' ) && ! echo_get_setting( 'admin_post_columns', true ) ) {
		return $columns;
	}

	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'cb' === $key ) {
			$new['echo_thumb'] = __( '封面', 'echo' );
		}
	}

	$new['echo_id']        = __( 'ID', 'echo' );
	$new['echo_words']     = __( '字数', 'echo' );
	$new['echo_permalink'] = __( '链接', 'echo' );

	return $new;
}
add_filter( 'manage_posts_columns', 'echo_posts_columns' );

/**
 * 输出自定义列内容
 *
 * @param string $column  列名。
 * @param int    $post_id 文章 ID。
 * @return void
 */
function echo_posts_custom_column( $column, $post_id ) {
	switch ( $column ) {
		case 'echo_thumb':
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail(
					$post_id,
					array( 64, 44 ),
					array( 'class' => 'echo-admin-thumb' )
				);
			} else {
				echo '<span class="echo-admin-thumb echo-admin-thumb--empty" aria-label="' . esc_attr__( '无封面', 'echo' ) . '">&mdash;</span>';
			}
			break;

		case 'echo_id':
			echo '<span class="echo-admin-id">' . esc_html( $post_id ) . '</span>';
			break;

		case 'echo_words':
			$content = get_post_field( 'post_content', $post_id );
			$plain   = wp_strip_all_tags( $content );
			$plain   = preg_replace( '/\s+/u', '', $plain );
			$count   = function_exists( 'mb_strlen' ) ? mb_strlen( $plain, 'UTF-8' ) : strlen( $plain );
			echo '<span class="echo-admin-words">' . esc_html( number_format_i18n( $count ) ) . '</span>';
			break;

		case 'echo_permalink':
			$slug = get_post_field( 'post_name', $post_id );
			$url  = get_permalink( $post_id );

			if ( '' === $slug ) {
				$display = '#' . $post_id;
			} else {
				// 中文 slug 在数据库里是百分号编码，显示时还原成可读文字
				$display = urldecode( $slug );
			}

			printf(
				'<a class="echo-admin-permalink" href="%1$s" target="_blank" rel="noopener" title="%2$s">%3$s</a>',
				esc_url( $url ),
				esc_attr( $url ),
				esc_html( $display )
			);
			break;
	}
}
add_action( 'manage_posts_custom_column', 'echo_posts_custom_column', 10, 2 );

/**
 * ID 列可排序
 *
 * @param array $columns 可排序列。
 * @return array
 */
function echo_posts_sortable_columns( $columns ) {
	$columns['echo_id'] = 'ID';
	return $columns;
}
add_filter( 'manage_edit-post_sortable_columns', 'echo_posts_sortable_columns' );

/* ============================================================
 * 2. 编辑器设置框
 * ============================================================ */

/**
 * 注册文章选项 meta box
 *
 * @return void
 */
function echo_add_post_meta_box() {
	add_meta_box(
		'echo_post_options',
		__( 'Echo 文章选项', 'echo' ),
		'echo_post_meta_box_html',
		'post',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'echo_add_post_meta_box' );

/**
 * 输出 meta box 表单
 *
 * @param WP_Post $post 当前文章。
 * @return void
 */
function echo_post_meta_box_html( $post ) {
	wp_nonce_field( 'echo_post_options', 'echo_post_options_nonce' );

	$subtitle     = get_post_meta( $post->ID, '_echo_subtitle', true );
	$seo_desc     = get_post_meta( $post->ID, '_echo_seo_description', true );
	$hide_sidebar = (bool) get_post_meta( $post->ID, '_echo_hide_sidebar', true );
	?>
	<div class="echo-post-meta-box">
		<p>
			<label for="echo_subtitle"><strong><?php esc_html_e( '副标题', 'echo' ); ?></strong></label>
			<input type="text" id="echo_subtitle" name="echo_subtitle" class="widefat"
				value="<?php echo esc_attr( $subtitle ); ?>"
				placeholder="<?php esc_attr_e( '显示在文章标题下方', 'echo' ); ?>" />
			<span class="description"><?php esc_html_e( '可选，留空则不显示。', 'echo' ); ?></span>
		</p>

		<p>
			<label for="echo_seo_description"><strong><?php esc_html_e( 'SEO 描述', 'echo' ); ?></strong></label>
			<textarea id="echo_seo_description" name="echo_seo_description" class="widefat" rows="3"
				placeholder="<?php esc_attr_e( '用于 meta description', 'echo' ); ?>"><?php echo esc_textarea( $seo_desc ); ?></textarea>
			<span class="description"><?php esc_html_e( '留空则自动使用文章摘要。', 'echo' ); ?></span>
		</p>

		<p>
			<label class="echo-post-meta-box__check">
				<input type="checkbox" name="echo_hide_sidebar" value="1" <?php checked( $hide_sidebar, true ); ?> />
				<strong><?php esc_html_e( '隐藏本文侧边栏', 'echo' ); ?></strong>
			</label>
			<span class="description"><?php esc_html_e( '仅对这篇文章生效，正文将占满宽度。', 'echo' ); ?></span>
		</p>
	</div>
	<?php
}

/**
 * 保存 meta box 数据
 *
 * @param int $post_id 文章 ID。
 * @return void
 */
function echo_save_post_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['echo_post_options_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['echo_post_options_nonce'] ) ), 'echo_post_options' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$subtitle = isset( $_POST['echo_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['echo_subtitle'] ) ) : '';
	$seo_desc = isset( $_POST['echo_seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['echo_seo_description'] ) ) : '';
	$hide     = isset( $_POST['echo_hide_sidebar'] ) && '1' === (string) $_POST['echo_hide_sidebar'];

	update_post_meta( $post_id, '_echo_subtitle', $subtitle );
	update_post_meta( $post_id, '_echo_seo_description', $seo_desc );
	update_post_meta( $post_id, '_echo_hide_sidebar', $hide ? 1 : 0 );
}
add_action( 'save_post', 'echo_save_post_meta' );

/* ============================================================
 * 3. 前台应用
 * ============================================================ */

/**
 * 单篇文章输出 meta description
 *
 * @return void
 */
function echo_seo_description() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_obj = get_queried_object();
	if ( ! $post_obj || ! isset( $post_obj->ID ) ) {
		return;
	}

	$desc = get_post_meta( $post_obj->ID, '_echo_seo_description', true );

	if ( '' === $desc ) {
		if ( ! empty( $post_obj->post_excerpt ) ) {
			$desc = $post_obj->post_excerpt;
		} else {
			$desc = wp_strip_all_tags( $post_obj->post_content );
		}
	}

	$desc = trim( preg_replace( '/\s+/u', ' ', $desc ) );

	if ( '' !== $desc ) {
		$short = function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, 160, 'UTF-8' ) : substr( $desc, 0, 160 );
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $short ) );
	}
}
add_action( 'wp_head', 'echo_seo_description', 2 );

/**
 * 单篇隐藏侧边栏：为 body 添加 class
 *
 * @param array $classes 已有 class。
 * @return array
 */
function echo_no_sidebar_body_class( $classes ) {
	if ( ! is_singular( 'post' ) ) {
		return $classes;
	}

	$post_obj = get_queried_object();

	if ( $post_obj && isset( $post_obj->ID ) && get_post_meta( $post_obj->ID, '_echo_hide_sidebar', true ) ) {
		$classes[] = 'echo-no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'echo_no_sidebar_body_class' );

/* ============================================================
 * 4. Echo 设置页：快捷文章管理
 * ============================================================ */

/**
 * 渲染快捷文章管理区块（独立于 options.php 主表单）
 *
 * @return void
 */
function echo_render_post_manager() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	?>
	<div id="echo-post-manager" class="echo-post-manager">
		<h2><?php esc_html_e( '文章管理', 'echo' ); ?></h2>
		<p class="description"><?php esc_html_e( '快速新建文章、删除文章，或批量修改分类。等同于后台「文章」功能，操作即时生效。', 'echo' ); ?></p>

		<form class="echo-post-add-form">
			<div class="echo-post-add-grid">
				<label>
					<span><?php esc_html_e( '标题', 'echo' ); ?></span>
					<input type="text" class="echo-post-add-title regular-text" required placeholder="<?php esc_attr_e( '新文章标题', 'echo' ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( '分类', 'echo' ); ?></span>
					<select class="echo-post-add-cat"><?php echo echo_render_parent_options( 0, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
				</label>
				<label>
					<span><?php esc_html_e( '状态', 'echo' ); ?></span>
					<select class="echo-post-add-status">
						<option value="draft"><?php esc_html_e( '草稿', 'echo' ); ?></option>
						<option value="publish"><?php esc_html_e( '立即发布', 'echo' ); ?></option>
					</select>
				</label>
			</div>
			<label class="echo-post-add-content-label">
				<span><?php esc_html_e( '正文（可选）', 'echo' ); ?></span>
				<textarea class="echo-post-add-content large-text" rows="2" placeholder="<?php esc_attr_e( '留空则创建空文章，稍后在编辑器里补', 'echo' ); ?>"></textarea>
			</label>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( '新建文章', 'echo' ); ?></button></p>
		</form>

		<div class="echo-post-bulk">
			<label>
				<span><?php esc_html_e( '批量设为分类：', 'echo' ); ?></span>
				<select class="echo-post-bulk-cat"><?php echo echo_render_parent_options( 0, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
			</label>
			<button type="button" class="button echo-post-bulk-apply"><?php esc_html_e( '应用到选中文章', 'echo' ); ?></button>
		</div>

		<table class="widefat striped echo-post-list">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" class="echo-post-check-all" /></td>
					<th><?php esc_html_e( '标题', 'echo' ); ?></th>
					<th><?php esc_html_e( '状态', 'echo' ); ?></th>
					<th><?php esc_html_e( '分类', 'echo' ); ?></th>
					<th><?php esc_html_e( '日期', 'echo' ); ?></th>
					<th><?php esc_html_e( '操作', 'echo' ); ?></th>
				</tr>
			</thead>
			<tbody id="echo-post-rows"><?php echo echo_render_post_rows(); // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody>
		</table>
		<p class="description echo-post-note">
			<?php esc_html_e( '仅显示最近 20 篇文章。更多文章请到后台「文章」管理。', 'echo' ); ?>
		</p>
	</div>
	<?php
}

/**
 * 生成文章表格行
 *
 * @return string
 */
function echo_render_post_rows() {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	ob_start();

	if ( empty( $posts ) ) {
		echo '<tr class="echo-post-empty"><td colspan="6">' . esc_html__( '暂无文章，请在上方新建。', 'echo' ) . '</td></tr>';
	}

	$status_labels = array(
		'publish' => __( '已发布', 'echo' ),
		'draft'   => __( '草稿', 'echo' ),
		'pending' => __( '待审核', 'echo' ),
		'private' => __( '私密', 'echo' ),
	);

	foreach ( $posts as $p ) {
		$status = get_post_status( $p->ID );
		$cats   = get_the_category( $p->ID );
		$names  = wp_list_pluck( $cats, 'name' );
		?>
		<tr class="echo-post-row" data-id="<?php echo esc_attr( $p->ID ); ?>">
			<th class="check-column"><input type="checkbox" class="echo-post-check" value="<?php echo esc_attr( $p->ID ); ?>" /></th>
			<td class="echo-post-title">
				<a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( $p->post_title ? $p->post_title : __( '（无标题）', 'echo' ) ); ?></a>
			</td>
			<td><span class="echo-post-status echo-post-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status ); ?></span></td>
			<td><?php echo esc_html( ! empty( $names ) ? implode( '、', $names ) : __( '—', 'echo' ) ); ?></td>
			<td><?php echo esc_html( get_the_time( get_option( 'date_format' ), $p->ID ) ); ?></td>
			<td class="echo-cat-actions">
				<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php esc_html_e( '编辑', 'echo' ); ?></a>
				<button type="button" class="button button-small echo-post-del"><?php esc_html_e( '删除', 'echo' ); ?></button>
			</td>
		</tr>
		<?php
	}

	return ob_get_clean();
}

/**
 * AJAX：新建 / 删除 / 批量改分类
 *
 * @return void
 */
function echo_ajax_manage_post() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( '无权限', 'echo' ) ), 403 );
	}

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'echo_settings_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( '安全校验失败，请刷新页面后重试', 'echo' ) ), 403 );
	}

	$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';

	$respond = function ( $message ) {
		wp_send_json_success(
			array(
				'message' => $message,
				'rows'    => echo_render_post_rows(),
			)
		);
	};

	// 删除
	if ( 'delete' === $op ) {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id || ! current_user_can( 'delete_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( '无法删除该文章', 'echo' ) ), 403 );
		}
		if ( ! wp_delete_post( $id, true ) ) {
			wp_send_json_error( array( 'message' => __( '删除失败', 'echo' ) ), 400 );
		}
		$respond( __( '文章已删除', 'echo' ) );
	}

	// 批量改分类
	if ( 'bulk_category' === $op ) {
		$ids = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) : array();
		$cat = isset( $_POST['cat'] ) ? absint( $_POST['cat'] ) : 0;

		if ( empty( $ids ) || ! $cat || ! term_exists( $cat, 'category' ) ) {
			wp_send_json_error( array( 'message' => __( '请选择文章和目标分类', 'echo' ) ), 400 );
		}

		foreach ( $ids as $id ) {
			if ( current_user_can( 'edit_post', $id ) ) {
				wp_set_post_categories( $id, array( $cat ), false );
			}
		}

		$respond( sprintf( __( '已更新 %d 篇文章的分类', 'echo' ), count( $ids ) ) );
	}

	// 新建
	if ( 'create' === $op ) {
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$status  = isset( $_POST['status'] ) && 'publish' === $_POST['status'] ? 'publish' : 'draft';
		$cat     = isset( $_POST['cat'] ) ? absint( $_POST['cat'] ) : 0;

		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( '文章标题不能为空', 'echo' ) ), 400 );
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
				'post_type'    => 'post',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ), 400 );
		}

		if ( $cat ) {
			wp_set_post_categories( $new_id, array( $cat ), false );
		}

		$respond( __( '文章已创建', 'echo' ) );
	}

	wp_send_json_error( array( 'message' => __( '未知操作', 'echo' ) ), 400 );
}
add_action( 'wp_ajax_echo_manage_post', 'echo_ajax_manage_post' );
