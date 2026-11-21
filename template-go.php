<?php
/**
 * Template Name: 外链跳转提醒
 * Template Post Type: page
 *
 * 由 inc/link-go.php 的 /go 路由经 template_include 载入。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$echo_lgo_target = echo_lgo_current_target();
$echo_lgo_mode   = echo_lgo_config( 'lgo_mode', 'auto' );
$echo_lgo_delay  = max( 1, min( 30, absint( echo_lgo_config( 'lgo_delay', 5 ) ) ) );
$echo_lgo_title  = echo_lgo_config( 'lgo_title', __( '即将离开本站', 'echo' ) );
$echo_lgo_msg    = echo_lgo_config( 'lgo_message', __( '你正在访问一个外部链接，请确认网址安全后再继续。', 'echo' ) );
$echo_lgo_btn    = echo_lgo_config( 'lgo_btn_text', __( '继续访问', 'echo' ) );
$echo_lgo_newtab = (bool) echo_lgo_config( 'lgo_new_tab', true );
$echo_lgo_host   = $echo_lgo_target ? (string) wp_parse_url( $echo_lgo_target, PHP_URL_HOST ) : '';

get_header();
?>

<main id="primary" class="echo-main echo-lgo-main">
	<div class="echo-lgo-wrap">
		<section class="echo-lgo-card" data-mode="<?php echo esc_attr( $echo_lgo_mode ); ?>"
			<?php if ( $echo_lgo_target ) : ?>
				data-target="<?php echo esc_attr( $echo_lgo_target ); ?>"
				data-delay="<?php echo esc_attr( $echo_lgo_delay ); ?>"
				data-newtab="<?php echo $echo_lgo_newtab ? '1' : '0'; ?>"
			<?php endif; ?>>
			<div class="echo-lgo-card__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
					<polyline points="15 3 21 3 21 9" />
					<line x1="10" y1="14" x2="21" y2="3" />
				</svg>
			</div>

			<?php if ( $echo_lgo_target ) : ?>
				<h1 class="echo-lgo-card__title"><?php echo esc_html( $echo_lgo_title ); ?></h1>
				<p class="echo-lgo-card__desc"><?php echo esc_html( $echo_lgo_msg ); ?></p>

				<div class="echo-lgo-dest">
					<span class="echo-lgo-dest__label"><?php esc_html_e( '目标网站', 'echo' ); ?></span>
					<strong class="echo-lgo-dest__host"><?php echo esc_html( $echo_lgo_host ); ?></strong>
					<code class="echo-lgo-dest__url"><?php echo esc_html( $echo_lgo_target ); ?></code>
				</div>

				<?php if ( 'auto' === $echo_lgo_mode ) : ?>
					<p class="echo-lgo-card__count">
						<?php
						/* translators: %d: 秒数 */
						printf( esc_html__( '%d 秒后自动跳转…', 'echo' ), (int) $echo_lgo_delay );
						?>
					</p>
				<?php else : ?>
					<p class="echo-lgo-card__count"><?php esc_html_e( '请确认后点击下方按钮继续访问。', 'echo' ); ?></p>
				<?php endif; ?>

				<div class="echo-lgo-card__actions">
					<button type="button" class="echo-lgo-btn echo-lgo-btn--primary" id="echo-lgo-go">
						<span id="echo-lgo-go-text"><?php echo esc_html( $echo_lgo_btn ); ?></span>
						<?php if ( 'auto' === $echo_lgo_mode ) : ?>
							<span class="echo-lgo-btn__count" id="echo-lgo-count">(<?php echo (int) $echo_lgo_delay; ?>)</span>
						<?php endif; ?>
					</button>
					<a class="echo-lgo-btn echo-lgo-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'echo' ); ?></a>
				</div>
			<?php else : ?>
				<h1 class="echo-lgo-card__title"><?php esc_html_e( '链接已失效', 'echo' ); ?></h1>
				<p class="echo-lgo-card__desc"><?php esc_html_e( '该跳转链接无效或已过期，请从原页面重新点击。', 'echo' ); ?></p>
				<div class="echo-lgo-card__actions">
					<a class="echo-lgo-btn echo-lgo-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '返回首页', 'echo' ); ?></a>
				</div>
			<?php endif; ?>
		</section>
	</div>
</main>

<?php
get_footer();
