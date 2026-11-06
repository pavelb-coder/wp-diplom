/**
 * Админ-скрипты WP Diplom: выбор фона, управление полями бланка.
 */
(function ($) {
	'use strict';

	$(function () {
		/* --- Выбор фонового изображения --- */
		var frame = null;

		$('#wpdc-background-select').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: wpdcAdmin.chooseBackground,
				button: { text: wpdcAdmin.useBackground },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();

				$('#wpdc_background_id').val(attachment.id);

				var url = attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;

				$('#wpdc-background-preview').html(
					$('<img>').attr({ src: url, alt: '' })
				);
			});

			frame.open();
		});

		$('#wpdc-background-remove').on('click', function (event) {
			event.preventDefault();
			$('#wpdc_background_id').val('');
			$('#wpdc-background-preview').empty();
		});

		/* --- PDF-шаблон --- */
		// Форма редактирования записи по умолчанию без enctype, поэтому
		// загрузка файла напрямую не работала бы.
		var postForm = $('#post');

		if (postForm.length && postForm.attr('enctype') !== 'multipart/form-data') {
			postForm.attr('enctype', 'multipart/form-data');
		}

		var pdfFrame = null;

		$('#wpdc-pdf-select').on('click', function (event) {
			event.preventDefault();

			if (pdfFrame) {
				pdfFrame.open();
				return;
			}

			pdfFrame = wp.media({
				title: wpdcAdmin.choosePdf,
				button: { text: wpdcAdmin.usePdf },
				library: { type: 'application/pdf' },
				multiple: false
			});

			pdfFrame.on('select', function () {
				var attachment = pdfFrame.state().get('selection').first().toJSON();

				$('#wpdc_pdf_id').val(attachment.id);

				var name = attachment.filename || (attachment.url || '').split('/').pop();

				$('#wpdc-pdf-chosen')
					.text(name)
					.show();
			});

			pdfFrame.open();
		});

		$('#wpdc_pdf').on('change', function () {
			var file = this.files && this.files[0];
			$('#wpdc_pdf_id').val('0');

			if (file) {
				$('#wpdc-pdf-chosen').text(file.name).show();
			}
		});

		/* --- Поля бланка --- */
		var body = $('#wpdc-fields-body');

		function nextIndex() {
			return body.find('.wpdc-field-row').length === 0
				? 0
				: Math.max.apply(null, body.find('.wpdc-field-row').map(function () {
					var match = $(this).find('input, textarea, select').first().attr('name').match(/\[(\d+)\]/);
					return match ? parseInt(match[1], 10) : 0;
				}).get()) + 1;
		}

		$('#wpdc-add-field').on('click', function (event) {
			event.preventDefault();

			var template = $('#tmpl-wpdc-field-row').html().replace(/__index__/g, nextIndex());

			body.append(template);
		});

		body.on('click', '.wpdc-remove-field', function (event) {
			event.preventDefault();

			if (window.confirm(wpdcAdmin.confirmDelete)) {
				$(this).closest('.wpdc-field-row').remove();
			}
		});

		/* --- Дополнительные поля сертификата --- */
		$('#wpdc-add-custom').on('click', function (event) {
			event.preventDefault();

			var row = $('<p class="wpdc-custom-row">'
				+ '<input type="text" name="wpdc_custom_key[]" class="wpdc-custom-key" placeholder="group">'
				+ '<input type="text" name="wpdc_custom_value[]" class="regular-text" placeholder="значение">'
				+ '<button type="button" class="button-link wpdc-remove-custom">×</button>'
				+ '</p>');

			$('#wpdc-custom-fields').append(row);
			row.find('input').first().trigger('focus');
		});

		$('#wpdc-custom-fields').on('click', '.wpdc-remove-custom', function (event) {
			event.preventDefault();
			$(this).closest('.wpdc-custom-row').remove();
		});
	});
})(jQuery);
