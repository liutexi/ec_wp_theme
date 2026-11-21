/**
 * 个人主页导航编辑器交互：拖拽排序 / 删除 / 添加自定义项
 * 依赖 DOM 结构见 echo_portal_nav_editor()
 */
(function () {
    'use strict';

    var ICONS = {
        home: '主页', archive: '归档', search: '搜索', link: '链接', pen: '写作', user: '用户',
        login: '登录', moon: '月亮', sun: '太阳', calendar: '日历', clock: '时钟', comment: '评论',
        tag: '标签', bookmark: '书签', mail: '邮件', globe: '地球'
    };
    // 简易 SVG 图标（与 PHP echo_user_icon 视觉一致）
    var ICON_PATHS = {
        home: '<path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
        archive: '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><line x1="10" y1="12" x2="14" y2="12"/>',
        search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        login: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        sun: '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.2" y1="4.2" x2="5.6" y2="5.6"/><line x1="18.4" y1="18.4" x2="19.8" y2="19.8"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.2" y1="19.8" x2="5.6" y2="18.4"/><line x1="18.4" y1="5.6" x2="19.8" y2="4.2"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        comment: '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"/>',
        tag: '<path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V4a2 2 0 0 1 2-2h8l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        bookmark: '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
        mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,6 12,13 22,6"/>',
        globe: '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>'
    };

    function iconSvg(name) {
        var d = ICON_PATHS[name] || ICON_PATHS.link;
        return '<svg class="echo-up-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
    }

    function iconOptions(selected) {
        var html = '<option value="">默认图标</option>';
        Object.keys(ICONS).forEach(function (k) {
            html += '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + ICONS[k] + '</option>';
        });
        return html;
    }

    // 重排 input name 索引，保持与 PHP 数组解析一致
    function reindex(editor) {
        var list = editor.querySelector('[data-echo-nav-list]');
        if (!list) return;
        Array.prototype.forEach.call(list.querySelectorAll('[data-echo-nav-row]'), function (row, i) {
            Array.prototype.forEach.call(row.querySelectorAll('input[name], select[name]'), function (field) {
                field.name = field.name.replace(/\[\d+\]/, '[' + i + ']');
            });
        });
    }

    function bindRow(editor, row) {
        var del = row.querySelector('[data-echo-nav-del]');
        if (del) {
            del.addEventListener('click', function () {
                row.remove();
                reindex(editor);
            });
        }
        // 图标 select 变更时更新左侧图标预览
        var sel = row.querySelector('.echo-nav-edit__icon-select');
        var ico = row.querySelector('.echo-nav-edit__ico');
        if (sel && ico) {
            sel.addEventListener('change', function () {
                ico.innerHTML = sel.value ? iconSvg(sel.value) : '';
            });
        }
        // 启用复选框切换 is-disabled
        var on = row.querySelector('.echo-nav-edit__on input');
        if (on) {
            on.addEventListener('change', function () {
                row.classList.toggle('is-disabled', !on.checked);
            });
        }
        // 拖拽排序
        row.draggable = true;
        row.addEventListener('dragstart', function () {
            row.classList.add('is-dragging');
        });
        row.addEventListener('dragend', function () {
            row.classList.remove('is-dragging');
            Array.prototype.forEach.call(editor.querySelectorAll('.drag-over'), function (el) { el.classList.remove('drag-over'); });
            reindex(editor);
        });
        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            row.classList.add('drag-over');
        });
        row.addEventListener('dragleave', function () {
            row.classList.remove('drag-over');
        });
        row.addEventListener('drop', function (e) {
            e.preventDefault();
            row.classList.remove('drag-over');
            var dragging = editor.querySelector('.is-dragging');
            if (dragging && dragging !== row) {
                var list = row.parentNode;
                list.insertBefore(dragging, row);
                reindex(editor);
            }
        });
    }

    function addRow(editor) {
        var list = editor.querySelector('[data-echo-nav-list]');
        if (!list) return;
        var i = list.querySelectorAll('[data-echo-nav-row]').length;
        var row = document.createElement('li');
        row.className = 'echo-nav-edit__row';
        row.setAttribute('data-echo-nav-row', '');
        row.innerHTML =
            '<span class="echo-nav-edit__grip" aria-hidden="true">⋮⋮</span>' +
            '<label class="echo-nav-edit__on"><input type="hidden" name="echo_portal_nav_items[' + i + '][enabled]" value="0" /><input type="checkbox" name="echo_portal_nav_items[' + i + '][enabled]" value="1" checked /></label>' +
            '<span class="echo-nav-edit__ico">' + iconSvg('link') + '</span>' +
            '<div class="echo-nav-edit__fields">' +
                '<input class="echo-nav-edit__label" type="text" name="echo_portal_nav_items[' + i + '][label]" value="" placeholder="名称" maxlength="24" />' +
                '<input class="echo-nav-edit__url" type="text" name="echo_portal_nav_items[' + i + '][url]" value="" placeholder="链接地址" />' +
            '</div>' +
            '<select class="echo-nav-edit__icon-select" name="echo_portal_nav_items[' + i + '][icon]">' + iconOptions('link') + '</select>' +
            '<label class="echo-nav-edit__newtab"><input type="checkbox" name="echo_portal_nav_items[' + i + '][newtab]" value="1" /> 新窗口</label>' +
            '<button type="button" class="echo-nav-edit__del" data-echo-nav-del>×</button>' +
            '<input type="hidden" name="echo_portal_nav_items[' + i + '][type]" value="custom" />' +
            '<input type="hidden" name="echo_portal_nav_items[' + i + '][key]" value="custom" />';
        list.appendChild(row);
        bindRow(editor, row);
        row.querySelector('.echo-nav-edit__label').focus();
    }

    function init() {
        var editors = document.querySelectorAll('[data-echo-nav-editor]');
        Array.prototype.forEach.call(editors, function (editor) {
            Array.prototype.forEach.call(editor.querySelectorAll('[data-echo-nav-row]'), function (row) {
                bindRow(editor, row);
            });
            var add = editor.querySelector('[data-echo-nav-add]');
            if (add) {
                add.addEventListener('click', function () { addRow(editor); });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
