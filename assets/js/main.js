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
		initAvatarFallback();
		initCodeCopy();
		initAuthExperience();
		initSubmitGuard();
		initEmailCode();
		initPageProgress();
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
		var preview = document.querySelector('.echo-user-avatar-row__img');
		if (preview) {
			preview.addEventListener('error', function () {
				var box = preview.parentNode;
				var tip = box ? box.querySelector('.echo-user-hint') : null;
				if (tip) {
					tip.textContent = '已保存的头像文件加载失败（地址可能已失效），请重新上传。';
					tip.style.color = '#b91c1c';
				}
			});
		}

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
	/**
	 * 头像兜底：图片加载失败（Gravatar 不可达 / 自定义头像失效）时
	 * 换成本地生成的首字母头像，避免出现破图
	 */
	function initAvatarFallback() {
		var imgs = document.querySelectorAll('img.avatar');
		if (!imgs.length) {
			return;
		}

		var primary = '';
		try {
			primary = (getComputedStyle(document.documentElement).getPropertyValue('--echo-primary') || '').trim();
		} catch (e) {
			primary = '';
		}

		Array.prototype.forEach.call(imgs, function (img) {
			img.addEventListener('error', function () {
				applyFallback(img, primary);
			});

			// 已在缓存里失败过的图不会触发 error
			if (img.complete && img.naturalWidth === 0) {
				applyFallback(img, primary);
			}
		});
	}

	function applyFallback(img, primary) {
		if (img.getAttribute('data-echo-fallback') === '1') {
			return;
		}
		img.setAttribute('data-echo-fallback', '1');
		img.src = buildLetterAvatar((img.getAttribute('alt') || '?').trim(), primary);
	}

	function buildLetterAvatar(name, primary) {
		var ch = name ? name.charAt(0) : '?';
		if (/[a-z]/.test(ch)) {
			ch = ch.toUpperCase();
		}
		var color = primary || '#2563eb';
		var svg =
			'<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">' +
			'<rect width="96" height="96" fill="' + escapeXml(color) + '"/>' +
			'<text x="48" y="49" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Microsoft YaHei,sans-serif" ' +
			'font-size="42" font-weight="700" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">' +
			escapeXml(ch) + '</text></svg>';
		return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
	}

	function escapeXml(str) {
		return String(str).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[c];
		});
	}
	/**
	 * 代码块右上角复制按钮
	 */
	function initCodeCopy() {
		var cfg = window.echoL10n || {};
		if (cfg.codeCopy === false) {
			return;
		}

		var blocks = document.querySelectorAll('.echo-entry-content pre');
		if (!blocks.length) {
			return;
		}

		Array.prototype.forEach.call(blocks, function (pre) {
			var parent = pre.parentNode;
			if (!parent) {
				return;
			}
			if (parent.classList && parent.classList.contains('echo-code-wrap')) {
				return;
			}

			// 包一层容器，横向滚动代码时按钮固定在右上角
			var wrap = document.createElement('div');
			wrap.className = 'echo-code-wrap';
			parent.insertBefore(wrap, pre);
			wrap.appendChild(pre);

			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'echo-code-copy';
			btn.textContent = cfg.copy || '复制';
			btn.setAttribute('aria-label', cfg.copy || '复制');
			wrap.appendChild(btn);

			var reset = function () {
				btn.textContent = cfg.copy || '复制';
				btn.classList.remove('is-copied');
			};

			btn.addEventListener('click', function () {
				copyText(getCodeText(pre)).then(
					function () {
						btn.textContent = cfg.copied || '已复制';
						btn.classList.add('is-copied');
						window.setTimeout(reset, 1800);
					},
					function () {
						btn.textContent = cfg.copyFail || '复制失败';
						window.setTimeout(reset, 1800);
					}
				);
			});
		});
	}

	function getCodeText(pre) {
		var code = pre.querySelector('code');
		if (code) {
			return (code.innerText || code.textContent || '').replace(/\s+$/, '');
		}
		var clone = pre.cloneNode(true);
		var btn = clone.querySelector('.echo-code-copy');
		if (btn && btn.parentNode) {
			btn.parentNode.removeChild(btn);
		}
		return (clone.textContent || '').replace(/\s+$/, '');
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.top = '-9999px';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			try {
				ta.setSelectionRange(0, ta.value.length);
			} catch (e) {
				/* 旧浏览器忽略 */
			}
			var ok = false;
			try {
				ok = document.execCommand('copy');
			} catch (e) {
				ok = false;
			}
			document.body.removeChild(ta);
			ok ? resolve() : reject(new Error('copy failed'));
		});
	}

	/**
	 * 登录 / 注册体验增强
	 * - 密码强度
	 * - 确认密码实时比对
	 * - 字段实时校验（邮箱格式 / 最小长度）
	 * - 错误字段高亮并聚焦（服务端返回 data-error-field）
	 */
	function initAuthExperience() {
		var forms = document.querySelectorAll('.echo-auth__form');
		if (!forms.length) {
			return;
		}

		Array.prototype.forEach.call(forms, function (form) {
			// 密码强度
			Array.prototype.forEach.call(form.querySelectorAll('[data-strength="1"]'), function (input) {
				var wrap = form.querySelector('[data-strength-for="' + input.id + '"]');
				if (!wrap) {
					return;
				}
				var bar = wrap.querySelector('.echo-auth__strength-bar');
				var text = wrap.querySelector('.echo-auth__strength-text');
				var min = parseInt(input.getAttribute('data-min') || '6', 10);

				input.addEventListener('input', function () {
					var val = input.value;
					if (!val) {
						wrap.hidden = true;
						bar.style.width = '0%';
						text.textContent = '';
						return;
					}
					var score = 0;
					if (val.length >= min) { score++; }
					if (val.length >= min + 4) { score++; }
					if (/[a-z]/.test(val) && /[A-Z]/.test(val)) { score++; }
					if (/[0-9]/.test(val)) { score++; }
					if (/[^A-Za-z0-9]/.test(val)) { score++; }

					wrap.hidden = false;
					var level, label, width;
					if (val.length < min) {
						level = 'weak'; label = '太短（至少 ' + min + ' 位）'; width = 25;
					} else if (score <= 2) {
						level = 'weak'; label = '弱'; width = 33;
					} else if (score === 3) {
						level = 'mid'; label = '中'; width = 66;
					} else {
						level = 'strong'; label = '强'; width = 100;
					}
					wrap.setAttribute('data-level', level);
					bar.style.width = width + '%';
					text.textContent = label;
				});
			});

			// 确认密码实时比对
			Array.prototype.forEach.call(form.querySelectorAll('[data-match]'), function (input) {
				var target = form.querySelector('#' + input.getAttribute('data-match'));
				if (!target) {
					return;
				}
				function check() {
					if (!input.value) {
						input.setCustomValidity('');
						input.classList.remove('is-invalid');
						return;
					}
					var ok = input.value === target.value;
					input.classList.toggle('is-invalid', !ok);
					input.setCustomValidity(ok ? '' : '两次输入的密码不一致');
				}
				input.addEventListener('input', check);
				target.addEventListener('input', check);
			});

			// 邮箱格式
			var email = form.querySelector('[data-field="user_email"]');
			if (email) {
				email.addEventListener('blur', function () {
					var ok = !email.value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
					email.classList.toggle('is-invalid', !ok);
				});
			}

			// 用户重新输入时立即清除错误态（有 data-match 的字段由一致性检查自行管理）
			Array.prototype.forEach.call(form.querySelectorAll('[data-field]'), function (el) {
				if (el.hasAttribute('data-match')) {
					return;
				}
				el.addEventListener('input', function () {
					if (el.value.trim() !== '') {
						el.classList.remove('is-invalid');
						el.removeAttribute('aria-invalid');
					}
				});
			});

			// 服务端错误字段：高亮 + 聚焦
			var alert = document.querySelector('.echo-auth__alert--error[data-error-field]');
			if (alert) {
				var field = alert.getAttribute('data-error-field');
				var target = form.querySelector('[data-field="' + field + '"]');
				if (target) {
					target.classList.add('is-invalid');
					target.setAttribute('aria-invalid', 'true');
					try {
						target.focus({ preventScroll: true });
					} catch (e) {
						target.focus();
					}
				}
			}
		});
	}

	/**
	 * 提交前的前端校验 + 防重复
	 * - 必填为空 / 邮箱格式错 / 两次密码不一致时拦下并聚焦
	 * - 通过后按钮进入 loading，避免连点重复提交
	 */
	function initSubmitGuard() {
		Array.prototype.forEach.call(document.querySelectorAll('.echo-auth__form'), function (form) {
			form.addEventListener('submit', function (e) {
				var bad = null;

				Array.prototype.forEach.call(form.querySelectorAll('[required], [data-match], [type="email"]'), function (el) {
					if (bad) {
						return;
					}
					// 蜜罐 / 隐藏字段跳过
					if (el.type === 'hidden' || el.classList.contains('echo-auth__hp')) {
						return;
					}
					if (el.type === 'checkbox') {
						if (el.required && !el.checked) {
							bad = el;
						}
						return;
					}
					if (el.required && el.value.trim() === '') {
						bad = el;
						return;
					}
					if (el.type === 'email' && el.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value)) {
						bad = el;
						return;
					}
					if (el.validity && el.validity.customError) {
						bad = el;
					}
				});

				if (bad) {
					e.preventDefault();
					bad.classList.add('is-invalid');
					try {
						bad.focus({ preventScroll: false });
					} catch (err) {
						bad.focus();
					}
					return;
				}

				var btn = form.querySelector('.echo-auth__submit');
				if (!btn || btn.classList.contains('is-loading')) {
					if (btn) {
						e.preventDefault();
					}
					return;
				}
				btn.classList.add('is-loading');
				btn.disabled = true;
				if (!btn.getAttribute('data-label')) {
					btn.setAttribute('data-label', btn.textContent);
				}
				btn.textContent = '处理中…';
			});
		});
	}

	/**
	 * 注册邮箱验证码：发送、倒计时、状态提示
	 */
	function initEmailCode() {
		var buttons = document.querySelectorAll('[data-echo-send-code]');
		if (!buttons.length) {
			return;
		}

		var cfg = window.echoL10n || {};
		var labels = cfg.mail || {};

		function t(key, fallback) {
			return labels[key] || fallback;
		}

		Array.prototype.forEach.call(buttons, function (btn) {
			var form = btn.closest('form');
			if (!form) {
				return;
			}

			var emailInput = form.querySelector('[data-field="user_email"]');
			var codeInput = form.querySelector('[data-field="echo_auth_code"]');
			var statusEl = form.querySelector('[data-code-status]');
			var idleLabel = btn.textContent;
			var timer = null;

			function setStatus(text, type) {
				if (!statusEl) {
					return;
				}
				statusEl.textContent = text || '';
				statusEl.className = 'echo-auth__code-status' + (type ? ' is-' + type : '');
			}

			function resetBtn() {
				window.clearInterval(timer);
				btn.disabled = false;
				btn.classList.remove('is-loading', 'is-cooling');
				btn.textContent = t('resend', idleLabel);
			}

			function countdown(seconds) {
				var left = seconds;
				window.clearInterval(timer);
				btn.disabled = true;
				btn.classList.remove('is-loading');
				btn.classList.add('is-cooling');
				btn.textContent = t('cooldown', '%d 秒后重发').replace('%d', left);

				timer = window.setInterval(function () {
					left -= 1;
					if (left <= 0) {
						resetBtn();
						return;
					}
					btn.textContent = t('cooldown', '%d 秒后重发').replace('%d', left);
				}, 1000);
			}

			btn.addEventListener('click', function () {
				if (btn.disabled) {
					return;
				}

				var email = emailInput ? emailInput.value.trim() : '';
				if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
					if (emailInput) {
						emailInput.classList.add('is-invalid');
						try {
							emailInput.focus();
						} catch (e) {
							/* 忽略 */
						}
					}
					setStatus(t('needEmail', '请先填写正确的邮箱地址。'), 'error');
					return;
				}
				if (emailInput) {
					emailInput.classList.remove('is-invalid');
				}

				var body = 'action=echo_send_register_code'
					+ '&nonce=' + encodeURIComponent(btn.getAttribute('data-nonce') || cfg.mailNonce || '')
					+ '&email=' + encodeURIComponent(email);

				btn.disabled = true;
				btn.classList.add('is-loading');
				btn.textContent = t('sending', '发送中…');
				setStatus('', '');

				window.fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body
				})
					.then(function (res) {
						return res.json();
					})
					.then(function (res) {
						var data = (res && res.data) || {};
						if (res && res.success) {
							setStatus(data.message || '', 'success');
							if (codeInput) {
								try {
									codeInput.focus();
								} catch (e) {
									/* 忽略 */
								}
							}
							countdown(parseInt(data.cooldown, 10) || 60);
							return;
						}
						setStatus(data.message || t('failed', '发送失败，请稍后重试。'), 'error');
						if (data.cooldown) {
							countdown(parseInt(data.cooldown, 10) || 60);
						} else {
							resetBtn();
						}
					})
					.catch(function () {
						setStatus(t('failed', '发送失败，请稍后重试。'), 'error');
						resetBtn();
					});
			});

			// 只允许输入数字，最多 6 位
			if (codeInput) {
				codeInput.addEventListener('input', function () {
					var clean = codeInput.value.replace(/\D+/g, '').slice(0, 6);
					if (clean !== codeInput.value) {
						codeInput.value = clean;
					}
				});
			}
		});
	}

	/**
	 * Miuix 风格页面切换进度条（仅启用 Miuix 外观时生效）
	 */
	function initPageProgress() {
		if (!document.body || !document.body.classList.contains('echo-style-miuix')) {
			return;
		}

		var bar = null;
		var fallback = null;

		function show() {
			if (bar) {
				return;
			}
			bar = document.createElement('div');
			bar.className = 'miuix-progress';
			bar.setAttribute('aria-hidden', 'true');
			document.body.appendChild(bar);

			window.clearTimeout(fallback);
			fallback = window.setTimeout(hide, 8000);
		}

		function hide() {
			window.clearTimeout(fallback);
			if (!bar) {
				return;
			}
			var el = bar;
			bar = null;
			el.classList.add('is-done');
			window.setTimeout(function () {
				if (el.parentNode) {
					el.parentNode.removeChild(el);
				}
			}, 220);
		}

		document.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
				return;
			}

			var link = e.target.closest ? e.target.closest('a[href]') : null;
			if (!link) {
				return;
			}
			if (link.target && link.target !== '_self') {
				return;
			}
			if (link.hasAttribute('download')) {
				return;
			}

			var href = link.getAttribute('href') || '';
			if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:|data:)/i.test(href)) {
				return;
			}
			if (link.host && link.host !== window.location.host) {
				return;
			}
			if (link.pathname === window.location.pathname && link.search === window.location.search && link.hash) {
				return;
			}

			show();
		});

		document.addEventListener('submit', function (e) {
			if (e.defaultPrevented) {
				return;
			}
			show();
		});

		window.addEventListener('pageshow', hide);
	}
})();
