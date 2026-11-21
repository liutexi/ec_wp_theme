/**
 * Echo 外链跳转提醒
 *
 * 1) 全站拦截外部链接：点击时向服务端申请 token，跳转中转页。
 * 2) 中转页：按配置执行倒计时自动跳转 / 手动点击跳转。
 */
(function () {
	'use strict';

	var cfg = window.echoLgo || {};

	/**
	 * 判断链接是否应被拦截
	 */
	function shouldIntercept(a) {
		if (!a || !a.href) { return false; }
		if (a.classList && a.classList.contains('echo-lgo-skip')) { return false; }
		if (a.hasAttribute('download')) { return false; }
		var url;
		try { url = new URL(a.href, window.location.href); } catch (e) { return false; }
		if (url.protocol !== 'http:' && url.protocol !== 'https:') { return false; }
		if (url.hostname === window.location.hostname) { return false; }
		if (url.hostname === 'www.' + window.location.hostname) { return false; }
		// 白名单
		var list = cfg.whitelist || [];
		for (var i = 0; i < list.length; i++) {
			var d = String(list[i]).toLowerCase();
			if (url.hostname === d || url.hostname === 'www.' + d ||
				(d.indexOf('*.') === 0 && url.hostname.slice(-(d.length - 2)) === d.slice(1))) {
				return false;
			}
		}
		return true;
	}

	/**
	 * 申请 token 并跳转中转页
	 */
	function goThrough(target) {
		if (!cfg.ajaxUrl || !cfg.nonce) {
			window.location.href = target;
			return;
		}
		var xhr = new XMLHttpRequest();
		xhr.open('POST', cfg.ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4) { return; }
			var ok = false;
			var dest = target;
			try {
				var res = JSON.parse(xhr.responseText);
				if (res && res.success && res.data && res.data.url) {
					dest = res.data.url;
					ok = true;
				}
			} catch (e) { /* 失败则直连 */ }
			window.location.href = dest;
		};
		xhr.send('action=echo_lgo_token&nonce=' + encodeURIComponent(cfg.nonce) + '&target=' + encodeURIComponent(target));
	}

	/**
	 * 全站外链拦截
	 */
	function initInterceptor() {
		if (!cfg.enabled) { return; }
		if (document.body && document.body.classList.contains('echo-lgo-page')) { return; }
		document.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) { return; }
			var a = e.target.closest ? e.target.closest('a[href]') : null;
			if (!shouldIntercept(a)) { return; }
			e.preventDefault();
			goThrough(a.href);
		}, false);
	}

	/**
	 * 中转页跳转执行
	 */
	function initGoPage() {
		var card = document.querySelector('.echo-lgo-card[data-target]');
		if (!card) { return; }
		var target = card.getAttribute('data-target');
		var newTab = card.getAttribute('data-newtab') === '1';
		var mode = card.getAttribute('data-mode');
		var btn = document.getElementById('echo-lgo-go');
		var btnText = document.getElementById('echo-lgo-go-text');
		var countEl = document.getElementById('echo-lgo-count');
		var countDesc = card.querySelector('.echo-lgo-card__count');
		var fired = false;

		function perform() {
			if (fired) { return; }
			fired = true;
			if (newTab) {
				window.open(target, '_blank', 'noopener,noreferrer');
			} else {
				window.location.href = target;
			}
		}

		if (btn) { btn.addEventListener('click', perform); }

		if (mode === 'auto') {
			var left = parseInt(card.getAttribute('data-delay'), 10) || 5;
			if (countDesc) {
				countDesc.textContent = (cfg.autoText || '秒后自动跳转…').replace('%d', left);
			}
			var timer = setInterval(function () {
				left -= 1;
				if (countEl) { countEl.textContent = '(' + Math.max(0, left) + ')'; }
				if (countDesc) {
					countDesc.textContent = (cfg.autoText || '秒后自动跳转…').replace('%d', Math.max(0, left));
				}
				if (left <= 0) { clearInterval(timer); perform(); }
			}, 1000);
		}
	}

	function init() {
		if (document.body && document.body.classList.contains('echo-lgo-page')) {
			initGoPage();
			return;
		}
		initInterceptor();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
