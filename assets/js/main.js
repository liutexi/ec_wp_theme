/**
 * Echo 主题交互脚本
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initMobileNav();
		initSearchOverlay();
		initReadingProgress();
		initBackToTop();
		initPasswordToggle();
		initAvatarInput();
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

	/**
	 * 文章阅读进度条（仅当容器存在时启用）
	 */
	function initReadingProgress() {
		var bar = document.querySelector('.echo-reading-progress__bar');
		if (!bar) {
			return;
		}

		function update() {
			var doc = document.documentElement;
			var scrollable = doc.scrollHeight - window.innerHeight;
			var pct = scrollable > 0 ? (window.pageYOffset / scrollable) * 100 : 0;
			bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
		}

		update();
		window.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
	}

	/**
	 * 返回顶部按钮（仅当容器存在时启用）
	 */
	function initBackToTop() {
		var btn = document.querySelector('.echo-back-to-top');
		if (!btn) {
			return;
		}

		function update() {
			btn.classList.toggle('is-visible', window.pageYOffset > 400);
		}

		update();
		window.addEventListener('scroll', update, { passive: true });

		btn.addEventListener('click', function () {
			var smooth = getComputedStyle(document.documentElement).scrollBehavior === 'smooth';
			try {
				window.scrollTo({ top: 0, behavior: smooth ? 'smooth' : 'auto' });
			} catch (e) {
				window.scrollTo(0, 0);
			}
		});
	}

	/**
	 * 密码显隐切换（登录 / 注册表单）
	 */
	function initPasswordToggle() {
		var buttons = document.querySelectorAll('.echo-auth__eye');
		if (!buttons.length) {
			return;
		}

		Array.prototype.forEach.call(buttons, function (btn) {
			btn.addEventListener('click', function () {
				var wrap = btn.closest('.echo-auth__pwd-wrap');
				var input = wrap ? wrap.querySelector('input') : null;
				if (!input) {
					return;
				}
				var show = input.getAttribute('type') === 'password';
				input.setAttribute('type', show ? 'text' : 'password');
				btn.classList.toggle('is-visible', show);
				btn.setAttribute('aria-pressed', show ? 'true' : 'false');
			});
		});
	}
	/**
	 * 头像上传前端预检：体积 / 类型不合格时立即提示并清空，避免提交后才失败
	 */
	function initAvatarInput() {
		var input = document.querySelector('input[type="file"][data-max-kb]');
		if (!input) {
			return;
		}

		var errorEl = input.parentNode.querySelector('.echo-user-hint--avatar-error');
		var hintEl = input.parentNode.querySelector('.echo-user-hint--avatar');
		var maxKb = parseInt(input.getAttribute('data-max-kb'), 10) || 0;
		var allowed = /^image\/(jpeg|png|gif|webp)$/i;

		function showError(text) {
			input.value = '';
			if (errorEl) {
				errorEl.textContent = text;
				errorEl.hidden = false;
			}
			if (hintEl) {
				hintEl.style.display = 'none';
			}
		}

		input.addEventListener('change', function () {
			if (errorEl) {
				errorEl.hidden = true;
				errorEl.textContent = '';
			}
			if (hintEl) {
				hintEl.style.display = '';
			}

			var file = input.files && input.files[0];
			if (!file) {
				return;
			}

			if (file.type && !allowed.test(file.type)) {
				showError('只支持 JPG / PNG / GIF / WebP 图片。');
				return;
			}

			if (maxKb && file.size > maxKb * 1024) {
				var mb = (file.size / 1024 / 1024).toFixed(1);
				showError('这张图约 ' + mb + ' MB，超过 ' + maxKb + ' KB 限制，请压缩后再上传。');
			}
		});
	}
})();
