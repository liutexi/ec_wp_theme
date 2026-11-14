<?php
/**
 * 侧边栏模板
 *
 * @package Echo
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>

<aside id="secondary" class="echo-sidebar">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
