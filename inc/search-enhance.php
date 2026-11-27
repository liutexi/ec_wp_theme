<?php
/**
 * 搜索增强：搜索历史（localStorage）、热词、文章/用户多 Tab、排序切换
 *
 * 不引入 Meilisearch 等重型依赖，基于 WP 原生搜索。后台可开关。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 默认设置。
 *
 * @return array
 */
function echo_se_defaults() {
	return array(
		'se_enable'     => true,
		'se_history'    => true,  // 搜索历史
		'se_hot'        => true,  // 热词（按搜索次数 transient 统计）
		'se_user_tab'   => true,  // 用户 Tab
		'se_sort'       => true,  // 排序切换
	);
}

/**
 * 读取设置。
 *
 * @param string $key     键。
 * @param mixed  $default 默认。
 * @return mixed
 */
function echo_se_config( $key, $default = null ) {
	$all = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_se_defaults();
	return isset( $all[ $key ] ) ? $all[ $key ] : $default;
}

/**
 * 是否启用。
 *
 * @return bool
 */
function echo_se_is_on() {
	return (bool) echo_se_config( 'se_enable', true );
}

/**
 * 清洗设置。
 *
 * @param array $input 原始。
 * @param array $out   输出。
 * @return void
 */
function echo_sanitize_se_settings( $input, &$out ) {
	foreach ( array( 'se_enable', 'se_history', 'se_hot', 'se_user_tab', 'se_sort' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) && '1' === (string) $input[ $key ];
	}
}

/**
 * 记录一次搜索词（用于热词统计）。
 *
 * @param string $term 搜索词。
 * @return void
 */
function echo_se_record_term( $term ) {
	$term = trim( wp_strip_all_tags( $term ) );
	if ( '' === $term || mb_strlen( $term ) > 40 ) {
		return;
	}
	$all = get_transient( 'echo_se_hot_terms' );
	if ( ! is_array( $all ) ) {
		$all = array();
	}
	$key = mb_strtolower( $term );
	$all[ $key ] = isset( $all[ $key ] ) ? array( 'term' => $term, 'count' => $all[ $key ]['count'] + 1 ) : array( 'term' => $term, 'count' => 1 );
	set_transient( 'echo_se_hot_terms', $all, WEEK_IN_SECONDS );
}

/**
 * 获取热词列表。
 *
 * @param int $limit 数量。
 * @return array
 */
function echo_se_hot_terms( $limit = 8 ) {
	$all = get_transient( 'echo_se_hot_terms' );
	if ( ! is_array( $all ) || empty( $all ) ) {
		return array();
	}
	uasort( $all, function ( $a, $b ) { return $b['count'] - $a['count']; } );
	$terms = array_values( array_map( function ( $item ) { return $item['term']; }, $all ) );
	return array_slice( $terms, 0, $limit );
}

/**
 * 记录搜索词（仅在搜索结果页、有关键词时）。
 *
 * @return void
 */
function echo_se_maybe_record() {
	if ( ! is_search() || ! echo_se_is_on() || ! echo_se_config( 'se_hot', true ) ) {
		return;
	}
	$term = get_search_query();
	if ( $term ) {
		echo_se_record_term( $term );
	}
}
add_action( 'template_redirect', 'echo_se_maybe_record' );

/**
 * 排序切换：映射 URL 参数到 WP_Query orderby。
 *
 * @param WP_Query $query 查询对象。
 * @return void
 */
function echo_se_sort_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	if ( ! echo_se_is_on() || ! echo_se_config( 'se_sort', true ) ) {
		return;
	}
	$order = isset( $_GET['seorder'] ) ? sanitize_key( wp_unslash( $_GET['seorder'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'new' === $order ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	} elseif ( 'old' === $order ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'ASC' );
	} elseif ( 'title' === $order ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'echo_se_sort_query' );

/**
 * 渲染搜索增强面板（历史/热词/Tab/排序），供 search.php 调用。
 *
 * @return void
 */
function echo_se_render_panel() {
	if ( ! echo_se_is_on() ) {
		return;
	}
	$term    = get_search_query();
	$hot     = echo_se_config( 'se_hot', true ) ? echo_se_hot_terms( 8 ) : array();
	$history = echo_se_config( 'se_history', true );
	$user_tab = echo_se_config( 'se_user_tab', true );
	$sort    = echo_se_config( 'se_sort', true );
	$base    = home_url( '/' );
	$order   = isset( $_GET['seorder'] ) ? sanitize_key( wp_unslash( $_GET['seorder'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="echo-search-enhance" data-history="<?php echo $history ? '1' : '0'; ?>">
		<?php if ( $user_tab ) : ?>
			<div class="echo-se-tabs" role="tablist">
				<a class="echo-se-tab is-active" href="<?php echo esc_url( add_query_arg( 'type', 'post' ) ); ?>" data-type="post" role="tab"><?php esc_html_e( '文章', 'echo' ); ?></a>
				<a class="echo-se-tab" href="<?php echo esc_url( add_query_arg( 'type', 'user' ) ); ?>" data-type="user" role="tab"><?php esc_html_e( '用户', 'echo' ); ?></a>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $hot ) || $history ) : ?>
			<div class="echo-se-tools">
				<?php if ( $history ) : ?>
					<div class="echo-se-block echo-se-history" hidden>
						<span class="echo-se-block__title"><?php esc_html_e( '搜索历史', 'echo' ); ?></span>
						<div class="echo-se-tags" data-slot="history"></div>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $hot ) ) : ?>
					<div class="echo-se-block">
						<span class="echo-se-block__title"><?php esc_html_e( '热门搜索', 'echo' ); ?></span>
						<div class="echo-se-tags">
							<?php foreach ( $hot as $t ) : ?>
								<a class="echo-se-tag" href="<?php echo esc_url( add_query_arg( 's', $t, $base ) ); ?>"><?php echo esc_html( $t ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $sort ) : ?>
			<div class="echo-se-sort">
				<span class="echo-se-sort__label"><?php esc_html_e( '排序', 'echo' ); ?></span>
				<?php
				$orders = array(
					''      => __( '默认', 'echo' ),
					'new'   => __( '最新', 'echo' ),
					'old'   => __( '最早', 'echo' ),
					'title' => __( '标题', 'echo' ),
				);
				foreach ( $orders as $key => $label ) :
					$url = add_query_arg( 'seorder', $key );
					if ( '' === $key ) {
						$url = remove_query_arg( 'seorder' );
					}
					?>
					<a class="echo-se-sort__item<?php echo $order === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * 搜索结果页用户 Tab 内容：在文章列表后追加用户结果。
 *
 * @return void
 */
function echo_se_render_users() {
	if ( ! echo_se_is_on() || ! echo_se_config( 'se_user_tab', true ) ) {
		return;
	}
	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'user' !== $type ) {
		return;
	}
	$term = get_search_query();
	if ( '' === $term ) {
		return;
	}
	$users = get_users(
		array(
			'search'         => '*' . esc_attr( $term ) . '*',
			'number'         => 20,
			'fields'         => 'all',
			'search_columns' => array( 'user_login', 'display_name', 'user_email' ),
		)
	);
	?>
	<div class="echo-se-users">
		<h3 class="echo-se-users__title"><?php esc_html_e( '相关用户', 'echo' ); ?></h3>
		<?php if ( empty( $users ) ) : ?>
			<p class="echo-se-users__empty"><?php esc_html_e( '没有找到匹配的用户。', 'echo' ); ?></p>
		<?php else : ?>
			<div class="echo-se-users__grid">
				<?php foreach ( $users as $u ) : ?>
					<a class="echo-se-user" href="<?php echo esc_url( function_exists( 'echo_user_url' ) ? echo_user_url( $u->ID ) : get_author_posts_url( $u->ID ) ); ?>">
						<?php echo get_avatar( $u->ID, 48 ); ?>
						<span class="echo-se-user__name"><?php echo esc_html( $u->display_name ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
