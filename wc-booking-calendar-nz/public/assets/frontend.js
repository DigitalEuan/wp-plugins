(function($) {
	'use strict';

	var pluginConfig = window.wc_booking_calendar || {};

	function parseJsonAttr($el, attrName) {
		var raw = $el.attr(attrName) || '[]';
		try {
			return JSON.parse(raw);
		} catch (e) {
			return [];
		}
	}

	function nl2Html(text) {
		var safe = $('<div/>').text(text || '').html();
		return safe.replace(/\n/g, '<br>');
	}

	function normalizeDateString(value) {
		var raw = $.trim(String(value || ''));
		var match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
		if (match) {
			return match[1] + '-' + match[2] + '-' + match[3];
		}

		var parsed = new Date(raw);
		if (isNaN(parsed.getTime())) {
			return raw;
		}

		var year = parsed.getFullYear();
		var month = String(parsed.getMonth() + 1).padStart(2, '0');
		var day = String(parsed.getDate()).padStart(2, '0');
		return year + '-' + month + '-' + day;
	}

	function getWeekdayKey(isoDate) {
		var match = normalizeDateString(isoDate).match(/^(\d{4})-(\d{2})-(\d{2})$/);
		var map = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
		if (!match) {
			return '';
		}
		var date = new Date(parseInt(match[1], 10), parseInt(match[2], 10) - 1, parseInt(match[3], 10));
		return map[date.getDay()] || '';
	}

	function buildBlackoutLookup() {
		var lookup = {};
		$.each(pluginConfig.blackout_dates || [], function(_, value) {
			var normalized = normalizeDateString(value);
			if (normalized) {
				lookup[normalized] = true;
			}
		});
		return lookup;
	}

	var WCBookingCalendar = {
		init: function() {
			$('.wc-booking-form').each(function() {
				WCBookingCalendar.setupForm($(this));
			});
		},

		setupForm: function($form) {
			$form.data('modeConfigs', parseJsonAttr($form, 'data-booking-modes'));
			$form.data('addonConfigs', parseJsonAttr($form, 'data-booking-addons'));
			$form.data('depositPercentage', parseInt($form.attr('data-deposit-percentage'), 10) || 0);
			$form.data('blackoutLookup', buildBlackoutLookup());

			this.initDatePicker($form);
			this.bindEvents($form);
			this.updateModeUI($form);
			this.updatePriceDisplay($form, { total: 0, due_today: 0 });
			this.clearAvailabilityStatus($form);
		},

		bindEvents: function($form) {
			$form.on('change', '#booking_mode', function() {
				WCBookingCalendar.updateModeUI($form);
				WCBookingCalendar.resetTimeSlots($form);
				WCBookingCalendar.clearAvailabilityStatus($form);
				if ($form.find('#booking_date').val()) {
					WCBookingCalendar.loadTimeSlots($form, $form.find('#booking_date').val());
				}
				WCBookingCalendar.calculatePrice($form);
			});

			$form.on('change', '#booking_date', function() {
				WCBookingCalendar.handleDateChange($form, $(this).val());
			});

			$form.on('change', '#booking_time, #resource_id, input[name="booking_payment_option"], input[name="booking_addons[]"]', function() {
				WCBookingCalendar.calculatePrice($form);
				WCBookingCalendar.checkAvailability($form);
			});

			$form.on('input change', '.person-type-input input', function() {
				WCBookingCalendar.calculatePrice($form);
				WCBookingCalendar.checkAvailability($form);
			});

			$form.on('click', '#booking-add-to-cart', function(e) {
				var $cartForm = $(this).closest('form.cart');
				if ($cartForm.length && !WCBookingCalendar.validateForm($form)) {
					e.preventDefault();
				}
			});
		},

		initDatePicker: function($form) {
			var $input = $form.find('.date-picker');
			var minDate = $input.data('min-date') || '';
			var maxDate = $input.data('max-date') || '';
			var blackoutLookup = $form.data('blackoutLookup') || {};
			var openDays = pluginConfig.bookable_days || { monday: 1, tuesday: 1, wednesday: 1, thursday: 1, friday: 1, saturday: 1, sunday: 1 };
			var map = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

			$input.datepicker({
				dateFormat: pluginConfig.date_format || 'yy-mm-dd',
				minDate: minDate,
				maxDate: maxDate,
				beforeShowDay: function(date) {
					var iso = $.datepicker.formatDate('yy-mm-dd', date);
					var dayName = map[date.getDay()];
					if (blackoutLookup[iso]) {
						return [false, 'wc-booking-blackout', 'Unavailable'];
					}
					if (openDays[dayName] === 0 || openDays[dayName] === '0') {
						return [false, 'wc-booking-closed-day', 'Unavailable'];
					}
					return [true, '', ''];
				},
				onSelect: function(dateText) {
					WCBookingCalendar.handleDateChange($form, dateText);
				}
			});
		},

		handleDateChange: function($form, rawDate) {
			var normalizedDate = normalizeDateString(rawDate);
			$form.find('#booking_date').val(normalizedDate);
			WCBookingCalendar.resetTimeSlots($form);
			WCBookingCalendar.clearAvailabilityStatus($form);

			if (!normalizedDate) {
				return;
			}

			if (WCBookingCalendar.isDateBlocked($form, normalizedDate)) {
				WCBookingCalendar.setAvailabilityStatus(
					$form,
					'unavailable',
					(pluginConfig.i18n && pluginConfig.i18n.unavailable) || 'Unavailable'
				);
				return;
			}

			WCBookingCalendar.loadTimeSlots($form, normalizedDate);
			WCBookingCalendar.calculatePrice($form);
		},

		isDateBlocked: function($form, dateValue) {
			var normalizedDate = normalizeDateString(dateValue);
			var blackoutLookup = $form.data('blackoutLookup') || {};
			var openDays = pluginConfig.bookable_days || {};
			var weekdayKey = getWeekdayKey(normalizedDate);

			if (!normalizedDate) {
				return false;
			}
			if (blackoutLookup[normalizedDate]) {
				return true;
			}
			if (weekdayKey && (openDays[weekdayKey] === 0 || openDays[weekdayKey] === '0')) {
				return true;
			}
			return false;
		},

		getSelectedAddons: function($form) {
			var selected = [];
			$form.find('input[name="booking_addons[]"]:checked').each(function() {
				selected.push($(this).val());
			});
			return selected;
		},

		getPersonTypes: function($form) {
			var personTypes = {};
			$form.find('.person-type-input input').each(function() {
				var typeId = ($(this).attr('id') || '').replace('person_type_', '');
				var count = parseInt($(this).val(), 10) || 0;
				if (count > 0) {
					personTypes[typeId] = count;
				}
			});
			return personTypes;
		},

		updateModeUI: function($form) {
			var selectedMode = $form.find('#booking_mode').val();
			var modeConfigs = $form.data('modeConfigs') || [];
			var activeMode = null;
			$.each(modeConfigs, function(_, mode) {
				if (mode.key === selectedMode) {
					activeMode = mode;
					return false;
				}
			});
			activeMode = activeMode || modeConfigs[0] || null;

			var $desc = $form.find('#booking-mode-description');
			if (activeMode && activeMode.description) {
				$desc.find('.booking-mode-description__inner').html(nl2Html(activeMode.description));
				$desc.show();
			} else {
				$desc.hide();
			}

			var showAddons = !!(activeMode && activeMode.show_addons);
			var $addonsSection = $form.find('#booking-addons-section');
			if ($addonsSection.length) {
				var hasAddonChoices = $addonsSection.find('input[type="checkbox"]').length > 0;
				if (showAddons && hasAddonChoices) {
					$addonsSection.show();
				} else {
					$addonsSection.hide();
					$addonsSection.find('input[type="checkbox"]').prop('checked', false);
				}
			}
		},

		loadTimeSlots: function($form, date) {
			var normalizedDate = normalizeDateString(date);
			var $select = $form.find('#booking_time');
			var $loading = $form.find('.loading-slots');

			if (!normalizedDate || WCBookingCalendar.isDateBlocked($form, normalizedDate)) {
				WCBookingCalendar.resetTimeSlots($form, true);
				WCBookingCalendar.setAvailabilityStatus(
					$form,
					'unavailable',
					(pluginConfig.i18n && pluginConfig.i18n.no_slots) || 'No time slots available for this date.'
				);
				return;
			}

			$loading.show();
			$select.prop('disabled', true);
			WCBookingCalendar.clearAvailabilityStatus($form);

			$.ajax({
				url: pluginConfig.ajax_url,
				type: 'POST',
				data: {
					action: 'wc_booking_get_slots',
					nonce: pluginConfig.nonce,
					product_id: $form.find('#product_id').val(),
					date: normalizedDate,
					resource_id: $form.find('#resource_id').val(),
					mode: $form.find('#booking_mode').val()
				}
			}).done(function(response) {
				var slots = (response && response.success && response.data && response.data.slots) ? response.data.slots : [];
				WCBookingCalendar.populateTimeSlots($form, slots);
				if (!slots.length) {
					WCBookingCalendar.setAvailabilityStatus(
						$form,
						'unavailable',
						(response && response.data && response.data.message) || ((pluginConfig.i18n && pluginConfig.i18n.no_slots) || 'No time slots available for this date.')
					);
				}
			}).fail(function() {
				WCBookingCalendar.populateTimeSlots($form, []);
				WCBookingCalendar.setAvailabilityStatus(
					$form,
					'unavailable',
					(pluginConfig.i18n && pluginConfig.i18n.error) || 'An error occurred. Please try again.'
				);
			}).always(function() {
				$loading.hide();
				$select.prop('disabled', $select.find('option').length <= 1 && !$select.val());
			});
		},

		populateTimeSlots: function($form, slots) {
			var $select = $form.find('#booking_time');
			$select.empty();
			if (!slots.length) {
				$select.append('<option value="">' + ((pluginConfig.i18n && pluginConfig.i18n.no_slots) || 'No time slots available for this date.') + '</option>');
				$select.prop('disabled', true);
				return;
			}
			$select.append('<option value="">' + ((pluginConfig.i18n && pluginConfig.i18n.select_time) || 'Select a time slot') + '</option>');
			$.each(slots, function(_, slot) {
				var label = slot.name + ' (' + slot.start + ' - ' + slot.end + ')';
				if (slot.available !== undefined) {
					label += ' — ' + slot.available + ' spots';
				}
				$select.append('<option value="' + slot.start + '-' + slot.end + '">' + label + '</option>');
			});
			$select.prop('disabled', false).val('');
		},

		resetTimeSlots: function($form, noSlotsState) {
			var $select = $form.find('#booking_time');
			$select.empty();
			if (noSlotsState) {
				$select.append('<option value="">' + ((pluginConfig.i18n && pluginConfig.i18n.no_slots) || 'No time slots available for this date.') + '</option>');
				$select.prop('disabled', true);
				return;
			}
			$select.append('<option value="">' + ((pluginConfig.i18n && pluginConfig.i18n.select_time) || 'Select a time slot') + '</option>');
			$select.prop('disabled', false);
		},

		calculatePrice: function($form) {
			var personTypes = WCBookingCalendar.getPersonTypes($form);
			if ($.isEmptyObject(personTypes)) {
				WCBookingCalendar.updatePriceDisplay($form, { total: 0, due_today: 0 });
				return;
			}

			$.ajax({
				url: pluginConfig.ajax_url,
				type: 'POST',
				data: {
					action: 'wc_booking_calculate_price',
					nonce: pluginConfig.nonce,
					product_id: $form.find('#product_id').val(),
					person_types: personTypes,
					date: normalizeDateString($form.find('#booking_date').val()),
					mode: $form.find('#booking_mode').val(),
					booking_addons: WCBookingCalendar.getSelectedAddons($form),
					payment_option: $form.find('input[name="booking_payment_option"]:checked').val() || 'full'
				}
			}).done(function(response) {
				if (response.success) {
					WCBookingCalendar.updatePriceDisplay($form, response.data || {});
				}
			});
		},

		checkAvailability: function($form) {
			var personTypes = WCBookingCalendar.getPersonTypes($form);
			var dateValue = normalizeDateString($form.find('#booking_date').val());
			var timeValue = $form.find('#booking_time').val();

			if ($.isEmptyObject(personTypes) || !dateValue || !timeValue) {
				WCBookingCalendar.clearAvailabilityStatus($form);
				return;
			}

			if (WCBookingCalendar.isDateBlocked($form, dateValue)) {
				WCBookingCalendar.setAvailabilityStatus(
					$form,
					'unavailable',
					(pluginConfig.i18n && pluginConfig.i18n.unavailable) || 'Unavailable'
				);
				return;
			}

			WCBookingCalendar.setAvailabilityStatus(
				$form,
				'checking',
				(pluginConfig.i18n && pluginConfig.i18n.checking) || 'Checking availability…'
			);

			$.ajax({
				url: pluginConfig.ajax_url,
				type: 'POST',
				data: {
					action: 'wc_booking_check_availability',
					nonce: pluginConfig.nonce,
					product_id: $form.find('#product_id').val(),
					date: dateValue,
					time: timeValue,
					resource_id: $form.find('#resource_id').val(),
					mode: $form.find('#booking_mode').val(),
					person_types: personTypes
				}
			}).done(function(response) {
				if (response.success) {
					WCBookingCalendar.setAvailabilityStatus(
						$form,
						'available',
						(pluginConfig.i18n && pluginConfig.i18n.available) || 'Available'
					);
				} else {
					WCBookingCalendar.setAvailabilityStatus(
						$form,
						'unavailable',
						(response.data && response.data.message) || ((pluginConfig.i18n && pluginConfig.i18n.unavailable) || 'Unavailable')
					);
				}
			}).fail(function() {
				WCBookingCalendar.setAvailabilityStatus(
					$form,
					'unavailable',
					(pluginConfig.i18n && pluginConfig.i18n.error) || 'An error occurred. Please try again.'
				);
			});
		},

		setAvailabilityStatus: function($form, state, message) {
			var $status = $form.find('.booking-availability-status');
			$status.removeClass('available unavailable checking');
			if (!message) {
				$status.hide();
				$status.find('.availability-message').text('');
				return;
			}
			if (state) {
				$status.addClass(state);
			}
			$status.find('.availability-message').text(message);
			$status.show();
		},

		clearAvailabilityStatus: function($form) {
			WCBookingCalendar.setAvailabilityStatus($form, '', '');
		},

		formatCurrency: function(amount) {
			var symbol = pluginConfig.currency_symbol || '$';
			return symbol + parseFloat(amount || 0).toFixed(2);
		},

		updatePriceDisplay: function($form, data) {
			var total = parseFloat(data.total || 0);
			var dueToday = parseFloat(data.due_today !== undefined ? data.due_today : total);
			$form.find('#booking-total-price').text(data.total_formatted || WCBookingCalendar.formatCurrency(total));
			$form.find('#booking-due-today').text(data.due_today_formatted || WCBookingCalendar.formatCurrency(dueToday));
		},

		validateForm: function($form) {
			var errors = [];
			var dateValue = normalizeDateString($form.find('#booking_date').val());
			if (!dateValue) {
				errors.push('Please select a date');
			}
			if (dateValue && WCBookingCalendar.isDateBlocked($form, dateValue)) {
				errors.push('This date is unavailable');
			}
			if (!$form.find('#booking_time').val()) {
				errors.push('Please select a time slot');
			}
			if ($.isEmptyObject(WCBookingCalendar.getPersonTypes($form))) {
				errors.push('Please select at least one person');
			}
			if ($form.find('#resource_id').is('[required]') && !$form.find('#resource_id').val()) {
				errors.push('Please select a guide/resource');
			}
			if (errors.length) {
				alert(errors.join('\n'));
				return false;
			}
			return true;
		}
	};

	$(document).ready(function() {
		WCBookingCalendar.init();
	});
})(jQuery);
