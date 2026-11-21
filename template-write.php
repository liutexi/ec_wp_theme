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

// 输出前处理提交 / 删除 / 发布。
echo_front_post_handle_submission();
echo_front_post_handle_deletion();
echo_front_post_handle_publish();

$echo_fp_post    = echo_front_post_editing_post();
$echo_fp_is_edit = (bool) $echo_fp_post;
$echo_fp_title   = $echo_fp_post ? $echo_fp_post->post_title : '';
$echo_fp_content = $echo_fp_post ? $echo_fp_post->post_content : '';
$echo_fp_excerpt = $echo_fp_post ? $echo_fp_post->post_excerpt : '';

// 提交方式的简短说明（仅作界面文案，不影响逻辑）。
$echo_fp_status_desc = array(
	'draft'   => __( '保存为草稿，仅自己可见', 'echo' ),
	'pending' => __( '提交审核，通过后再发布', 'echo' ),
	'publish' => __( '立即发布，公开可见', 'echo' ),
);

// 编辑器依赖：前端使用 wp_editor 需要这些文件。
if ( ! function_exists( 'wp_editor' ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/post.php';
}

get_header();
?>

<main id="primary" class="echo-main echo-fp-page">
	<div class="echo-container">
		<div class="echo-layout echo-layout--full">
			<div class="echo-content">

				<!-- 顶部引导 -->
				<header class="echo-fp-hero">
					<span class="echo-fp-hero__badge"><?php echo_front_post_icon( 'pen' ); ?></span>
					<div class="echo-fp-hero__body">
						<h1 class="echo-fp-hero__title"><?php echo $echo_fp_is_edit ? esc_html__( '编辑文章', 'echo' ) : esc_html__( '写文章', 'echo' ); ?></h1>
						<p class="echo-fp-hero__desc"><?php esc_html_e( '把你的想法变成文字，分享给更多人。', 'echo' ); ?></p>
					</div>
					<?php if ( $echo_fp_is_edit ) : ?>
						<a class="echo-fp-hero__action" href="<?php echo esc_url( echo_front_post_url() ); ?>">
							<?php echo_front_post_icon( 'plus' ); ?>
							<span><?php esc_html_e( '写新文章', 'echo' ); ?></span>
						</a>
					<?php endif; ?>
				</header>

				<?php if ( ! echo_front_post_can_write() ) : ?>

					<section class="echo-fp-panel echo-fp-guest">
						<span class="echo-fp-guest__icon"><?php echo_front_post_icon( 'lock' ); ?></span>
						<h2 class="echo-fp-guest__title"><?php esc_html_e( '请先登录', 'echo' ); ?></h2>
						<p><?php esc_html_e( '你需要登录并具备发文权限后才能撰写文章。', 'echo' ); ?></p>
						<?php
						if ( function_exists( 'echo_auth_url' ) && echo_auth_config( 'auth_enabled', true ) ) {
							$login_url = echo_auth_url( 'login' );
						} else {
							$login_url = wp_login_url( echo_front_post_url() );
						}
						?>
						<p><a class="echo-btn" href="<?php echo esc_url( add_query_arg( 'redirect_to', urlencode( echo_front_post_url() ), $login_url ) ); ?>"><?php echo_front_post_icon( 'lock' ); ?><?php esc_html_e( '前往登录', 'echo' ); ?></a></p>
					</section>

				<?php else : ?>

					<?php echo_front_post_render_feedback(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<div class="echo-fp-alert echo-fp-alert--success" role="status">
							<?php echo_front_post_icon( 'check' ); ?>
							<span>
							<?php
							if ( 'new' === sanitize_key( wp_unslash( $_GET['saved'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
								esc_html_e( '文章已提交。', 'echo' );
							} else {
								esc_html_e( '文章已更新。', 'echo' );
							}
							?>
							</span>
						</div>
					<?php endif; ?>
					<?php if ( isset( $_GET['thumberr'] ) && '1' === sanitize_key( wp_unslash( $_GET['thumberr'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<div class="echo-fp-alert echo-fp-alert--error" role="alert">
							<?php echo_front_post_icon( 'info' ); ?>
							<span><?php esc_html_e( '文章已保存，但封面上传失败，你可以在下方重试。', 'echo' ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( isset( $_GET['deleted'] ) && '1' === sanitize_key( wp_unslash( $_GET['deleted'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<div class="echo-fp-alert echo-fp-alert--success" role="status">
							<?php echo_front_post_icon( 'check' ); ?>
							<span><?php esc_html_e( '文章已删除（已移入回收站）。', 'echo' ); ?></span>
						</div>
					<?php endif; ?>
					<?php if ( isset( $_GET['published'] ) && '1' === sanitize_key( wp_unslash( $_GET['published'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
						<div class="echo-fp-alert echo-fp-alert--success" role="status">
							<?php echo_front_post_icon( 'check' ); ?>
							<span><?php esc_html_e( '文章已发布。', 'echo' ); ?></span>
						</div>
					<?php endif; ?>

					<form class="echo-fp-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $echo_fp_is_edit ? echo_front_post_url( $echo_fp_post->ID ) : echo_front_post_url() ); ?>">
						<input type="hidden" name="echo_fp_action" value="save" />
						<input type="hidden" name="echo_fp_id" value="<?php echo esc_attr( $echo_fp_post ? $echo_fp_post->ID : 0 ); ?>" />
						<?php wp_nonce_field( 'echo_front_post_save', 'echo_fp_nonce' ); ?>
						<div class="echo-fp-hp" aria-hidden="true">
							<label><?php esc_html_e( '请留空', 'echo' ); ?><input type="text" name="echo_fp_hp" value="" tabindex="-1" autocomplete="off" /></label>
						</div>

						<!-- 面板一：文章内容 -->
						<section class="echo-fp-panel">
							<div class="echo-fp-panel__head">
								<span class="echo-fp-panel__icon echo-fp-panel__icon--file"><?php echo_front_post_icon( 'file' ); ?></span>
								<div class="echo-fp-panel__heading">
									<h2><?php esc_html_e( '文章内容', 'echo' ); ?></h2>
									<p><?php esc_html_e( '一个好标题和清晰的正文，是文章成功的一半。', 'echo' ); ?></p>
								</div>
							</div>
							<div class="echo-fp-panel__body">
								<div class="echo-fp-field">
									<label class="echo-fp-label" for="echo_fp_title"><?php esc_html_e( '标题', 'echo' ); ?> <span class="echo-fp-req">*</span></label>
									<input type="text" id="echo_fp_title" name="echo_fp_title" class="echo-fp-input echo-fp-input--title" required maxlength="200"
										value="<?php echo esc_attr( $echo_fp_title ); ?>"
										placeholder="<?php esc_attr_e( '请输入文章标题', 'echo' ); ?>" />
								</div>

								<div class="echo-fp-field">
									<label class="echo-fp-label" for="echo_fp_content"><?php esc_html_e( '正文', 'echo' ); ?></label>
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
							</div>
						</section>

						<!-- 面板二：发布设置 -->
						<section class="echo-fp-panel">
							<div class="echo-fp-panel__head">
								<span class="echo-fp-panel__icon echo-fp-panel__icon--sliders"><?php echo_front_post_icon( 'sliders' ); ?></span>
								<div class="echo-fp-panel__heading">
									<h2><?php esc_html_e( '发布设置', 'echo' ); ?></h2>
									<p><?php esc_html_e( '设置分类、标签与摘要，帮助读者更快找到你的文章。', 'echo' ); ?></p>
								</div>
							</div>
							<div class="echo-fp-panel__body">
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
										<div class="echo-fp-field">
											<span class="echo-fp-label"><?php esc_html_e( '分类', 'echo' ); ?></span>
											<div class="echo-fp-chips">
												<?php foreach ( $cats as $cat ) : ?>
													<label class="echo-fp-chip">
														<input type="checkbox" name="echo_fp_cats[]" value="<?php echo esc_attr( $cat->term_id ); ?>"
															<?php checked( in_array( (int) $cat->term_id, array_map( 'intval', $selected ), true ), true ); ?> />
														<span><?php echo esc_html( $cat->name ); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endif; ?>
								<?php endif; ?>

								<?php if ( echo_front_post_config( 'fp_allow_tags', true ) ) : ?>
									<div class="echo-fp-field">
										<label class="echo-fp-label" for="echo_fp_tags"><?php esc_html_e( '标签', 'echo' ); ?></label>
										<input type="text" id="echo_fp_tags" name="echo_fp_tags" class="echo-fp-input"
											value="<?php echo $echo_fp_is_edit ? esc_attr( implode( ', ', wp_get_post_tags( $echo_fp_post->ID, array( 'fields' => 'names' ) ) ) ) : ''; ?>"
											placeholder="<?php esc_attr_e( '多个标签用英文逗号分隔', 'echo' ); ?>" />
									</div>
								<?php endif; ?>

								<div class="echo-fp-field">
									<label class="echo-fp-label" for="echo_fp_excerpt"><?php esc_html_e( '摘要', 'echo' ); ?></label>
									<textarea id="echo_fp_excerpt" name="echo_fp_excerpt" class="echo-fp-input echo-fp-textarea" rows="3"
										placeholder="<?php esc_attr_e( '可选，不填则自动截取正文', 'echo' ); ?>"><?php echo esc_textarea( $echo_fp_excerpt ); ?></textarea>
								</div>
							</div>
						</section>

						<!-- 面板三：封面与发布方式 -->
						<section class="echo-fp-panel">
							<div class="echo-fp-panel__head">
								<span class="echo-fp-panel__icon echo-fp-panel__icon--image"><?php echo_front_post_icon( 'image' ); ?></span>
								<div class="echo-fp-panel__heading">
									<h2><?php esc_html_e( '封面与发布', 'echo' ); ?></h2>
									<p><?php esc_html_e( '选择封面图片和提交方式，然后就可以发布了。', 'echo' ); ?></p>
								</div>
							</div>
							<div class="echo-fp-panel__body">
								<?php if ( echo_front_post_config( 'fp_allow_thumbnail', true ) ) : ?>
									<div class="echo-fp-field">
										<span class="echo-fp-label"><?php esc_html_e( '封面图片', 'echo' ); ?></span>
										<?php if ( $echo_fp_is_edit && has_post_thumbnail( $echo_fp_post->ID ) ) : ?>
											<div class="echo-fp-thumb-current">
												<?php echo get_the_post_thumbnail( $echo_fp_post->ID, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
												<label class="echo-fp-check">
													<input type="checkbox" name="echo_fp_remove_thumb" value="1" />
													<span><?php esc_html_e( '移除当前封面', 'echo' ); ?></span>
												</label>
											</div>
										<?php endif; ?>
										<label class="echo-fp-upload" for="echo_fp_thumb">
											<?php echo_front_post_icon( 'upload' ); ?>
											<span class="echo-fp-upload__text"><?php esc_html_e( '点击选择封面图片', 'echo' ); ?></span>
											<input type="file" name="echo_fp_thumb" id="echo_fp_thumb" accept="image/*" class="echo-fp-upload__input" />
										</label>
										<p class="echo-fp-hint">
											<?php
											/* translators: %d: 最大 KB */
											printf( esc_html__( '最大 %d KB，支持 JPG / PNG / GIF / WebP。', 'echo' ), (int) max( 256, min( 8192, absint( echo_front_post_config( 'fp_thumbnail_max_kb', 4096 ) ) ) ) );
											?>
										</p>
									</div>
								<?php endif; ?>

								<?php $statuses = echo_front_post_status_options(); ?>
								<?php if ( count( $statuses ) > 1 ) : ?>
									<div class="echo-fp-field">
										<span class="echo-fp-label"><?php esc_html_e( '提交方式', 'echo' ); ?></span>
										<div class="echo-fp-statuses">
											<?php
											$current_status = $echo_fp_is_edit ? $echo_fp_post->post_status : echo_front_post_config( 'fp_default_status', 'pending' );
											foreach ( $statuses as $s_key => $s_label ) :
												?>
												<label class="echo-fp-status-option">
													<input type="radio" name="echo_fp_status" value="<?php echo esc_attr( $s_key ); ?>"
														<?php checked( $s_key, $current_status ); ?> />
													<span class="echo-fp-status-option__body">
														<strong><?php echo esc_html( $s_label ); ?></strong>
														<?php if ( isset( $echo_fp_status_desc[ $s_key ] ) ) : ?>
															<small><?php echo esc_html( $echo_fp_status_desc[ $s_key ] ); ?></small>
														<?php endif; ?>
													</span>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
								<?php else : ?>
									<input type="hidden" name="echo_fp_status" value="<?php echo esc_attr( key( $statuses ) ); ?>" />
								<?php endif; ?>
							</div>
						</section>

						<!-- 底部操作栏 -->
						<div class="echo-fp-submitbar">
							<p class="echo-fp-submitbar__tip"><?php echo_front_post_icon( 'info' ); ?><span><?php esc_html_e( '提交前请再检查一遍标题与正文。', 'echo' ); ?></span></p>
							<div class="echo-fp-submitbar__actions">
								<button type="submit" class="echo-btn echo-fp-submit">
									<?php echo_front_post_icon( 'send' ); ?>
									<span><?php echo $echo_fp_is_edit ? esc_html__( '保存修改', 'echo' ) : esc_html__( '提交文章', 'echo' ); ?></span>
								</button>
							</div>
						</div>
					</form>

					<!-- 文章列表（管理员为全站文章管理） -->
					<section class="echo-fp-panel echo-fp-mylist">
						<div class="echo-fp-panel__head">
							<span class="echo-fp-panel__icon echo-fp-panel__icon--list"><?php echo_front_post_icon( 'list' ); ?></span>
							<div class="echo-fp-panel__heading">
								<h2><?php echo_front_post_is_manager() ? esc_html_e( '文章管理', 'echo' ) : esc_html_e( '我的文章', 'echo' ); ?></h2>
								<p><?php echo_front_post_is_manager() ? esc_html_e( '全站文章：可编辑、发布或删除任意文章。', 'echo' ) : esc_html_e( '你最近创建的文章，点击标题可继续编辑。', 'echo' ); ?></p>
							</div>
						</div>
						<div class="echo-fp-panel__body">
							<?php $my_posts = echo_front_post_my_posts( 20 ); ?>
							<?php if ( empty( $my_posts ) ) : ?>
								<div class="echo-fp-empty">
									<?php echo_front_post_icon( 'file' ); ?>
									<p><?php esc_html_e( '还没有文章，从上面开始创作吧。', 'echo' ); ?></p>
								</div>
							<?php else : ?>
								<ul class="echo-fp-posts">
									<?php foreach ( $my_posts as $mp ) : ?>
										<li class="echo-fp-posts__item">
											<span class="echo-fp-posts__icon"><?php echo_front_post_icon( 'file' ); ?></span>
											<span class="echo-fp-posts__title">
												<?php if ( echo_front_post_can_edit( $mp->ID ) ) : ?>
													<a href="<?php echo esc_url( echo_front_post_url( $mp->ID ) ); ?>"><?php echo esc_html( $mp->post_title ? $mp->post_title : __( '（无标题）', 'echo' ) ); ?></a>
												<?php else : ?>
													<?php echo esc_html( $mp->post_title ); ?>
												<?php endif; ?>
											</span>
											<span class="echo-fp-posts__meta">
												<?php if ( echo_front_post_is_manager() ) : ?>
													<span class="echo-fp-posts__author"><?php echo esc_html( get_the_author_meta( 'display_name', $mp->post_author ) ); ?></span>
												<?php endif; ?>
												<span class="echo-fp-status echo-fp-status--<?php echo esc_attr( $mp->post_status ); ?>"><?php echo esc_html( echo_front_post_status_label( $mp->post_status ) ); ?></span>
												<time><?php echo esc_html( get_the_date( '', $mp ) ); ?></time>
											</span>
											<span class="echo-fp-posts__actions">
												<?php if ( echo_front_post_can_edit( $mp->ID ) ) : ?>
													<a class="echo-fp-posts__action" href="<?php echo esc_url( echo_front_post_url( $mp->ID ) ); ?>" title="<?php esc_attr_e( '编辑', 'echo' ); ?>"><?php echo_front_post_icon( 'pen' ); ?></a>
												<?php endif; ?>
												<?php if ( echo_front_post_is_manager() && 'publish' !== $mp->post_status && echo_front_post_can_edit( $mp->ID ) ) : ?>
													<form class="echo-fp-posts__inline-form" method="post" action="<?php echo esc_url( echo_front_post_url() ); ?>">
														<input type="hidden" name="echo_fp_action" value="publish" />
														<input type="hidden" name="echo_fp_id" value="<?php echo esc_attr( $mp->ID ); ?>" />
														<?php wp_nonce_field( 'echo_front_post_publish', 'echo_fp_pub_nonce' ); ?>
														<button type="submit" class="echo-fp-posts__action echo-fp-posts__action--publish" title="<?php esc_attr_e( '直接发布', 'echo' ); ?>"><?php echo_front_post_icon( 'send' ); ?></button>
													</form>
												<?php endif; ?>
												<?php if ( echo_front_post_can_delete( $mp->ID ) ) : ?>
													<form class="echo-fp-posts__inline-form" method="post" action="<?php echo esc_url( echo_front_post_url() ); ?>">
														<input type="hidden" name="echo_fp_action" value="delete" />
														<input type="hidden" name="echo_fp_id" value="<?php echo esc_attr( $mp->ID ); ?>" />
														<?php wp_nonce_field( 'echo_front_post_delete', 'echo_fp_del_nonce' ); ?>
														<button type="submit" class="echo-fp-posts__action echo-fp-posts__action--delete" title="<?php esc_attr_e( '删除', 'echo' ); ?>" onclick="return confirm('<?php echo esc_js( __( '确定要删除这篇文章吗？将移入回收站。', 'echo' ) ); ?>');"><?php echo_front_post_icon( 'trash' ); ?></button>
													</form>
												<?php endif; ?>
											</span>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</section>

				<?php endif; ?>
			</div>
		</div>
	</div>
</main>

<?php if ( echo_front_post_can_write() ) : ?>
<script>
(function () {
	var input = document.getElementById( 'echo_fp_thumb' );
	if ( ! input ) { return; }
	var box  = document.querySelector( '.echo-fp-upload' );
	var text = document.querySelector( '.echo-fp-upload__text' );
	input.addEventListener( 'change', function () {
		var file = input.files && input.files[0];
		if ( ! file ) { return; }
		if ( text ) { text.textContent = file.name; }
		if ( box ) { box.classList.add( 'echo-fp-upload--has-file' ); }
	} );
})();
</script>
<?php endif; ?>

<?php
get_footer();
