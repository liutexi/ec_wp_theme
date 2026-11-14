<?php
/**
 * 页头模板
 *
 * @package Echo
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="echo-header">
	<div class="echo-container echo-header__inner">
		<div class="echo-brand">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				echo '<span class="echo-brand__dot"></span>';
				echo '<a class="echo-brand" href="' . esc_url( home_url( '/' ) ) . '" rel="home">' . get_bloginfo( 'name' ) . '</a>';
			}
			?>
		</div>

		<nav class="echo-nav" aria-label="<?php esc_attr_e( '主菜单', 'echo' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_id'        => 'echo-primary-menu',
					'container'      => false,
					'fallback_cb'    => 'echo_menu_fallback',
					'depth'          => 2,
				)
			);
			?>
		</nav>

		<div class="echo-header__actions">
			<button class="echo-search-toggle" aria-label="<?php esc_attr_e( '搜索', 'echo' ); ?>" aria-expanded="false">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="8"></circle>
					<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
				</svg>
			</button>

			<button class="echo-nav-toggle" aria-label="<?php esc_attr_e( '打开菜单', 'echo' ); ?>" aria-expanded="false">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>
</header>

<!-- 搜索浮层 -->
<div class="echo-search-overlay" id="echo-search-overlay" aria-hidden="true">
	<div class="echo-search-overlay__box">
		<form class="echo-search-overlay__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="<?php esc_attr_e( '搜索文章…', 'echo' ); ?>" value="<?php echo get_search_query(); ?>" autofocus>
			<button type="submit" class="echo-btn"><?php esc_html_e( '搜索', 'echo' ); ?></button>
		</form>
	</div>
</div>
