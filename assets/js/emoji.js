/**
 * Echo 表情选择器
 * 依赖全局 echoEmoji：{ baseUrl, data: [[code, name, file], ...], title, search, empty }
 *
 * 支持插入目标：
 *  - 评论框 #comment（textarea）
 *  - 前台写文章：可视化 TinyMCE #echo_fp_content、Markdown #echo_fp_markdown、HTML #echo_fp_html
 *    按当前激活的编辑面板自动选择插入位置。
 */
(function () {
    'use strict';

    var cfg = window.echoEmoji;
    if (!cfg || !Array.isArray(cfg.data) || !cfg.data.length) {
        return;
    }

    var pop = null;
    var grid = null;
    var searchInput = null;
    var activeButton = null;
    var activeTarget = null;

    /* ---------- DOM 工具 ---------- */
    function createPop() {
        if (pop) {
            return;
        }
        pop = document.createElement('div');
        pop.className = 'echo-emoji-pop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', cfg.title || '表情');

        var head = document.createElement('div');
        head.className = 'echo-emoji-pop__head';
        searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'echo-emoji-pop__search';
        searchInput.placeholder = cfg.search || '搜索表情…';
        searchInput.setAttribute('aria-label', cfg.search || '搜索表情');
        head.appendChild(searchInput);
        pop.appendChild(head);

        grid = document.createElement('div');
        grid.className = 'echo-emoji-pop__grid';
        pop.appendChild(grid);

        document.body.appendChild(pop);

        searchInput.addEventListener('input', function () {
            renderGrid(searchInput.value.trim());
        });

        // 点击面板外部关闭
        document.addEventListener('click', function (e) {
            if (pop.classList.contains('is-open') && !pop.contains(e.target) && !e.target.closest('.echo-emoji-btn')) {
                closePop();
            }
        });
        // Esc 关闭
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && pop.classList.contains('is-open')) {
                closePop();
                if (activeButton) {
                    activeButton.focus();
                }
            }
        });
    }

    function renderGrid(query) {
        if (!grid) {
            return;
        }
        grid.innerHTML = '';
        var q = (query || '').toLowerCase();
        var frag = document.createDocumentFragment();
        var count = 0;
        cfg.data.forEach(function (row) {
            var code = row[0];
            var name = row[1];
            var file = row[2];
            if (q && String(name).toLowerCase().indexOf(q) === -1 && String(code).toLowerCase().indexOf(q) === -1) {
                return;
            }
            count += 1;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'echo-emoji-pop__item';
            btn.title = String(name);
            btn.setAttribute('aria-label', String(name));
            var img = document.createElement('img');
            img.src = cfg.baseUrl + file;
            img.alt = String(name);
            img.loading = 'lazy';
            btn.appendChild(img);
            btn.addEventListener('click', function () {
                insertEmoji(code, name);
            });
            frag.appendChild(btn);
        });
        if (!count) {
            var empty = document.createElement('div');
            empty.className = 'echo-emoji-pop__empty';
            empty.textContent = cfg.empty || '没有匹配的表情';
            frag.appendChild(empty);
        }
        grid.appendChild(frag);
    }

    function positionPop(button) {
        var rect = button.getBoundingClientRect();
        var left = rect.left + window.scrollX;
        var top = rect.bottom + window.scrollY + 6;
        var width = 320;
        var vw = document.documentElement.clientWidth;
        if (left + width > vw - 12) {
            left = Math.max(12, vw - width - 12);
        }
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function openPop(button, target) {
        createPop();
        activeButton = button;
        activeTarget = target;
        renderGrid('');
        searchInput.value = '';
        positionPop(button);
        pop.classList.add('is-open');
        button.classList.add('is-active');
        // 延迟聚焦，避免点击按钮时面板被外部点击逻辑立即关闭
        setTimeout(function () { searchInput.focus(); }, 0);
    }

    function closePop() {
        if (!pop) {
            return;
        }
        pop.classList.remove('is-open');
        if (activeButton) {
            activeButton.classList.remove('is-active');
        }
        activeButton = null;
        activeTarget = null;
    }

    /* ---------- 插入逻辑 ---------- */
    function insertTextarea($textarea, code) {
        var start = $textarea.selectionStart;
        var end = $textarea.selectionEnd;
        var value = $textarea.value;
        $textarea.value = value.slice(0, start) + code + value.slice(end);
        var pos = start + code.length;
        $textarea.setSelectionRange(pos, pos);
        $textarea.focus();
        // 触发 input/change，让主题预览逻辑感知
        $textarea.dispatchEvent(new Event('input', { bubbles: true }));
        $textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function getActivePostTarget() {
        // 写文章页：按当前显示的面板决定插入目标
        var mode = (document.getElementById('echo_fp_mode') || {}).value || 'visual';
        if (mode === 'markdown') {
            return document.getElementById('echo_fp_markdown');
        }
        if (mode === 'html') {
            return document.getElementById('echo_fp_html');
        }
        // visual：优先 TinyMCE 编辑器 iframe；未加载则退回隐藏 textarea
        if (window.tinymce && window.tinymce.get && window.tinymce.get('echo_fp_content')) {
            return { tinymce: window.tinymce.get('echo_fp_content') };
        }
        return document.getElementById('echo_fp_content');
    }

    function insertEmoji(code, name) {
        var target = null;
        if (activeTarget === 'comment') {
            target = document.getElementById('comment');
        } else if (activeTarget === 'post') {
            target = getActivePostTarget();
        }

        var done = false;
        if (target && target.tinymce) {
            target.tinymce.execCommand('mceInsertContent', false, code);
            target.tinymce.focus();
            done = true;
        } else if (target && typeof target.value === 'string' && typeof target.setSelectionRange === 'function') {
            insertTextarea(target, code);
            done = true;
        }

        if (done) {
            closePop();
        }
    }

    /* ---------- 绑定按钮 ---------- */
    function bindButtons() {
        var buttons = document.querySelectorAll('.echo-emoji-btn[data-echo-emoji-target]');
        buttons.forEach(function (button) {
            if (button.dataset.echoEmojiBound === '1') {
                return;
            }
            button.dataset.echoEmojiBound = '1';
            button.addEventListener('click', function (e) {
                e.preventDefault();
                var target = button.getAttribute('data-echo-emoji-target');
                if (activeButton === button && pop && pop.classList.contains('is-open')) {
                    closePop();
                    return;
                }
                openPop(button, target);
            });
        });
    }

    function init() {
        bindButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // TinyMCE 可能在 DOMContentLoaded 之后初始化，模式切换时重新扫描按钮即可（按钮本身静态，无需重绑）
    window.EchoEmoji = {
        open: function (target) {
            var button = document.querySelector('.echo-emoji-btn[data-echo-emoji-target="' + target + '"]');
            if (button) {
                openPop(button, target);
            }
        },
        close: closePop
    };
})();
