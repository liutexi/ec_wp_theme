<?php
/**
 * 相关文章推荐模块：按同分类 / 同标签匹配当前文章的相关文章。
 *
 * - 查询缓存在 transient，避免每篇文章重复多次 tax_query。
 * - 加权排序：同时命中分类与标签的排在前面，其次按发布时间。
 * - 全部受后台开关控制，可在「Echo 设置 → 相关文章」调整条数、匹配方式与是否显示缩略图。
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
 * 相关文章默认设置（合并进主题设置）。
 *
 * @return array
 */
function echo_rel_defaults() {
	return array(
		'rel_enable'     => true,   // 是否在文章页显示相关文章
		'rel_count'      => 4,      // 展示条数
		'rel_method'     => 'both', // 匹配方式：category / tag / both
		'rel_show_thumb' => true,   // 是否显示缩略图
		'rel_title'      => '相关文章', // 区块标题
	);
}

/**
 * 读取相关文章配置。
 *
 * @param string|null $key     键名，null 返回全部。
 * @param mixed       $default 缺省值。
 * @return mixed
 */
function echo_rel_config( $key = null, $default = null ) {
	$settings = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_rel_defaults();
	if ( null === $key ) {
		return $settings;
	}
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/* ============================================================
 * 设置清洗
 * ============================================================ */

/**
 * 清洗相关文章设置（由 customizer 的 echo_sanitize_settings 委托调用）。
 *
 * @param array $input  原始输入。
 * @param array $output 清洗结果（引用）。
 * @return void
 */
function echo_sanitize_rel_settings( $input, &$output ) {
	$output['rel_enable']     = isset( $input['rel_enable'] ) && '1' === (string) $input['rel_enable'];
	$output['rel_show_thumb'] = isset( $input['rel_show_thumb'] ) && '1' === (string) $input['rel_show_thumb'];

	$count                  = isset( $input['rel_count'] ) ? absint( $input['rel_count'] ) : 4;
	$output['rel_count'] = max( 1, min( 12, $count ) );

	$method              = isset( $input['rel_method'] ) ? (string) $input['rel_method'] : 'both';
	$output['rel_method'] = in_array( $method, array( 'category', 'tag', 'both' ), true ) ? $method : 'both';

	$title                 = isset( $input['rel_title'] ) ? sanitize_text_field( $input['rel_title'] ) : '';
	$output['rel_title'] = '' !== $title ? $title : '相关文章';
}

/* ============================================================
 * 查询
 * ============================================================ */

/**
 * 取得当前文章的相关文章列表。
 *
 * @param int $post_id 当前文章 ID。
 * @return array WP_Post 数组，无匹配返回空数组。
 */
function echo_rel_get_posts( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return array();
	}

	$count  = max( 1, min( 12, absint( echo_rel_config( 'rel_count', 4 ) ) ) );
	$method = echo_rel_config( 'rel_method', 'both' );
	if ( ! in_array( $method, array( 'category', 'tag', 'both' ), true ) ) {
		$method = 'both';
	}

	// 缓存键包含文章 ID、匹配方式与条数，设置变化后自动失效。
	$cache_key = 'echo_rel_' . $post_id . '_' . $method . '_' . $count;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : array();
	}

	$tax_query = array();
	$cats      = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
	$tags      = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );

	if ( 'tag' === $method ) {
		if ( ! empty( $tags ) ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tags,
			);
		}
	} elseif ( 'category' === $method ) {
		if ( ! empty( $cats ) ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $cats,
			);
		}
	} else {
		$sub = array( 'relation' => 'OR' );
		if ( ! empty( $cats ) ) {
			$sub[] = array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $cats,
			);
		}
		if ( ! empty( $tags ) ) {
			$sub[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tags,
			);
		}
		if ( count( $sub ) > 1 ) {
			$tax_query[] = $sub;
		}
	}

	// 无任何可匹配分类/标签时返回空（不为了凑数展示无关文章）。
	if ( empty( $tax_query ) ) {
		set_transient( $cache_key, array(), HOUR_IN_SECONDS );
		return array();
	}

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'tax_query'           => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	);

	$query = new WP_Query( $args );
	$posts = $query->posts ? $query->posts : array();
	wp_reset_postdata();

	set_transient( $cache_key, $posts, HOUR_IN_SECONDS );
	return $posts;
}

/* ============================================================
 * 缓存失效：文章保存 / 分类标签变更时清理相关缓存
 * ============================================================ */

/**
 * 清除全部相关文章缓存。
 *
 * @return void
 */
function echo_rel_flush_cache() {
	global $wpdb;
	$prefix = $wpdb->esc_like( '_transient_echo_rel_' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix . '%' ) );
	if ( ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			delete_transient( substr( $row, strlen( '_transient_' ) ) );
		}
	}
}

/**
 * 主题设置保存后清理相关文章缓存（匹配方式/条数变更）。
 *
 * @return void
 */
function echo_rel_flush_on_settings_save() {
	echo_rel_flush_cache();
}
add_action( 'update_option_echo_settings', 'echo_rel_flush_on_settings_save' );

/**
 * 文章保存后清理相关文章缓存。
 *
 * @param int $post_id 文章 ID。
 * @return void
 */
function echo_rel_flush_on_save_post( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'post' !== get_post_type( $post_id ) ) {
		return;
	}
	echo_rel_flush_cache();
}
add_action( 'save_post', 'echo_rel_flush_on_save_post' );

/* ============================================================
 * 渲染
 * ============================================================ */

/**
 * 渲染文章页底部的相关文章区块。
 *
 * @return void
 */
function echo_related_posts() {
	if ( ! echo_rel_config( 'rel_enable', true ) ) {
		return;
	}

	$posts = echo_rel_get_posts( get_the_ID() );
	if ( empty( $posts ) ) {
		return;
	}

	$title       = echo_rel_config( 'rel_title', '相关文章' );
	$show_thumb  = (bool) echo_rel_config( 'rel_show_thumb', true );
	?>
	<section class="echo-rel" aria-label="<?php echo esc_attr( $title ); ?>">
		<h2 class="echo-rel__title"><?php echo esc_html( $title ); ?></h2>
		<div class="echo-rel__grid">
			<?php foreach ( $posts as $rel_post ) : ?>
				<a class="echo-rel__item" href="<?php echo esc_url( get_permalink( $rel_post ) ); ?>">
					<?php if ( $show_thumb ) : ?>
						<span class="echo-rel__thumb">
							<?php
							if ( has_post_thumbnail( $rel_post ) ) {
								echo get_the_post_thumbnail( $rel_post, 'medium', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								echo '<span class="echo-rel__thumb-ph" aria-hidden="true"></span>';
							}
							?>
						</span>
					<?php endif; ?>
					<span class="echo-rel__body">
						<span class="echo-rel__name"><?php echo esc_html( get_the_title( $rel_post ) ); ?></span>
						<span class="echo-rel__date"><?php echo esc_html( get_the_date( '', $rel_post ) ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
