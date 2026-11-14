/**
 * Echo 设置页脚本：取色器 + Logo 上传 + 即时保存 + 右下角通知
 */
(function ($) {
	'use strict';

	$(function () {
		// 主题色取色器
		$('.echo-color-field').wpColorPicker();

		initColorPresets();
		initWidthSlider();
		initLogoUploader();
		initInstantSave();
		initSwitchState();
		initToast();
	});

	/**
	 * 主题色预设色块：点击填充取色器
	 */
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
	 * 右下角即时通知（toast）
	 */
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
		var $toast = $('#echo-toast');

		if (!$toast.length) {
			$toast = $('<div id="echo-toast" class="echo-toast"></div>').appendTo('body');
		}

		$toast
			.removeClass('echo-toast--success echo-toast--error')
			.addClass('echo-toast--' + (type || 'success'))
			.text(message)
			.addClass('echo-toast--show');

		clearTimeout($toast.data('timer'));
		$toast.data('timer', setTimeout(function () {
			$toast.removeClass('echo-toast--show');
		}, 2200));
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
