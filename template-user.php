<?php
/**
 * 用户主页模板
 *
 * 不通过「页面模板」选择，而是由 /uid/{id} 重写规则经 template_include 载入。
 * 未登录用户也可查看；本人（或有 edit_user 权限）可在此编辑资料。
 *
 * @package Echo
 */

echo_user_render_page();
