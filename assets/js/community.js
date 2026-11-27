/* ==========================================================================
   社区互动增强：评论点赞 / 复制链接 / 签到 / 搜索历史
   ========================================================================== */
(function () {
	'use strict';

	var C = window.echoCommunity || {};
	var ajaxUrl = C.ajaxUrl || '';
	var logged = !!C.logged;

	function post(action, nonce, data, done) {
		if (!ajaxUrl) { return; }
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (r) { return r.json(); })
			.then(function (res) { done(res); })
			.catch(function () { done({ success: false }); });
	}

	/* ---------- 1. 评论点赞 ---------- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.echo-cl-like');
		if (!btn) { return; }
		e.preventDefault();
		if (!logged) {
			alert(C.needLogin || '请先登录后再操作');
			return;
		}
		if (btn.disabled) { return; }
		var cid = btn.getAttribute('data-cid');
		btn.disabled = true;
		post('echo_cl_toggle', C.clNonce, { comment_id: cid }, function (res) {
			btn.disabled = false;
			if (!res || !res.success || !res.data) { return; }
			var active = !!res.data.active;
			var count = parseInt(res.data.count, 10) || 0;
			btn.classList.toggle('is-liked', active);
			btn.setAttribute('aria-pressed', active ? 'true' : 'false');
			var counter = btn.querySelector('.echo-cl-like__count');
			if (counter) { counter.textContent = count > 0 ? String(count) : ''; }
		});
	});

	/* ---------- 2. 复制链接 ---------- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.echo-share__btn--copy');
		if (!btn) { return; }
		e.preventDefault();
		var url = btn.getAttribute('data-url') || window.location.href;
		var label = btn.querySelector('span');
		var done = function (ok) {
			if (label) {
				var original = label.textContent;
				label.textContent = ok ? (C.copied || '链接已复制') : (C.copyFail || '复制失败');
				btn.classList.toggle('is-copied', ok);
				setTimeout(function () {
					label.textContent = original;
					btn.classList.remove('is-copied');
				}, 2000);
			}
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(function () { done(true); }, function () { fallbackCopy(url, done); });
		} else {
			fallbackCopy(url, done);
		}
	});

	function fallbackCopy(text, done) {
		try {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			document.execCommand('copy');
			document.body.removeChild(ta);
			done(true);
		} catch (err) {
			done(false);
		}
	}

	/* ---------- 3. 签到 ---------- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.echo-checkin__btn');
		if (!btn || btn.disabled) { return; }
		e.preventDefault();
		if (!logged) {
			alert(C.needLogin || '请先登录后再操作');
			return;
		}
		var box = btn.closest('.echo-checkin');
		btn.disabled = true;
		post('echo_ci_do', C.ciNonce, {}, function (res) {
			btn.disabled = false;
			var msg = box ? box.querySelector('.echo-checkin__msg') : null;
			if (!res || !res.success || !res.data) {
				if (msg) {
					msg.textContent = (res && res.data && res.data.message) || (C.signFail || '签到失败，请重试');
					msg.hidden = false;
				}
				return;
			}
			var d = res.data;
			if (msg) {
				msg.textContent = C.signSuccess || '签到成功！';
				msg.classList.add('is-success');
				msg.hidden = false;
			}
			btn.textContent = '今日已签到';
			btn.classList.add('is-done');
			var stats = box ? box.querySelectorAll('.echo-checkin__stat strong') : [];
			if (stats[0]) { stats[0].textContent = String(d.total); }
			if (stats[1]) { stats[1].textContent = String(d.streak); }
			setTimeout(function () {
				if (msg) { msg.hidden = true; }
			}, 4000);
		});
	});

	/* ---------- 4. 搜索历史 ---------- */
	var searchBox = document.querySelector('.echo-search-enhance[data-history="1"]');
	if (searchBox) {
		var KEY = 'echoSearchHistory';
		var slot = searchBox.querySelector('[data-slot="history"]');
		var block = searchBox.querySelector('.echo-se-history');
		var form = document.querySelector('.echo-header__search form, form[role="search"], .echo-search-form');

		function loadHistory() {
			try {
				var arr = JSON.parse(window.localStorage.getItem(KEY) || '[]');
				return Array.isArray(arr) ? arr : [];
			} catch (e) { return []; }
		}
		function saveHistory(arr) {
			try { window.localStorage.setItem(KEY, JSON.stringify(arr.slice(0, 8))); } catch (e) { /* 忽略 */ }
		}
		function renderHistory() {
			if (!slot) { return; }
			var arr = loadHistory();
			if (!arr.length) {
				if (block) { block.hidden = true; }
				return;
			}
			if (block) { block.hidden = false; }
			slot.innerHTML = '';
			arr.forEach(function (term) {
				var a = document.createElement('a');
				a.className = 'echo-se-tag';
				a.href = window.location.pathname + '?s=' + encodeURIComponent(term);
				a.textContent = term;
				slot.appendChild(a);
			});
			var clear = document.createElement('button');
			clear.type = 'button';
			clear.className = 'echo-se-tag';
			clear.textContent = C.clearHistory || '清空';
			clear.addEventListener('click', function () {
				saveHistory([]);
				renderHistory();
			});
			slot.appendChild(clear);
		}
		renderHistory();

		if (form) {
			form.addEventListener('submit', function () {
				var input = form.querySelector('input[name="s"]');
				if (!input) { return; }
				var term = (input.value || '').trim();
				if (!term) { return; }
				var arr = loadHistory().filter(function (t) { return t !== term; });
				arr.unshift(term);
				saveHistory(arr);
			});
		}
	}
})();
