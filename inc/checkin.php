<?php
/**
 * 用户签到打卡：签到 + 连续天数记录（不接积分商城）
 *
 * 数据存 user meta：echo_checkin_days（累计天数）、echo_checkin_streak（连续天数）、
 * echo_checkin_last（最后签到日期 Y-m-d）。AJAX + nonce。后台可开关。
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
function echo_ci_defaults() {
	return array(
		'ci_enable' => false, // 默认关闭，需后台开启
	);
}

/**
 * 读取设置。
 *
 * @param string $key     键。
 * @param mixed  $default 默认。
 * @return mixed
 */
function echo_ci_config( $key, $default = null ) {
	$all = function_exists( 'echo_get_settings' ) ? echo_get_settings() : echo_ci_defaults();
	return isset( $all[ $key ] ) ? $all[ $key ] : $default;
}

/**
 * 是否启用。
 *
 * @return bool
 */
function echo_ci_is_on() {
	return (bool) echo_ci_config( 'ci_enable', false );
}

/**
 * 清洗设置。
 *
 * @param array $input 原始。
 * @param array $out   输出。
 * @return void
 */
function echo_sanitize_ci_settings( $input, &$out ) {
	$out['ci_enable'] = isset( $input['ci_enable'] ) && '1' === (string) $input['ci_enable'];
}

/**
 * 获取用户签到数据。
 *
 * @param int $user_id 用户 ID。
 * @return array total/streak/last/signed_today
 */
function echo_ci_data( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$total  = max( 0, (int) get_user_meta( $user_id, 'echo_checkin_days', true ) );
	$streak = max( 0, (int) get_user_meta( $user_id, 'echo_checkin_streak', true ) );
	$last   = (string) get_user_meta( $user_id, 'echo_checkin_last', true );
	$today  = current_time( 'Y-m-d' );
	return array(
		'total'        => $total,
		'streak'       => $streak,
		'last'         => $last,
		'signed_today' => ( $last === $today ),
	);
}

/**
 * 执行签到。
 *
 * @param int $user_id 用户 ID。
 * @return array|WP_Error
 */
function echo_ci_do( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return new WP_Error( 'not_logged_in', __( '请先登录后再签到。', 'echo' ) );
	}
	$data  = echo_ci_data( $user_id );
	if ( $data['signed_today'] ) {
		return new WP_Error( 'already_signed', __( '今天已经签到过了。', 'echo' ) );
	}

	$today    = current_time( 'Y-m-d' );
	$yesterday = date( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -1 day' ) );
	$streak   = ( $data['last'] === $yesterday ) ? $data['streak'] + 1 : 1;

	update_user_meta( $user_id, 'echo_checkin_days', $data['total'] + 1 );
	update_user_meta( $user_id, 'echo_checkin_streak', $streak );
	update_user_meta( $user_id, 'echo_checkin_last', $today );

	return echo_ci_data( $user_id );
}

/**
 * AJAX：签到。
 *
 * @return void
 */
function echo_ci_ajax_do() {
	check_ajax_referer( 'echo_ci', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( '请先登录后再签到。', 'echo' ) ) );
	}
	$result = echo_ci_do( get_current_user_id() );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_echo_ci_do', 'echo_ci_ajax_do' );

/**
 * 渲染签到卡片。
 *
 * @param int $user_id 目标用户 ID（0 为当前用户）。
 * @return void
 */
function echo_ci_render( $user_id = 0 ) {
	if ( ! echo_ci_is_on() ) {
		return;
	}
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	$data       = echo_ci_data( $user_id );
	$is_self    = ( $user_id === get_current_user_id() );
	$can_sign   = $is_self && ! $data['signed_today'];
	?>
	<div class="echo-checkin" data-uid="<?php echo esc_attr( $user_id ); ?>">
		<div class="echo-checkin__head">
			<svg class="echo-checkin__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
			<span class="echo-checkin__title"><?php esc_html_e( '每日签到', 'echo' ); ?></span>
		</div>
		<div class="echo-checkin__stats">
			<div class="echo-checkin__stat"><strong><?php echo esc_html( $data['total'] ); ?></strong><span><?php esc_html_e( '累计天数', 'echo' ); ?></span></div>
			<div class="echo-checkin__stat"><strong><?php echo esc_html( $data['streak'] ); ?></strong><span><?php esc_html_e( '连续天数', 'echo' ); ?></span></div>
		</div>
		<?php if ( $is_self ) : ?>
			<button type="button" class="echo-checkin__btn<?php echo $can_sign ? '' : ' is-done'; ?>" <?php echo $can_sign ? '' : 'disabled'; ?>>
				<?php echo $can_sign ? esc_html__( '签到', 'echo' ) : esc_html__( '今日已签到', 'echo' ); ?>
			</button>
		<?php else : ?>
			<p class="echo-checkin__tip"><?php echo $data['signed_today'] ? esc_html__( '今日已签到', 'echo' ) : esc_html__( '尚未签到', 'echo' ); ?></p>
		<?php endif; ?>
		<p class="echo-checkin__msg" hidden></p>
	</div>
	<?php
}

/**
 * 短代码：[echo_checkin]。
 *
 * @return string
 */
function echo_ci_shortcode() {
	if ( ! echo_ci_is_on() ) {
		return '';
	}
	ob_start();
	echo_ci_render( get_current_user_id() );
	return ob_get_clean();
}
add_shortcode( 'echo_checkin', 'echo_ci_shortcode' );
