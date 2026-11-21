/* ============================================================
 * Echo 阅读互动：点赞 / 收藏 AJAX 交互
 * 依赖全局 echoEng（ajaxUrl / nonce / logged / 文案）
 * ============================================================ */
(function () {
    'use strict';

    var cfg = window.echoEng || {};

    function request(type, postId) {
        if (!cfg.ajaxUrl) {
            return Promise.reject(new Error('no ajax url'));
        }
        var body = new URLSearchParams();
        body.append('action', 'echo_eng_toggle');
        body.append('nonce', cfg.nonce || '');
        body.append('type', type);
        body.append('post_id', postId);
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (res) {
            if (res && res.success && res.data) {
                return res.data;
            }
            var msg = res && res.data && res.data.message ? res.data.message : cfg.failedText;
            throw new Error(msg || 'failed');
        });
    }

    function requireLogin(btn) {
        var url = btn.getAttribute('data-login-url') || '';
        if (cfg.needLogin) {
            window.alert(cfg.needLogin);
        }
        if (url) {
            window.location.href = url;
        }
    }

    /* ---------- 文章互动条 ---------- */
    function initBar() {
        var buttons = document.querySelectorAll('[data-eng-type]');
        if (!buttons.length) {
            return;
        }
        Array.prototype.forEach.call(buttons, function (btn) {
            btn.addEventListener('click', function () {
                if (!cfg.logged) {
                    requireLogin(btn);
                    return;
                }
                if (btn.classList.contains('is-busy')) {
                    return;
                }
                var type = btn.getAttribute('data-eng-type');
                var postId = btn.getAttribute('data-post-id') ||
                    (btn.closest('[data-echo-eng]') && btn.closest('[data-echo-eng]').getAttribute('data-post-id'));
                if (!postId) {
                    return;
                }
                btn.classList.add('is-busy');
                request(type, postId).then(function (data) {
                    var active = !!data.active;
                    var count = typeof data.count === 'number' ? data.count : null;
                    btn.setAttribute('data-eng-active', active ? '1' : '0');
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    btn.classList.toggle('is-active', active);

                    var label = btn.querySelector('.echo-eng-btn__label');
                    var counter = btn.querySelector('.echo-eng-btn__count');
                    if (type === 'like') {
                        if (label) { label.textContent = active ? cfg.likedText : cfg.likeText; }
                    } else {
                        if (label) { label.textContent = active ? cfg.favedText : cfg.favText; }
                    }
                    if (counter && count !== null) {
                        counter.textContent = count;
                    }
                    btn.setAttribute('data-eng-count', count !== null ? count : '0');
                }).catch(function (err) {
                    if (err && err.message) {
                        window.alert(err.message);
                    }
                }).finally(function () {
                    btn.classList.remove('is-busy');
                });
            });
        });
    }

    /* ---------- 个人中心：取消收藏 ---------- */
    function initFavRemove() {
        var removes = document.querySelectorAll('[data-eng-remove-fav]');
        if (!removes.length) {
            return;
        }
        Array.prototype.forEach.call(removes, function (btn) {
            btn.addEventListener('click', function () {
                if (!cfg.logged) {
                    requireLogin(btn);
                    return;
                }
                if (btn.classList.contains('is-busy')) {
                    return;
                }
                var postId = btn.getAttribute('data-post-id');
                if (!postId) {
                    return;
                }
                btn.classList.add('is-busy');
                request('favorite', postId).then(function (data) {
                    // 返回 active=false 表示已取消收藏，移除该行
                    if (!data.active) {
                        var row = btn.closest('.echo-my-favorites__item');
                        if (row) { row.remove(); }
                    }
                }).catch(function (err) {
                    if (err && err.message) {
                        window.alert(err.message);
                    }
                    btn.classList.remove('is-busy');
                });
            });
        });
    }

    function init() {
        initBar();
        initFavRemove();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
