(function ($) {
	'use strict';

	if (typeof linkpeySettings === 'undefined') {
		return;
	}

	function renderTestResult($result, payload, isSuccess) {
		var html = '';
		if (isSuccess) {
			var okClass = payload.warning ? 'linkpey-test-warn' : 'linkpey-test-ok';
			html += '<span class="' + okClass + '">' + _.escape(payload.message || linkpeySettings.i18n.success) + '</span>';
			if (payload.count !== undefined) {
				html += ' <span class="linkpey-muted">(' + _.escape(String(payload.count)) + ' ' + _.escape(linkpeySettings.i18n.channels) + ')</span>';
			}
			if (payload.hint) {
				html += '<br><span class="linkpey-test-hint">' + _.escape(payload.hint) + '</span>';
			}
			if (payload.debug && payload.debug.url) {
				html += '<br><span class="linkpey-muted ltr">' + _.escape(payload.debug.url) + '</span>';
				html += '<br><span class="linkpey-muted">' + _.escape('HTTP ' + payload.debug.http_status + ' | bytes: ' + payload.debug.body_length + (payload.debug.transport ? ' | ' + payload.debug.transport : '')) + '</span>';
			}
		} else {
			html += '<span class="linkpey-test-error">' + _.escape(payload.message || linkpeySettings.i18n.failed) + '</span>';
			if (payload.hint) {
				html += '<br><span class="linkpey-test-hint">' + _.escape(payload.hint) + '</span>';
			}
			if (payload.technical) {
				html += '<br><code class="linkpey-test-technical">' + _.escape(payload.technical) + '</code>';
			}
			if (payload.api_url) {
				html += '<br><span class="linkpey-muted ltr">' + _.escape(payload.api_url) + '</span>';
			}
		}
		$result.html(html);
	}

	var _ = {
		escape: function (value) {
			return $('<div/>').text(value || '').html();
		},
	};

	$('.linkpey-toggle-token').on('click', function () {
		var $input = $('#linkpey_api_token_input');
		var isPassword = $input.attr('type') === 'password';
		$input.attr('type', isPassword ? 'text' : 'password');
		$(this).text(isPassword ? linkpeySettings.i18n.hideToken : linkpeySettings.i18n.showToken);
	});

	function runTransportProbe(transportId) {
		var $buttons = $('.linkpey-transport-btn');
		var $wrap = $('#linkpey-transport-wrap');
		var $out = $('#linkpey-transport-output');
		var token = ($('#linkpey_api_token_input').val() || '').trim();

		if (!token) {
			$wrap.prop('hidden', false);
			$out.text(linkpeySettings.i18n.noToken + ' ' + linkpeySettings.i18n.noTokenHint);
			return;
		}

		$wrap.prop('hidden', false);
		$out.text(linkpeySettings.i18n.transportRunning);
		$buttons.prop('disabled', true);

		$.post(linkpeySettings.ajaxUrl, {
			action: 'linkpey_transport_probe',
			nonce: linkpeySettings.transportNonce,
			api_token: token,
			transport: transportId,
		})
			.always(function () {
				$buttons.prop('disabled', false);
			})
			.done(function (response) {
				if (!response.success || !response.data || !response.data.report) {
					$out.text('Transport probe failed.');
					return;
				}
				$out.text(JSON.stringify(response.data.report, null, 2));
			})
			.fail(function (xhr) {
				var msg = linkpeySettings.i18n.ajaxFailed;
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				$out.text(msg);
			});
	}

	$(document).on('click', '.linkpey-transport-btn', function () {
		runTransportProbe($(this).data('transport'));
	});

	$('#linkpey-run-diagnostic').on('click', function () {
		var $btn = $(this);
		var $wrap = $('#linkpey-diagnostic-wrap');
		var $out = $('#linkpey-diagnostic-output');
		var token = ($('#linkpey_api_token_input').val() || '').trim();

		$wrap.prop('hidden', false);
		$out.text(linkpeySettings.i18n.diagnosticRunning);
		$btn.prop('disabled', true);

		$.post(linkpeySettings.ajaxUrl, {
			action: 'linkpey_run_diagnostic',
			nonce: linkpeySettings.diagNonce,
			api_token: token,
		})
			.always(function () {
				$btn.prop('disabled', false);
			})
			.done(function (response) {
				if (!response.success || !response.data || !response.data.report) {
					$out.text('Diagnostic failed.');
					return;
				}
				$out.text(JSON.stringify(response.data.report, null, 2));
			})
			.fail(function () {
				$out.text(linkpeySettings.i18n.ajaxFailed);
			});
	});

	$('#linkpey-test-connection').on('click', function () {
		var $btn = $(this);
		var $result = $('#linkpey-test-result');
		var token = ($('#linkpey_api_token_input').val() || '').trim();

		if (!token) {
			renderTestResult($result, {
				message: linkpeySettings.i18n.noToken,
				hint: linkpeySettings.i18n.noTokenHint,
			}, false);
			return;
		}

		$btn.prop('disabled', true);
		$result.text(linkpeySettings.i18n.testing);

		$.post(linkpeySettings.ajaxUrl, {
			action: 'linkpey_test_connection',
			nonce: linkpeySettings.nonce,
			api_token: token,
		})
			.always(function () {
				$btn.prop('disabled', false);
			})
			.done(function (response) {
				if (response.success) {
					renderTestResult($result, response.data || {}, true);
					return;
				}
				renderTestResult($result, response.data || {}, false);
			})
			.fail(function (xhr) {
				var payload = { message: linkpeySettings.i18n.ajaxFailed };
				if (xhr.responseJSON && xhr.responseJSON.data) {
					payload = xhr.responseJSON.data;
				}
				renderTestResult($result, payload, false);
			});
	});
})(jQuery);
