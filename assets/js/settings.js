/**
 * Echo 设置页脚本：取色器 + Logo 上传 + 即时保存 + 右下角通知
 */
(function ($) {
	'use strict';

	$(function () {
		// 左侧分类导航（取色器按面板懒初始化，避免在隐藏容器里算错尺寸）
		initSettingsNav();

		initColorPresets();
		initWidthSlider();
		initLogoUploader();
		initInstantSave();
		initSwitchState();
		initCategoryManager();
		initLinksManager();
		initPostManager();
		initBackupManager();
		initMailTools();
		initToast();
	});

	/**
	 * 主题色预设色块：点击填充取色器
	 */
	/**
	 * 设置页左侧分类导航
	 */
	function initSettingsNav() {
		var $nav = $('.echo-settings-nav');
		var $panels = $('.echo-settings-panel');

		if (!$nav.length || !$panels.length) {
			initColorPickers(document);
			return;
		}

		var $items = $nav.find('.echo-settings-nav__item');

		function activate(id, persist) {
			if (!id) {
				return;
			}

			$items.removeClass('is-active').filter('[data-target="' + id + '"]').addClass('is-active');
			$panels.removeClass('is-active').filter('[data-panel="' + id + '"]').addClass('is-active');
			initColorPickers($panels.filter('[data-panel="' + id + '"]'));

			if (persist) {
				try {
					window.localStorage.setItem('echoSettingsPanel', id);
				} catch (e) {
					/* 忽略隐私模式限制 */
				}
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', '#' + id);
				}
			}
		}

		$nav.on('click', '.echo-settings-nav__item', function () {
			activate($(this).attr('data-target'), true);
		});

		var saved = '';
		var hash = (window.location.hash || '').replace('#', '');
		try {
			saved = window.localStorage.getItem('echoSettingsPanel') || '';
		} catch (e) {
			saved = '';
		}

		var preset = $('.echo-settings-shell').attr('data-current') || '';
		var initial = '';

		if (preset && $items.filter('[data-target="' + preset + '"]').length) {
			initial = preset;
			// 从菜单子项进入时记住，保存设置跳回后仍停在原分类
			try {
				window.localStorage.setItem('echoSettingsPanel', preset);
			} catch (e) {
				/* 忽略 */
			}
		} else if (hash && $items.filter('[data-target="' + hash + '"]').length) {
			initial = hash;
		} else if (saved && $items.filter('[data-target="' + saved + '"]').length) {
			initial = saved;
		} else {
			initial = $items.first().attr('data-target');
		}

		activate(initial, false);
	}

	/**
	 * 按面板范围初始化取色器（同一元素只初始化一次）
	 */
	function initColorPickers(scope) {
		$(scope).find('.echo-color-field').each(function () {
			var $el = $(this);
			if ($el.data('echoPickerReady')) {
				return;
			}
			$el.data('echoPickerReady', true);
			if ($.fn.wpColorPicker) {
				$el.wpColorPicker();
			}
		});
	}

	function initColorPresets() {
		var $presets = $('.echo-color-preset');
		var $field = $('.echo-color-field');

		if (!$presets.length || !$field.length) {
			return;
		}

		$presets.on('click', function () {
			var color = $(this).data('color');

			// 更新 wpColorPicker 的值（触发 change 让 iris 同步）
			$field.val(color).trigger('change');

			// 更新取色器色块显示
			var $wrap = $field.closest('.wp-picker-container');
			if ($wrap.length) {
				$wrap.find('.wp-color-result').css('background-color', color);
			}

			$presets.removeClass('is-active');
			$(this).addClass('is-active');
		});

		// 手动改色时，清除预设高亮
		$field.on('change', function () {
			var val = String($field.val()).toLowerCase();
			$presets.removeClass('is-active');
			$presets.filter(function () {
				return String($(this).data('color')).toLowerCase() === val;
			}).addClass('is-active');
		});
	}

	/**
	 * 内容宽度滑块：与数值显示、隐藏字段联动
	 */
	function initWidthSlider() {
		var $range = $('#echo_content_width_range');
		var $display = $('#echo_content_width_display');
		var $hidden = $('#echo_content_width');

		if (!$range.length) {
			return;
		}

		$range.on('input', function () {
			var val = $range.val();
			$display.text(val);
			$hidden.val(val);
		});
	}

	/**
	 * 开关状态文字：切换时显示「已开启/已关闭」
	 */
	function initSwitchState() {
		$('.echo-switch-label input[type="checkbox"]').on('change', function () {
			var $state = $(this).siblings('.echo-switch-state');
			$state.text($(this).is(':checked') ? '已开启' : '已关闭');
		});
	}

	/**
	 * 分类管理：新建 / 编辑 / 删除 / 设为默认（全部 AJAX）
	 */
	/**
	 * 友情链接管理：增删、上下移动、整表保存
	 */
	function initLinksManager() {
		var $wrap = $('#echo-links-manager');
		if (!$wrap.length) {
			return;
		}

		var $rows = $('#echo-link-rows');
		var rowHtml = $('#echo-link-row-template').html() || '';

		function ensureNotEmpty() {
			if (!$rows.find('tr').length) {
				$rows.append('<tr class="echo-link-empty"><td colspan="6">还没有友情链接，点下方「添加一条」开始。</td></tr>');
			}
		}

		$wrap.on('click', '#echo-link-add', function () {
			$rows.find('.echo-link-empty').remove();
			$rows.append(rowHtml);
			$rows.find('tr.echo-link-row').last().find('.echo-link-name').trigger('focus');
		});

		$wrap.on('click', '.echo-link-remove', function () {
			$(this).closest('tr').remove();
			ensureNotEmpty();
		});

		$wrap.on('click', '.echo-link-up', function () {
			var $tr = $(this).closest('tr');
			$tr.prev('tr').before($tr);
		});

		$wrap.on('click', '.echo-link-down', function () {
			var $tr = $(this).closest('tr');
			$tr.next('tr').after($tr);
		});

		// 拖拽排序
		var dragRow = null;

		$wrap.on('dragstart', 'tr.echo-link-row', function (e) {
			dragRow = $(this).addClass('is-dragging');
			if (e.originalEvent && e.originalEvent.dataTransfer) {
				e.originalEvent.dataTransfer.effectAllowed = 'move';
				try {
					e.originalEvent.dataTransfer.setData('text/plain', 'echo-link-row');
				} catch (err) {
					/* IE 兼容 */
				}
			}
		});

		$wrap.on('dragover', 'tr.echo-link-row', function (e) {
			if (!dragRow) {
				return;
			}
			e.preventDefault();
			var $target = $(this);
			if ($target.is(dragRow)) {
				return;
			}
			var rect = this.getBoundingClientRect();
			var after = (e.originalEvent.clientY - rect.top) > rect.height / 2;
			if (after) {
				$target.after(dragRow);
			} else {
				$target.before(dragRow);
			}
		});

		$wrap.on('dragend', 'tr.echo-link-row', function () {
			$(this).removeClass('is-dragging');
			dragRow = null;
		});

		// 从媒体库选择图标
		$wrap.on('click', '.echo-link-logo-pick', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $input = $btn.closest('.echo-link-logo-row').find('.echo-link-logo');

			if (!window.wp || !wp.media) {
				$input.trigger('focus');
				return;
			}

			var frame = wp.media({
				title: '选择图标',
				button: { text: '使用这张图片' },
				multiple: false,
				library: { type: 'image' }
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
				$input.val(url).trigger('change');
			});

			frame.open();
		});

		// 批量导入
		$wrap.on('click', '#echo-link-import', function () {
			var text = $('#echo-link-bulk-text').val() || '';
			var added = 0;

			text.split(/\r?\n/).forEach(function (line) {
				line = line.trim();
				if (!line) {
					return;
				}

				var parts = line.split('|').map(function (part) {
					return part.trim();
				});
				var url = parts[1] || '';

				// 兼容整行只填网址
				if (!/^https?:\/\//i.test(url) && /^https?:\/\//i.test(parts[0] || '')) {
					url = parts[0];
					parts = [''];
				}

				if (!/^https?:\/\//i.test(url)) {
					return;
				}

				$rows.find('.echo-link-empty').remove();
				$rows.append(rowHtml);

				var $tr = $rows.find('tr.echo-link-row').last();
				$tr.find('.echo-link-name').val(parts[0] || '');
				$tr.find('.echo-link-url').val(url);
				$tr.find('.echo-link-desc').val(parts[2] || '');
				$tr.find('.echo-link-group').val(parts[3] || '');
				$tr.find('.echo-link-logo').val(parts[4] || '');
				added++;
			});

			if (added) {
				showToast('已导入 ' + added + ' 条，记得点「保存友情链接」', 'success');
			} else {
				showToast('没有识别到有效网址', 'error');
			}
		});

		// 导出当前列表
		$wrap.on('click', '#echo-link-export', function () {
			var lines = [];

			$rows.find('tr.echo-link-row').each(function () {
				var $tr = $(this);
				lines.push([
					$tr.find('.echo-link-name').val() || '',
					$tr.find('.echo-link-url').val() || '',
					$tr.find('.echo-link-desc').val() || '',
					$tr.find('.echo-link-group').val() || '',
					$tr.find('.echo-link-logo').val() || ''
				].join(' | '));
			});

			$('#echo-link-bulk-text').val(lines.join('\n')).trigger('focus').trigger('select');
			showToast('已导出 ' + lines.length + ' 条到文本框，可直接复制', 'success');
		});

		$wrap.on('click', '#echo-link-save', function () {
			var links = [];
			$rows.find('tr.echo-link-row').each(function () {
				var $tr = $(this);
				links.push({
					name: $tr.find('.echo-link-name').val(),
					url: $tr.find('.echo-link-url').val(),
					desc: $tr.find('.echo-link-desc').val(),
					group: $tr.find('.echo-link-group').val(),
					logo: $tr.find('.echo-link-logo').val(),
					enabled: $tr.find('.echo-link-enabled').is(':checked')
				});
			});

			var $btn = $(this).prop('disabled', true);
			var $data = $('#echo-ajax-data');

			$.post($data.data('ajax-url'), {
				action: 'echo_save_links',
				nonce: $data.data('nonce'),
				links: JSON.stringify(links)
			})
				.done(function (res) {
					if (res && res.success) {
						showToast(res.data.message, 'success');
					} else {
						showToast((res && res.data && res.data.message) || '保存失败', 'error');
					}
				})
				.fail(function () {
					showToast('保存失败，请重试', 'error');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	}

	function initCategoryManager() {
		var $root = $('#echo-cat-manager');
		if (!$root.length) { return; }

		var $rows = $('#echo-cat-rows');
		var $data = $('#echo-ajax-data');
		var url = $data.data('ajax-url');
		var nonce = $data.data('nonce');

		function request(payload) {
			payload.action = 'echo_manage_category';
			payload.nonce = nonce;

			$.ajax({
				url: url,
				type: 'POST',
				dataType: 'json',
				data: payload,
				success: function (res) {
					if (res && res.success) {
						$rows.html(res.data.rows);
						$root.find('.echo-cat-add-parent').html(res.data.options);
						if (payload.op === 'create') {
							$root.find('.echo-cat-add-form')[0].reset();
						}
						showToast(res.data.message, 'success');
					} else {
						showToast((res && res.data && res.data.message) || '操作失败', 'error');
					}
				},
				error: function (xhr) {
					var msg = '操作失败，请重试';
					try {
						var r = JSON.parse(xhr.responseText);
						if (r && r.data && r.data.message) { msg = r.data.message; }
					} catch (e) {}
					showToast(msg, 'error');
				}
			});
		}

		// 新增
		$root.on('submit', '.echo-cat-add-form', function (e) {
			e.preventDefault();
			request({
				op: 'create',
				name: $root.find('.echo-cat-add-name').val(),
				slug: $root.find('.echo-cat-add-slug').val(),
				parent: $root.find('.echo-cat-add-parent').val(),
				description: $root.find('.echo-cat-add-desc').val()
			});
		});

		// 展开编辑行
		$root.on('click', '.echo-cat-edit', function () {
			var id = $(this).closest('.echo-cat-row').data('id');
			$rows.find('.echo-cat-edit-row').hide();
			$rows.find('.echo-cat-edit-row[data-id="' + id + '"]').show();
		});

		// 取消编辑
		$root.on('click', '.echo-cat-cancel', function () {
			$(this).closest('.echo-cat-edit-row').hide();
		});

		// 保存修改
		$root.on('click', '.echo-cat-save', function () {
			var $edit = $(this).closest('.echo-cat-edit-row');
			request({
				op: 'update',
				id: $edit.data('id'),
				name: $edit.find('.echo-cat-f-name').val(),
				slug: $edit.find('.echo-cat-f-slug').val(),
				parent: $edit.find('.echo-cat-f-parent').val(),
				description: $edit.find('.echo-cat-f-desc').val()
			});
		});

		// 删除
		$root.on('click', '.echo-cat-del', function () {
			var $row = $(this).closest('.echo-cat-row');
			var name = $.trim($row.find('.echo-cat-name').text());
			if (!window.confirm('确定删除分类「' + name + '」？\n该分类下的文章不会被删除，将移动到默认分类。')) {
				return;
			}
			request({ op: 'delete', id: $row.data('id') });
		});

		// 设为默认
		$root.on('click', '.echo-cat-set-default', function () {
			request({ op: 'set_default', id: $(this).closest('.echo-cat-row').data('id') });
		});
	}

	/**
	 * 文章管理：新建 / 删除 / 批量改分类（全部 AJAX）
	 */
	function initPostManager() {
		var $root = $('#echo-post-manager');
		if (!$root.length) { return; }

		var $rows = $('#echo-post-rows');
		var $data = $('#echo-ajax-data');
		var url = $data.data('ajax-url');
		var nonce = $data.data('nonce');

		function request(payload) {
			payload.action = 'echo_manage_post';
			payload.nonce = nonce;

			$.ajax({
				url: url,
				type: 'POST',
				dataType: 'json',
				data: payload,
				success: function (res) {
					if (res && res.success) {
						$rows.html(res.data.rows);
						$root.find('.echo-post-check-all').prop('checked', false);
						if (payload.op === 'create') {
							$root.find('.echo-post-add-form')[0].reset();
						}
						showToast(res.data.message, 'success');
					} else {
						showToast((res && res.data && res.data.message) || '操作失败', 'error');
					}
				},
				error: function (xhr) {
					var msg = '操作失败，请重试';
					try {
						var r = JSON.parse(xhr.responseText);
						if (r && r.data && r.data.message) { msg = r.data.message; }
					} catch (e) {}
					showToast(msg, 'error');
				}
			});
		}

		// 全选
		$root.on('change', '.echo-post-check-all', function () {
			$root.find('.echo-post-check').prop('checked', $(this).is(':checked'));
		});

		// 新建
		$root.on('submit', '.echo-post-add-form', function (e) {
			e.preventDefault();
			request({
				op: 'create',
				title: $root.find('.echo-post-add-title').val(),
				content: $root.find('.echo-post-add-content').val(),
				status: $root.find('.echo-post-add-status').val(),
				cat: $root.find('.echo-post-add-cat').val()
			});
		});

		// 删除
		$root.on('click', '.echo-post-del', function () {
			var $row = $(this).closest('.echo-post-row');
			var title = $.trim($row.find('.echo-post-title').text());
			if (!window.confirm('确定删除文章「' + title + '」？\n此操作不可撤销。')) {
				return;
			}
			request({ op: 'delete', id: $row.data('id') });
		});

		// 批量改分类
		$root.on('click', '.echo-post-bulk-apply', function () {
			var ids = $root.find('.echo-post-check:checked').map(function () {
				return $(this).val();
			}).get();
			var cat = $root.find('.echo-post-bulk-cat').val();

			if (!ids.length) {
				showToast('请先勾选文章', 'error');
				return;
			}
			if (!cat || cat === '0') {
				showToast('请选择目标分类', 'error');
				return;
			}

			request({ op: 'bulk_category', ids: ids, cat: cat });
		});
	}

	/**
	 * 配置备份 / 导出 / 导入
	 */
	function initBackupManager() {
		var $root = $('#echo-backup-manager');
		if (!$root.length) { return; }

		var $data = $('#echo-ajax-data');
		var url = $data.data('ajax-url');
		var nonce = $data.data('nonce');
		var $rows = $('#echo-backup-rows');

		function request(payload, opts) {
			opts = opts || {};
			var ajaxOpts = {
				url: url,
				type: 'POST',
				dataType: 'json',
				data: payload,
				success: function (res) {
					if (res && res.success) {
						if (res.data.rows) { $rows.html(res.data.rows); }
						if (res.data.next !== undefined) {
							$root.find('.echo-backup-next').text(
								res.data.next ? ('下次自动备份：' + res.data.next) : '定时备份未启用。WP-Cron 依赖站点访问触发，低频站点可能延迟。'
							);
						}
						if (opts.onSuccess) { opts.onSuccess(res.data); }
						if (res.data.message) { showToast(res.data.message, 'success'); }
					} else {
						showToast((res && res.data && res.data.message) || '操作失败', 'error');
					}
				},
				error: function (xhr) {
					var msg = '操作失败，请重试';
					try {
						var r = JSON.parse(xhr.responseText);
						if (r && r.data && r.data.message) { msg = r.data.message; }
					} catch (e) {}
					showToast(msg, 'error');
				}
			};

			payload.action = 'echo_backup_action';
			payload.nonce = nonce;

			if (opts.formData) {
				var fd = new FormData();
				Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });
				fd.append('import_file', opts.formData);
				ajaxOpts.data = fd;
				ajaxOpts.processData = false;
				ajaxOpts.contentType = false;
			}

			$.ajax(ajaxOpts);
		}

		function downloadJson(filename, content) {
			var blob = new Blob([content], { type: 'application/json;charset=utf-8' });
			var link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = filename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
		}

		// 开关状态文字
		$root.on('change', '.echo-backup-enabled', function () {
			$root.find('.echo-backup-enabled').siblings('.echo-switch-state').text(
				$(this).is(':checked') ? '已开启' : '已关闭'
			);
		});

		// 保存定时设置
		$root.on('click', '.echo-backup-save-options', function () {
			request({
				op: 'save_options',
				enabled: $root.find('.echo-backup-enabled').is(':checked') ? '1' : '0',
				frequency: $root.find('.echo-backup-frequency').val(),
				keep: $root.find('.echo-backup-keep').val()
			});
		});

		// 立即备份
		$root.on('click', '.echo-backup-now', function () {
			request({ op: 'backup_now' });
		});

		// 导出
		$root.on('click', '.echo-backup-export', function () {
			request({ op: 'export' }, {
				onSuccess: function (d) { downloadJson(d.filename, d.content); }
			});
		});

		// 导入
		$root.on('submit', '.echo-backup-import-form', function (e) {
			e.preventDefault();
			var input = $root.find('.echo-backup-import-file')[0];
			if (!input || !input.files || !input.files[0]) {
				showToast('请选择配置文件', 'error');
				return;
			}
			var file = input.files[0];
			if (file.size > 1048576) {
				showToast('配置文件过大（上限 1MB）', 'error');
				return;
			}
			if (!window.confirm('导入会覆盖当前配置（导入前会自动备份一份）。确定继续？')) {
				return;
			}
			request({ op: 'import' }, {
				formData: file,
				onSuccess: function () {
					$root.find('.echo-backup-import-form')[0].reset();
				}
			});
		});

		// 下载备份
		$root.on('click', '.echo-backup-download', function () {
			var file = $(this).closest('.echo-backup-row').data('file');
			request({ op: 'download', file: file }, {
				onSuccess: function (d) { downloadJson(d.filename, d.content); }
			});
		});

		// 恢复备份
		$root.on('click', '.echo-backup-restore', function () {
			var file = $(this).closest('.echo-backup-row').data('file');
			if (!window.confirm('确定用这份备份覆盖当前配置？（恢复前会自动备份当前配置）')) {
				return;
			}
			request({ op: 'restore', file: file });
		});

		// 删除备份
		$root.on('click', '.echo-backup-delete', function () {
			var file = $(this).closest('.echo-backup-row').data('file');
			if (!window.confirm('确定删除备份 ' + file + '？')) {
				return;
			}
			request({ op: 'delete', file: file });
		});
	}

	/**
	 * 右下角即时通知（toast）
	 */
	/**
	 * 邮件 / SMTP：服务商预设、密码显隐、发送测试邮件
	 */
	function initMailTools() {
		var $presets = $('.echo-smtp-presets');
		if (!$presets.length && !$('[data-mail-test]').length) {
			return;
		}

		var data = window.echoSettingsData || {};

		$presets.on('click', '.echo-smtp-presets__item', function () {
			var $btn = $(this);
			var $host = $('#smtp_host');
			if (!$host.length) {
				return;
			}

			$host.val($btn.data('smtp-host'));
			$('#smtp_port').val($btn.data('smtp-port'));
			$('#smtp_secure').val($btn.data('smtp-secure'));

			$btn.addClass('is-active').siblings().removeClass('is-active');
			$host.trigger('input').trigger('change');
			showToast('已填入 ' + $.trim($btn.text()) + ' 的主机与端口，记得补账号密码', 'success');
		});

		$(document).on('click', '[data-echo-secret-toggle]', function () {
			var $btn = $(this);
			var $input = $btn.siblings('[data-echo-secret]');
			if (!$input.length) {
				return;
			}
			var show = $input.attr('type') === 'password';
			$input.attr('type', show ? 'text' : 'password');
			$btn.text(show ? '隐藏' : '显示');
		});

		$(document).on('click', '[data-mail-test]', function () {
			var $btn = $(this);
			var $field = $btn.closest('td');
			var $status = $field.find('[data-mail-test-status]');
			var to = $.trim($field.find('[data-mail-test-to]').val() || '');

			$btn.prop('disabled', true).addClass('is-loading');
			$status.removeClass('is-error is-success').text('正在连接 SMTP…');

			$.post(data.ajaxUrl || window.ajaxurl, {
				action: 'echo_test_mail',
				nonce: data.nonce || $('#echo-ajax-data').data('nonce') || '',
				email: to
			})
				.done(function (res) {
					if (res && res.success) {
						$status.addClass('is-success').text((res.data && res.data.message) || '发送成功');
					} else {
						$status.addClass('is-error').text((res && res.data && res.data.message) || '发送失败');
					}
				})
				.fail(function (xhr) {
					var msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
					$status.addClass('is-error').text(msg || '请求失败，请刷新页面后重试');
				})
				.always(function () {
					$btn.prop('disabled', false).removeClass('is-loading');
				});
		});
	}

	function initToast() {
		// 页面加载后，若存在保存成功提示，弹 toast（一般由 options.php 跳转回来）
		var $notice = $('.wrap .notice-success, .wrap #setting-error-settings_updated');
		if ($notice.length) {
			showToast($notice.find('p').text() || '设置已保存', 'success');
		}
	}

	/**
	 * 显示右下角 toast
	 * @param {string} message 提示文字
	 * @param {string} type    success | error
	 */
	function showToast(message, type) {
		type = type || 'success';

		var $stack = $('#echo-toast-stack');
		if (!$stack.length) {
			$stack = $('<div id="echo-toast-stack" class="echo-toast-stack" role="status" aria-live="polite"></div>').appendTo('body');
		}

		var icons = {
			success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
			error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
			info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="8" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
		};

		var $toast = $(
			'<div class="echo-toast echo-toast--' + type + '">' +
				'<span class="echo-toast__icon">' + (icons[type] || icons.info) + '</span>' +
				'<span class="echo-toast__text"></span>' +
				'<span class="echo-toast__bar"></span>' +
			'</div>'
		);

		$toast.find('.echo-toast__text').text(message);
		$stack.append($toast);

		// 最多同时显示 3 条
		$stack.children('.echo-toast').slice(0, -3).remove();

		window.requestAnimationFrame(function () {
			$toast.addClass('is-in');
		});

		setTimeout(function () {
			$toast.removeClass('is-in').addClass('is-out');
			setTimeout(function () {
				$toast.remove();
			}, 260);
		}, 2600);
	}

	/**
	 * 复选框即时保存：点击开关立即 AJAX 保存并弹通知
	 */
	function initInstantSave() {
		$('input[data-instant="1"]').on('change', function () {
			var $this = $(this);
			var key = $this.data('key');
			var value = $this.is(':checked') ? '1' : '0';

			// 优先从 DOM 兜底数据读取，其次用 localize 注入的全局变量
			var $data = $('#echo-ajax-data');
			var ajaxUrl = ($data.length && $data.data('ajax-url')) ||
				(window.echoSettingsData && window.echoSettingsData.ajaxUrl) ||
				(window.ajaxurl) || '';
			var nonce = ($data.length && $data.data('nonce')) ||
				(window.echoSettingsData && window.echoSettingsData.nonce) || '';

			if (!ajaxUrl) {
				showToast('无法获取请求地址，请刷新页面', 'error');
				$this.prop('checked', !$this.is(':checked'));
				return;
			}

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'echo_save_setting',
					nonce: nonce,
					key: key,
					value: value
				},
				success: function (res) {
					if (res && res.success) {
						showToast(res.data && res.data.message ? res.data.message : '设置已保存', 'success');
					} else {
						var msg = (res && res.data && res.data.message) ? res.data.message : '保存失败';
						showToast(msg, 'error');
						$this.prop('checked', !$this.is(':checked'));
					}
				},
				error: function (xhr, status, err) {
					var msg = '保存失败，请重试';
					try {
						var r = JSON.parse(xhr.responseText);
						if (r && r.data && r.data.message) {
							msg = r.data.message;
						}
					} catch (e) {}
					showToast(msg, 'error');
					$this.prop('checked', !$this.is(':checked'));
				}
			});
		});
	}

	/**
	 * Logo 上传器：用 WordPress 媒体库选择/上传图片
	 */
	function initLogoUploader() {
		var $field = $('.echo-logo-field');

		if (!$field.length) {
			return;
		}

		var $input = $('#echo_custom_logo');
		var $previewWrap = $field.find('.echo-logo-preview-wrap');
		var $uploadBtn = $field.find('.echo-logo-upload');
		var $removeBtn = $field.find('.echo-logo-remove');

		var frame = wp.media({
			title: '选择 Logo',
			button: {
				text: '使用此图片'
			},
			library: {
				type: 'image'
			},
			multiple: false
		});

		$uploadBtn.on('click', function (e) {
			e.preventDefault();
			frame.open();
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();

			$input.val(attachment.id);

			if (attachment.sizes && attachment.sizes.medium) {
				$previewWrap.html(
					'<img class="echo-logo-preview" src="' + attachment.sizes.medium.url + '" alt="" />'
				);
			} else {
				$previewWrap.html(
					'<img class="echo-logo-preview" src="' + attachment.url + '" alt="" />'
				);
			}

			$removeBtn.show();
			showToast('Logo 已选择，点击「保存设置」生效', 'success');
		});

		$removeBtn.on('click', function (e) {
			e.preventDefault();

			$input.val('');
			$previewWrap.html('<span class="echo-logo-placeholder">尚未上传 Logo</span>');
			$removeBtn.hide();
			showToast('Logo 已移除，点击「保存设置」生效', 'success');
		});
	}
})(jQuery);
