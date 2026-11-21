<?php
/**
 * Template Name: 写文章
 * Template Post Type: page
 *
 * 由 inc/front-post.php 的 /write 路由经 template_include 载入；
 * 也可在后台手动建页面并选择本模板（配合查询参数 echo_write=1）。
 *
 * @package Echo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 输出前处理提交 / 加载编辑器依赖。
echo_front_post_handle_submission();

$echo_fp_post    = echo_front_post_editing_post();
$echo_fp_is_edit = (bool) $echo_fp_post;
$echo_fp_title   = $echo_fp_post ? $echo_fp_post->post_title : '';
$echo_fp_content = $echo_fp_post ? $echo_fp_post->post_content : '';
$echo_fp_excerpt = $echo_fp_post ? $echo_fp_post->post_excerpt : '';

// 编辑器依赖：前端使用 wp_editor 需要这些文件。
if ( ! function_exists( 'wp_editor' ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/post.php';
}

get_header();
?>

<main id="primary" class="echo-main">
	<div class="echo-container">
		<div class="echo-layout echo-layout--full">
			<div class="echo-content">
				<article class="echo-single echo-single--write">
					<header class="echo-single__header">
						<h1 class="echo-single__title"><?php echo $echo_fp_is_edit ? esc_html__( '编辑文章', 'echo' ) : esc_html__( '写文章', 'echo' ); ?></h1>
					</header>

					<?php if ( ! echo_front_post_can_write() ) : ?>

						<section class="echo-write-card echo-write-guest">
							<h2 class="echo-write-card__title"><?php esc_html_e( '请先登录', 'echo' ); ?></h2>
							<p><?php esc_html_e( '你需要登录并具备发文权限后才能撰写文章。', 'echo' ); ?></p>
							<?php
							if ( function_exists( 'echo_auth_url' ) && echo_auth_config( 'auth_enabled', true ) ) {
								$login_url = echo_auth_url( 'login' );
							} else {
								$login_url = wp_login_url( echo_front_post_url() );
							}
							?>
							<p><a class="echo-btn" href="<?php echo esc_url( add_query_arg( 'redirect_to', urlencode( echo_front_post_url() ), $login_url ) ); ?>"><?php esc_html_e( '前往登录', 'echo' ); ?></a></p>
						</section>

					<?php else : ?>

						<?php echo_front_post_render_feedback(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

						<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
							<?php if ( 'new' === sanitize_key( wp_unslash( $_GET['saved'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
								<div class="echo-write-alert echo-write-alert--success" role="status"><?php esc_html_e( '文章已提交。', 'echo' ); ?></div>
							<?php else : ?>
								<div class="echo-write-alert echo-write-alert--success" role="status"><?php esc_html_e( '文章已更新。', 'echo' ); ?></div>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( isset( $_GET['thumberr'] ) && '1' === sanitize_key( wp_unslash( $_GET['thumberr'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
							<div class="echo-write-alert echo-write-alert--error" role="alert"><?php esc_html_e( '文章已保存，但封面上传失败，你可以在上方重试。', 'echo' ); ?></div>
						<?php endif; ?>

						<form class="echo-write-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $echo_fp_is_edit ? echo_front_post_url( $echo_fp_post->ID ) : echo_front_post_url() ); ?>">
							<input type="hidden" name="echo_fp_action" value="save" />
							<input type="hidden" name="echo_fp_id" value="<?php echo esc_attr( $echo_fp_post ? $echo_fp_post->ID : 0 ); ?>" />
							<?php wp_nonce_field( 'echo_front_post_save', 'echo_fp_nonce' ); ?>
							<div class="echo-write-hp" aria-hidden="true">
								<label><?php esc_html_e( '请留空', 'echo' ); ?><input type="text" name="echo_fp_hp" value="" tabindex="-1" autocomplete="off" /></label>
							</div>

							<section class="echo-write-card">
								<div class="echo-write-field">
									<label class="echo-write-label" for="echo_fp_title"><?php esc_html_e( '标题', 'echo' ); ?> <span class="echo-write-req">*</span></label>
									<input type="text" id="echo_fp_title" name="echo_fp_title" class="echo-write-input" required maxlength="200"
										value="<?php echo esc_attr( $echo_fp_title ); ?>"
										placeholder="<?php esc_attr_e( '请输入文章标题', 'echo' ); ?>" />
								</div>

								<div class="echo-write-field">
									<label class="echo-write-label" for="echo_fp_content"><?php esc_html_e( '正文', 'echo' ); ?></label>
									<?php
									wp_editor(
										$echo_fp_content,
										'echo_fp_content',
										array(
											'textarea_name' => 'echo_fp_content',
											'media_buttons' => false,
											'textarea_rows' => 16,
											'tinymce'       => array(
												'toolbar1' => 'bold,italic,strikethrough,blockquote,bullist,numlist,link,unlink,undo,redo',
												'toolbar2' => '',
											),
										)
									);
									?>
								</div>

								<?php if ( echo_front_post_config( 'fp_allow_category', true ) ) : ?>
									<?php
									$cats = get_categories(
										array(
											'hide_empty' => false,
											'orderby'    => 'name',
											'order'      => 'ASC',
										)
									);
									$selected = $echo_fp_is_edit ? wp_get_post_categories( $echo_fp_post->ID ) : array();
									?>
									<?php if ( ! empty( $cats ) ) : ?>
										<div class="echo-write-field">
											<span class="echo-write-label"><?php esc_html_e( '分类', 'echo' ); ?></span>
											<div class="echo-write-cats">
												<?php foreach ( $cats as $cat ) : ?>
													<label class="echo-write-check">
														<input type="checkbox" name="echo_fp_cats[]" value="<?php echo esc_attr( $cat->term_id ); ?>"
															<?php checked( in_array( (int) $cat->term_id, array_map( 'intval', $selected ), true ), true ); ?> />
														<?php echo esc_html( $cat->name ); ?>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endif; ?>
								<?php endif; ?>

								<?php if ( echo_front_post_config( 'fp_allow_tags', true ) ) : ?>
									<div class="echo-write-field">
										<label class="echo-write-label" for="echo_fp_tags"><?php esc_html_e( '标签', 'echo' ); ?></label>
										<input type="text" id="echo_fp_tags" name="echo_fp_tags" class="echo-write-input"
											value="<?php echo $echo_fp_is_edit ? esc_attr( implode( ', ', wp_get_post_tags( $echo_fp_post->ID, array( 'fields' => 'names' ) ) ) ) : ''; ?>"
											placeholder="<?php esc_attr_e( '多个标签用英文逗号分隔', 'echo' ); ?>" />
									</div>
								<?php endif; ?>

								<div class="echo-write-field">
									<label class="echo-write-label" for="echo_fp_excerpt"><?php esc_html_e( '摘要', 'echo' ); ?></label>
									<textarea id="echo_fp_excerpt" name="echo_fp_excerpt" class="echo-write-textarea" rows="3"
										placeholder="<?php esc_attr_e( '可选，不填则自动截取正文', 'echo' ); ?>"><?php echo esc_textarea( $echo_fp_excerpt ); ?></textarea>
								</div>

								<?php if ( echo_front_post_config( 'fp_allow_thumbnail', true ) ) : ?>
									<div class="echo-write-field">
										<span class="echo-write-label"><?php esc_html_e( '封面图片', 'echo' ); ?></span>
										<?php if ( $echo_fp_is_edit && has_post_thumbnail( $echo_fp_post->ID ) ) : ?>
											<div class="echo-write-thumb-current">
												<?php echo get_the_post_thumbnail( $echo_fp_post->ID, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												<label class="echo-write-check">
													<input type="checkbox" name="echo_fp_remove_thumb" value="1" />
													<?php esc_html_e( '移除当前封面', 'echo' ); ?>
												</label>
											</div>
										<?php endif; ?>
										<input type="file" name="echo_fp_thumb" id="echo_fp_thumb" accept="image/*" class="echo-write-file" />
										<p class="echo-write-hint">
											<?php
											/* translators: %d: 最大 KB */
											printf( esc_html__( '最大 %d KB，支持 JPG / PNG / GIF / WebP。', 'echo' ), (int) max( 256, min( 8192, absint( echo_front_post_config( 'fp_thumbnail_max_kb', 4096 ) ) ) ) );
											?>
										</p>
									</div>
								<?php endif; ?>

								<?php $statuses = echo_front_post_status_options(); ?>
								<?php if ( count( $statuses ) > 1 ) : ?>
									<div class="echo-write-field">
										<span class="echo-write-label"><?php esc_html_e( '提交方式', 'echo' ); ?></span>
										<div class="echo-write-statuses">
											<?php
											$current_status = $echo_fp_is_edit ? $echo_fp_post->post_status : echo_front_post_config( 'fp_default_status', 'pending' );
											foreach ( $statuses as $s_key => $s_label ) :
												?>
												<label class="echo-write-radio">
													<input type="radio" name="echo_fp_status" value="<?php echo esc_attr( $s_key ); ?>"
														<?php checked( $s_key, $current_status ); ?> />
													<?php echo esc_html( $s_label ); ?>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
								<?php else : ?>
									<input type="hidden" name="echo_fp_status" value="<?php echo esc_attr( key( $statuses ) ); ?>" />
								<?php endif; ?>

								<div class="echo-write-actions">
									<button type="submit" class="echo-btn echo-write-submit">
										<?php echo $echo_fp_is_edit ? esc_html__( '保存修改', 'echo' ) : esc_html__( '提交文章', 'echo' ); ?>
									</button>
									<?php if ( $echo_fp_is_edit ) : ?>
										<a class="echo-btn echo-btn--ghost" href="<?php echo esc_url( echo_front_post_url() ); ?>"><?php esc_html_e( '写一篇新的', 'echo' ); ?></a>
									<?php endif; ?>
								</div>
							</section>
						</form>

						<section class="echo-write-card echo-write-mylist">
							<h2 class="echo-write-card__title"><?php esc_html_e( '我的文章', 'echo' ); ?></h2>
							<?php $my_posts = echo_front_post_my_posts( 10 ); ?>
							<?php if ( empty( $my_posts ) ) : ?>
								<p class="echo-write-empty"><?php esc_html_e( '你还没有发布过文章。', 'echo' ); ?></p>
							<?php else : ?>
								<ul class="echo-write-posts">
									<?php foreach ( $my_posts as $mp ) : ?>
										<li class="echo-write-posts__item">
											<span class="echo-write-posts__title">
												<?php if ( echo_front_post_can_edit( $mp->ID ) ) : ?>
													<a href="<?php echo esc_url( echo_front_post_url( $mp->ID ) ); ?>"><?php echo esc_html( $mp->post_title ? $mp->post_title : __( '（无标题）', 'echo' ) ); ?></a>
												<?php else : ?>
													<?php echo esc_html( $mp->post_title ); ?>
												<?php endif; ?>
											</span>
											<span class="echo-write-posts__meta">
												<span class="echo-write-status echo-write-status--<?php echo esc_attr( $mp->post_status ); ?>"><?php echo esc_html( echo_front_post_status_label( $mp->post_status ) ); ?></span>
												<time><?php echo esc_html( get_the_date( '', $mp ) ); ?></time>
											</span>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</section>

					<?php endif; ?>
				</article>
			</div>
		</div>
	</div>
</main>

<?php
get_footer();
