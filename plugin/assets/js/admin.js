(function ($) {
	'use strict';

	function setResult(html, isError) {
		$('#devti-result').html(
			'<div class="' + (isError ? 'notice notice-error' : 'notice notice-success') + '"><p>' + html + '</p></div>'
		);
	}

	$(document).on('click', '#devti-test-conn', function (e) {
		e.preventDefault();
		setResult(DEVTI_SFTP.strings.testing, false);

		$.post(DEVTI_SFTP.ajax_url, {
			action: 'devti_sftp_test',
			nonce: DEVTI_SFTP.nonce
		})
			.done(function (resp) {
				if (resp && resp.success) {
					setResult('<strong>' + DEVTI_SFTP.strings.success + ':</strong> ' + resp.data.message, false);
				} else {
					setResult('<strong>' + DEVTI_SFTP.strings.error + ':</strong> ' + (resp && resp.data ? resp.data.message : 'Erro desconhecido'), true);
				}
			})
			.fail(function () {
				setResult('<strong>' + DEVTI_SFTP.strings.error + ':</strong> Falha na requisição AJAX.', true);
			});
	});

	$(document).on('click', '#devti-send-file', function (e) {
		e.preventDefault();
		setResult(DEVTI_SFTP.strings.sending, false);

		$.post(DEVTI_SFTP.ajax_url, {
			action: 'devti_sftp_send',
			nonce: DEVTI_SFTP.nonce
		})
			.done(function (resp) {
				if (resp && resp.success) {
					setResult('<strong>' + DEVTI_SFTP.strings.success + ':</strong> ' + resp.data.message, false);
				} else {
					setResult('<strong>' + DEVTI_SFTP.strings.error + ':</strong> ' + (resp && resp.data ? resp.data.message : 'Erro desconhecido'), true);
				}
			})
			.fail(function () {
				setResult('<strong>' + DEVTI_SFTP.strings.error + ':</strong> Falha na requisição AJAX.', true);
			});
	});
})(jQuery);
