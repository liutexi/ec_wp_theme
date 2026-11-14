/**
 * Echo 设置页脚本：取色器 + Logo 媒体上传器
 */
(function ($) {
	'use strict';

	$(function () {
		// 主题色取色器
		$('.echo-color-field').wpColorPicker();

		initLogoUploader();
	});

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

			// 更新预览
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
		});

		$removeBtn.on('click', function (e) {
			e.preventDefault();

			$input.val('');
			$previewWrap.html('<span class="echo-logo-placeholder">尚未上传 Logo</span>');
			$removeBtn.hide();
		});
	}
})(jQuery);
