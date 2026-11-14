<?php
/**
 * Template Name: 登录 / 注册
 * Template Post Type: page
 *
 * 登录 / 注册 / 找回密码 三态共用这一个页面，通过 ?view= 切换：
 * ?view=login（默认）、?view=register、?view=lostpassword、?view=reset
 *
 * 在后台新建页面、别名设为设置中的别名（默认 login），
 * 「页面属性 → 模板」选择「登录 / 注册」即可。
 *
 * @package Echo
 */

echo_auth_render_page( 'login' );
