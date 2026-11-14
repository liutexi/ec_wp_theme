/**
 * Echo 设置页脚本：取色器 + Logo 上传 + 即时保存 + 右下角通知
 */
(function ($) {
	'use strict';

	$(function () {
		// 主题色取色器
		$('.echo-color-field').wpColorPicker();

		initLogoUploader();
		initInstantSave();
		initToast();
	});

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

			$.ajax({
				url: (window.echoSettingsData && echoSettingsData.ajaxUrl) || ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'echo_save_setting',
					nonce: window.echoSettingsData ? echoSettingsData.nonce : '',
					key: key,
					value: value
				},
				success: function (res) {
					if (res && res.success) {
						showToast('设置已保存', 'success');
					} else {
						showToast((res && res.data && res.data.message) || '保存失败', 'error');
						// 回滚复选框状态
						$this.prop('checked', !$this.is(':checked'));
					}
				},
				error: function () {
					showToast('保存失败，请重试', 'error');
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
