(function ($) {
	'use strict';

	if (typeof linkpeyAdmin === 'undefined') {
		return;
	}

	var state = {
		channels: [],
		previewTimer: null,
		lastSelectedKeys: [],
	};

	function storageKey() {
		return 'linkpey_selected_channels_u' + (linkpeyAdmin.userId || 0);
	}

	function loadStoredSelection() {
		try {
			var stored = window.localStorage.getItem(storageKey());
			if (stored) {
				state.lastSelectedKeys = JSON.parse(stored) || [];
			}
		} catch (e) {
			state.lastSelectedKeys = [];
		}
	}

	function saveStoredSelection(keys) {
		try {
			window.localStorage.setItem(storageKey(), JSON.stringify(keys || []));
		} catch (e) {
			// ignore
		}
	}

	function normalizePlatform(value) {
		var key = String(value || '').trim().toLowerCase();
		var aliases = {
			eitaa: 'eita',
			eit: 'eita',
		};
		return aliases[key] || key;
	}

	function channelKey(channel) {
		return normalizePlatform(channel.platform) + ':' + (channel.entity_id || '');
	}

	function formatCount(template, first, second) {
		return String(template).replace('%1$d', first).replace('%2$d', second);
	}

	function getOptions() {
		var options = {};
		$('.linkpey-option').each(function () {
			options[$(this).data('option')] = $(this).is(':checked');
		});
		return options;
	}

	function updateCounter(length) {
		var max = linkpeyAdmin.maxLength;
		var $counter = $('#linkpey-counter');
		var text = length + ' / ' + max + ' ' + linkpeyAdmin.i18n.chars;

		$counter.removeClass('is-warning is-error');
		if (length > max) {
			$counter.addClass('is-error');
		} else if (length > max - 500) {
			$counter.addClass('is-warning');
		}

		$counter.text(text);
		updateSendButtonState(length > max);
	}

	function updateSendButtonState(overLimit) {
		var hasSelection = $('.linkpey-channel:checked').length > 0;
		$('#linkpey-send-btn').prop('disabled', overLimit || !linkpeyAdmin.hasToken || !hasSelection);
	}

	function updateImageInfo(count) {
		var $info = $('#linkpey-image-info');
		if (!getOptions().include_images) {
			$info.text('');
			return;
		}

		if (count === 0) {
			$info.text('');
			return;
		}

		if (count === 1) {
			$info.text('1 تصویر — ' + linkpeyAdmin.i18n.albumSingle);
			return;
		}

		$info.text(count + ' تصویر — ' + linkpeyAdmin.i18n.albumMultiple);
	}

	function refreshPreview() {
		var options = getOptions();
		$.post(linkpeyAdmin.ajaxUrl, {
			action: 'linkpey_preview_message',
			nonce: linkpeyAdmin.nonces.preview,
			post_id: linkpeyAdmin.postId,
			include_title: options.include_title ? 1 : 0,
			include_excerpt: options.include_excerpt ? 1 : 0,
			include_content: options.include_content ? 1 : 0,
			include_link: options.include_link ? 1 : 0,
			include_images: options.include_images ? 1 : 0,
		})
			.done(function (response) {
				if (!response.success) {
					return;
				}

				$('#linkpey-preview').text(response.data.message || '');
				updateCounter(response.data.length || 0);
				updateImageInfo(response.data.image_count || 0);
			});
	}

	function schedulePreview() {
		clearTimeout(state.previewTimer);
		state.previewTimer = setTimeout(refreshPreview, 250);
	}

	function groupChannelsByPlatform(channels) {
		var groups = {};
		(channels || []).forEach(function (channel) {
			var platformSlug = normalizePlatform(channel.platform);
			if (!groups[platformSlug]) {
				groups[platformSlug] = {
					platform: platformSlug,
					platform_display_name: channel.platform_display_name || channel.platform || platformSlug,
					channels: [],
				};
			}
			groups[platformSlug].channels.push(channel);
		});
		return groups;
	}

	function updateSelectedCount() {
		var total = $('.linkpey-channel').length;
		var selected = $('.linkpey-channel:checked').length;
		var text = formatCount(linkpeyAdmin.i18n.selectedCount, selected, total);
		$('#linkpey-selected-count').text(text);
		updateSendButtonState($('#linkpey-counter').hasClass('is-error'));

		var keys = [];
		$('.linkpey-channel:checked').each(function () {
			keys.push($(this).val());
		});
		saveStoredSelection(keys);
	}

	function setAllChannels(checked) {
		$('.linkpey-channel').prop('checked', checked);
		$('.linkpey-platform-toggle').prop('checked', checked);
		updateSelectedCount();
	}

	function renderChannels(channels) {
		var $list = $('#linkpey-channels-list');
		state.channels = channels || [];
		loadStoredSelection();

		if (!state.channels.length) {
			$list.html('<p class="linkpey-muted">' + linkpeyAdmin.i18n.noChannels + '</p>');
			updateSelectedCount();
			return;
		}

		var groups = groupChannelsByPlatform(state.channels);
		var html = '';

		Object.keys(groups).forEach(function (platformKey) {
			var group = groups[platformKey];
			html += '<div class="linkpey-platform-group" data-platform="' + _.escape(group.platform) + '">';
			html += '<label class="linkpey-platform-header">';
			html += '<input type="checkbox" class="linkpey-platform-toggle" data-platform="' + _.escape(group.platform) + '" /> ';
			html += '<strong>' + _.escape(group.platform_display_name) + '</strong>';
			html += ' <small>(' + group.channels.length + ')</small>';
			html += '</label>';
			html += '<div class="linkpey-platform-channels">';

			group.channels.forEach(function (channel) {
				var label = channel.title || channel.username || channel.entity_id;
				var key = channelKey(channel);
				var checked = state.lastSelectedKeys.indexOf(key) !== -1 ? ' checked' : '';

				html += '<label class="linkpey-channel-item">';
				html += '<input type="checkbox" class="linkpey-channel" value="' + _.escape(key) + '" ';
				html += 'data-platform="' + _.escape(normalizePlatform(channel.platform || '')) + '" ';
				html += 'data-platform-display="' + _.escape(channel.platform_display_name || channel.platform || '') + '" ';
				html += 'data-entity-id="' + _.escape(channel.entity_id || '') + '" ';
				html += 'data-title="' + _.escape(label) + '"' + checked + ' /> ';
				html += _.escape(label);
				if (channel.username) {
					html += ' <small class="ltr">@' + _.escape(channel.username) + '</small>';
				}
				html += '</label>';
			});

			html += '</div></div>';
		});

		$list.html(html);
		syncPlatformToggles();
		updateSelectedCount();
	}

	function syncPlatformToggles() {
		$('.linkpey-platform-group').each(function () {
			var $group = $(this);
			var $channels = $group.find('.linkpey-channel');
			var $toggle = $group.find('.linkpey-platform-toggle');
			var checkedCount = $group.find('.linkpey-channel:checked').length;

			if (checkedCount === 0) {
				$toggle.prop('checked', false).prop('indeterminate', false);
			} else if (checkedCount === $channels.length) {
				$toggle.prop('checked', true).prop('indeterminate', false);
			} else {
				$toggle.prop('checked', false).prop('indeterminate', true);
			}
		});
	}

	function loadChannels(force) {
		if (!linkpeyAdmin.hasToken) {
			$('#linkpey-channels-list').html('<p class="linkpey-muted">' + linkpeyAdmin.i18n.noToken + '</p>');
			updateSelectedCount();
			return;
		}

		$('#linkpey-channels-list').html('<p class="linkpey-muted">...</p>');

		$.post(linkpeyAdmin.ajaxUrl, {
			action: 'linkpey_get_channels',
			nonce: linkpeyAdmin.nonces.channels,
			force_refresh: force ? 1 : 0,
		})
			.done(function (response) {
				if (!response.success) {
					$('#linkpey-channels-list').html(
						'<p class="linkpey-notice warning">' + _.escape(response.data && response.data.message ? response.data.message : linkpeyAdmin.i18n.noChannels) + '</p>'
					);
					updateSelectedCount();
					return;
				}
				if ((!state.lastSelectedKeys || !state.lastSelectedKeys.length) && response.data.last_selected) {
					state.lastSelectedKeys = response.data.last_selected;
				}
				renderChannels(response.data.channels || []);
			})
			.fail(function () {
				$('#linkpey-channels-list').html(
					'<p class="linkpey-notice warning">' + _.escape('خطا در ارتباط با سرور. دوباره تلاش کنید.') + '</p>'
				);
				updateSelectedCount();
			});
	}

	function getSelectedChannels() {
		var selected = [];
		$('.linkpey-channel:checked').each(function () {
			selected.push({
				platform: normalizePlatform($(this).data('platform')),
				platform_display_name: $(this).data('platform-display'),
				entity_id: $(this).data('entity-id'),
				title: $(this).data('title'),
			});
		});
		return selected;
	}

	function countPlatforms(channels) {
		var platforms = {};
		channels.forEach(function (channel) {
			platforms[channel.platform] = true;
		});
		return Object.keys(platforms).length;
	}

	function renderSendResults(results) {
		var $wrap = $('#linkpey-send-results');
		if (!results || !results.length) {
			$wrap.empty();
			return;
		}

		var html = '';
		results.forEach(function (item) {
			var platformLabel = item.platform_display_name || item.platform || '';
			html += '<div class="linkpey-result-item ' + (item.success ? 'is-success' : 'is-error') + '">';
			html += (item.success ? '✓ ' : '✗ ');
			html += _.escape(platformLabel) + ' — ' + _.escape(item.channel);
			html += '<br><small>' + _.escape(item.message) + '</small>';
			html += '</div>';
		});
		$wrap.html(html);
	}

	function sendPost() {
		var channels = getSelectedChannels();
		if (!channels.length) {
			alert(linkpeyAdmin.i18n.selectChannel);
			return;
		}

		var platformCount = countPlatforms(channels);
		var confirmMessage = formatCount(linkpeyAdmin.i18n.confirmSend, channels.length, platformCount);

		if (!window.confirm(confirmMessage)) {
			return;
		}

		var options = getOptions();
		var $btn = $('#linkpey-send-btn');
		var sendingText = formatCount(linkpeyAdmin.i18n.sendingTo, channels.length, platformCount);

		$btn.prop('disabled', true).text(sendingText);

		$.post(linkpeyAdmin.ajaxUrl, {
			action: 'linkpey_send_post',
			nonce: linkpeyAdmin.nonces.send,
			post_id: linkpeyAdmin.postId,
			channels: JSON.stringify(channels),
			include_title: options.include_title ? 1 : 0,
			include_excerpt: options.include_excerpt ? 1 : 0,
			include_content: options.include_content ? 1 : 0,
			include_link: options.include_link ? 1 : 0,
			include_images: options.include_images ? 1 : 0,
		})
			.always(function () {
				$btn.prop('disabled', false).text('ارسال به کانال\u200cهای انتخاب\u200cشده');
				schedulePreview();
				updateSelectedCount();
			})
			.done(function (response) {
				if (!response.success) {
					alert(response.data && response.data.message ? response.data.message : linkpeyAdmin.i18n.emptyMessage);
					return;
				}
				renderSendResults(response.data.results || []);
			});
	}

	var _ = {
		escape: function (value) {
			return $('<div/>').text(value || '').html();
		},
	};

	$(function () {
		$('.linkpey-option').on('change', schedulePreview);

		$('#linkpey-refresh-channels').on('click', function () {
			loadChannels(true);
		});

		$('#linkpey-select-all').on('click', function () {
			setAllChannels(true);
		});

		$('#linkpey-deselect-all').on('click', function () {
			setAllChannels(false);
		});

		$(document).on('change', '.linkpey-platform-toggle', function () {
			var platform = $(this).data('platform');
			var checked = $(this).is(':checked');
			$('.linkpey-platform-group[data-platform="' + platform + '"] .linkpey-channel').prop('checked', checked);
			$(this).prop('indeterminate', false);
			updateSelectedCount();
		});

		$(document).on('change', '.linkpey-channel', function () {
			syncPlatformToggles();
			updateSelectedCount();
		});

		$('#linkpey-send-btn').on('click', sendPost);

		loadChannels(false);
		schedulePreview();
	});
})(jQuery);
