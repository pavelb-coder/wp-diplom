/**
 * Админ-скрипты WP Diplom: выбор фона, управление полями бланка.
 */
(function ($) {
	'use strict';

	$(function () {
		/* --- Выбор фонового изображения --- */
		var frame = null;

		$('#wpd-background-select').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: wpdAdmin.chooseBackground,
				button: { text: wpdAdmin.useBackground },
				library: { type: 'image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();

				$('#wpd_background_id').val(attachment.id);

				var url = attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;

				$('#wpd-background-preview').html(
					$('<img>').attr({ src: url, alt: '' })
				);
			});

			frame.open();
		});

		$('#wpd-background-remove').on('click', function (event) {
			event.preventDefault();
			$('#wpd_background_id').val('');
			$('#wpd-background-preview').empty();
		});

		/* --- Поля бланка --- */
		var body = $('#wpd-fields-body');

		function nextIndex() {
			return body.find('.wpd-field-row').length === 0
				? 0
				: Math.max.apply(null, body.find('.wpd-field-row').map(function () {
					var match = $(this).find('input, textarea, select').first().attr('name').match(/\[(\d+)\]/);
					return match ? parseInt(match[1], 10) : 0;
				}).get()) + 1;
		}

		$('#wpd-add-field').on('click', function (event) {
			event.preventDefault();

			var template = $('#tmpl-wpd-field-row').html().replace(/__index__/g, nextIndex());

			body.append(template);
		});

		body.on('click', '.wpd-remove-field', function (event) {
			event.preventDefault();

			if (window.confirm(wpdAdmin.confirmDelete)) {
				$(this).closest('.wpd-field-row').remove();
			}
		});

		/* --- Дополнительные поля сертификата --- */
		$('#wpd-add-custom').on('click', function (event) {
			event.preventDefault();

			var row = $('<p class="wpd-custom-row">'
				+ '<input type="text" name="wpd_custom_key[]" class="wpd-custom-key" placeholder="group">'
				+ '<input type="text" name="wpd_custom_value[]" class="regular-text" placeholder="значение">'
				+ '<button type="button" class="button-link wpd-remove-custom">×</button>'
				+ '</p>');

			$('#wpd-custom-fields').append(row);
			row.find('input').first().trigger('focus');
		});

		$('#wpd-custom-fields').on('click', '.wpd-remove-custom', function (event) {
			event.preventDefault();
			$(this).closest('.wpd-custom-row').remove();
		});
	});
})(jQuery);
