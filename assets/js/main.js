/**
 * Echo 主题交互脚本
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initMobileNav();
		initSearchOverlay();
	});

	/**
	 * 移动端导航菜单切换
	 */
	function initMobileNav() {
		var toggle = document.querySelector('.echo-nav-toggle');
		var nav = document.querySelector('.echo-nav');

		if (!toggle || !nav) {
			return;
		}

		toggle.addEventListener('click', function () {
			var isOpen = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			toggle.setAttribute('aria-label', isOpen ? '关闭菜单' : '打开菜单');
		});

		// 点击菜单项后自动收起（移动端）
		nav.addEventListener('click', function (e) {
			if (e.target.closest('a') && window.innerWidth <= 860) {
				nav.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	/**
	 * 搜索浮层
	 */
	function initSearchOverlay() {
		var toggle = document.querySelector('.echo-search-toggle');
		var overlay = document.getElementById('echo-search-overlay');
		var input = overlay ? overlay.querySelector('input[type="search"]') : null;

		if (!toggle || !overlay) {
			return;
		}

		function openSearch() {
			overlay.classList.add('is-open');
			overlay.setAttribute('aria-hidden', 'false');
			toggle.setAttribute('aria-expanded', 'true');
			document.body.style.overflow = 'hidden';
			if (input) {
				input.focus();
			}
		}

		function closeSearch() {
			overlay.classList.remove('is-open');
			overlay.setAttribute('aria-hidden', 'true');
			toggle.setAttribute('aria-expanded', 'false');
			document.body.style.overflow = '';
		}

		toggle.addEventListener('click', function () {
			if (overlay.classList.contains('is-open')) {
				closeSearch();
			} else {
				openSearch();
			}
		});

		// 点击遮罩空白处关闭
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				closeSearch();
			}
		});

		// 按 Esc 关闭
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
				closeSearch();
			}
		});
	}
})();
