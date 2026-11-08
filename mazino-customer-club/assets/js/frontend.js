/**
 * Mazino Customer Club – frontend form.
 */
(function () {
	'use strict';

	var DATA = window.MCC_DATA || {};
	var I18N = DATA.i18n || {};

	var PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
	var ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

	function toEnglishDigits(value) {
		return String(value === null || value === undefined ? '' : value).replace(/[۰-۹٠-٩]/g, function (character) {
			var index = PERSIAN_DIGITS.indexOf(character);

			if (index > -1) {
				return String(index);
			}

			return String(ARABIC_DIGITS.indexOf(character));
		});
	}

	function toPersianDigits(value) {
		return String(value).replace(/\d/g, function (digit) {
			return PERSIAN_DIGITS.charAt(parseInt(digit, 10));
		});
	}

	function normalizePhone(value) {
		var digits = toEnglishDigits(value).replace(/\D+/g, '');

		if (digits.indexOf('0098') === 0) {
			digits = '0' + digits.slice(4);
		} else if (digits.length === 12 && digits.indexOf('98') === 0) {
			digits = '0' + digits.slice(2);
		} else if (digits.length === 10 && digits.charAt(0) === '9') {
			digits = '0' + digits;
		}

		return digits;
	}

	function isValidMobile(phone) {
		return /^09\d{9}$/.test(phone);
	}

	function isLeapJalali(year) {
		var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
		var jp = breaks[0];
		var jump = 0;
		var index;
		var remaining;
		var yearInCycle;

		for (index = 1; index < breaks.length; index++) {
			var current = breaks[index];
			jump = current - jp;

			if (year < current) {
				break;
			}

			jp = current;
		}

		yearInCycle = year - jp;

		if (jump - yearInCycle < 6) {
			yearInCycle = yearInCycle - jump + Math.floor((jump + 4) / 33) * 33;
		}

		remaining = ((yearInCycle + 1) % 33) - 1;

		return remaining % 4 === 0;
	}

	function daysInMonth(year, month) {
		if (month <= 6) {
			return 31;
		}

		if (month <= 11) {
			return 30;
		}

		return isLeapJalali(year) ? 30 : 29;
	}

	function each(root, selector, callback) {
		var nodes = root.querySelectorAll(selector);
		var index;

		for (index = 0; index < nodes.length; index++) {
			callback(nodes[index], index);
		}
	}

	function setFieldError(field, message) {
		var wrapper = field.closest ? field.closest('.mcc-field') : null;

		if (!wrapper) {
			return;
		}

		var error = wrapper.querySelector('.mcc-error');

		wrapper.classList.toggle('has-error', Boolean(message));

		if (error) {
			error.textContent = message || '';
		}

		field.setAttribute('aria-invalid', message ? 'true' : 'false');
	}

	function initForm(form) {
		if (form.getAttribute('data-mcc-ready') === '1') {
			return;
		}

		var wrap = form.closest ? form.closest('.mcc-wrap') : null;

		if (!wrap) {
			return;
		}

		form.setAttribute('data-mcc-ready', '1');

		var success = wrap.querySelector('.mcc-success');
		var alertBox = form.querySelector('.mcc-alert');
		var button = form.querySelector('.mcc-btn');
		var label = form.querySelector('.mcc-btn-label');
		var defaultLabel = label ? label.textContent : '';
		var daySelect = form.querySelector('select[data-part="day"]');
		var monthSelect = form.querySelector('select[data-part="month"]');
		var yearSelect = form.querySelector('select[data-part="year"]');
		var sourceInput = form.querySelector('input[name="source"]');
		var submitting = false;
		var enabled = (form.getAttribute('data-fields') || '').split(',');

		if (sourceInput) {
			sourceInput.value = window.location.href;
		}

		function enabledField(name) {
			return enabled.indexOf(name) > -1;
		}

		function refreshDays() {
			if (!daySelect || !monthSelect) {
				return;
			}

			var month = parseInt(toEnglishDigits(monthSelect.value), 10) || 0;
			var year = parseInt(toEnglishDigits(yearSelect ? yearSelect.value : '0'), 10) || 0;
			var limit = month ? daysInMonth(year || 1400, month) : 31;
			var current = parseInt(toEnglishDigits(daySelect.value), 10) || 0;

			while (daySelect.options.length > 1) {
				daySelect.remove(1);
			}

			for (var day = 1; day <= limit; day++) {
				var option = document.createElement('option');

				option.value = String(day);
				option.textContent = toPersianDigits(day);
				daySelect.appendChild(option);
			}

			if (current && current <= limit) {
				daySelect.value = String(current);
			}
		}

		if (monthSelect) {
			monthSelect.addEventListener('change', refreshDays);

			if (monthSelect.value) {
				refreshDays();
			}
		}

		if (yearSelect) {
			yearSelect.addEventListener('change', refreshDays);
		}

		function clearErrors() {
			each(form, '.mcc-field', function (field) {
				field.classList.remove('has-error');

				var error = field.querySelector('.mcc-error');

				if (error) {
					error.textContent = '';
				}
			});

			if (alertBox) {
				alertBox.classList.remove('is-visible');
				alertBox.textContent = '';
			}
		}

		function showAlert(message) {
			if (!alertBox) {
				return;
			}

			alertBox.textContent = message;
			alertBox.classList.add('is-visible');
		}

		function validate() {
			var firstInvalid = null;

			function mark(field, message) {
				setFieldError(field, message);

				if (!firstInvalid) {
					firstInvalid = field;
				}
			}

			if (enabledField('name')) {
				var nameInput = form.querySelector('input[name="name"]');

				if (nameInput) {
					var name = nameInput.value.replace(/\s+/g, ' ').trim();

					if (name.length === 0) {
						mark(nameInput, I18N.required);
					} else if (name.length < 2) {
						mark(nameInput, I18N.nameShort);
					}
				}
			}

			if (enabledField('phone')) {
				var phoneInput = form.querySelector('input[name="phone"]');

				if (phoneInput) {
					var phone = normalizePhone(phoneInput.value);

					if (!phone) {
						mark(phoneInput, I18N.required);
					} else if (!isValidMobile(phone)) {
						mark(phoneInput, I18N.phone);
					} else {
						phoneInput.value = phone;
					}
				}
			}

			if (enabledField('birth')) {
				var birthWrapper = form.querySelector('.mcc-field[data-field="birth"]');
				var birthField = birthWrapper ? birthWrapper.querySelector('select') : null;
				var day = daySelect ? daySelect.value : '';
				var month = monthSelect ? monthSelect.value : '';
				var year = yearSelect ? yearSelect.value : '';

				if (birthField) {
					if (!day || !month || !year) {
						mark(birthField, I18N.birth);
					} else if (parseInt(day, 10) > daysInMonth(parseInt(year, 10), parseInt(month, 10))) {
						mark(birthField, I18N.birth);
					}
				}
			}

			if (enabledField('consent')) {
				var consent = form.querySelector('input[name="consent"]');

				if (consent && !consent.checked) {
					mark(consent, I18N.consent || I18N.required);
				}
			}

			if (firstInvalid) {
				if (firstInvalid.focus) {
					firstInvalid.focus({ preventScroll: true });
				}

				if (firstInvalid.scrollIntoView) {
					firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			}

			return !firstInvalid;
		}

		function setLoading(isLoading) {
			submitting = isLoading;

			if (!button) {
				return;
			}

			button.disabled = isLoading;
			button.classList.toggle('is-loading', isLoading);

			if (label) {
				label.textContent = isLoading ? I18N.sending || defaultLabel : defaultLabel;
			}
		}

		function showSuccess() {
			var redirect = form.getAttribute('data-redirect') || '';

			if (redirect) {
				window.location.href = redirect;
				return;
			}

			form.hidden = true;

			if (success) {
				success.hidden = false;
				success.setAttribute('tabindex', '-1');

				var title = success.querySelector('.mcc-success-title');

				if (title) {
					title.setAttribute('tabindex', '-1');
					title.focus({ preventScroll: true });
				}

				if (success.scrollIntoView) {
					success.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			}
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			if (submitting) {
				return;
			}

			clearErrors();

			if (!validate()) {
				return;
			}

			var body = new FormData(form);
			var xhr = new XMLHttpRequest();

			setLoading(true);

			xhr.open('POST', DATA.ajaxUrl || '', true);

			xhr.onload = function () {
				setLoading(false);

				var response = null;

				try {
					response = JSON.parse(xhr.responseText);
				} catch (error) {
					response = null;
				}

				if (!response) {
					showAlert(I18N.network);
					return;
				}

				if (response.success) {
					showSuccess();
					return;
				}

				var data = response.data || {};
				var duplicateMessage = form.getAttribute('data-duplicate') || '';

				if (data.code === 'duplicate' && duplicateMessage) {
					showAlert(duplicateMessage);
				} else if (data.message) {
					showAlert(data.message);
				} else {
					showAlert(form.getAttribute('data-error') || I18N.generic);
				}
			};

			xhr.onerror = function () {
				setLoading(false);
				showAlert(I18N.network);
			};

			xhr.send(body);
		});

		each(form, '.mcc-input, .mcc-select', function (field) {
			field.addEventListener('input', function () {
				setFieldError(field, '');
			});
		});

		var consentInput = form.querySelector('input[name="consent"]');

		if (consentInput) {
			consentInput.addEventListener('change', function () {
				setFieldError(consentInput, '');
			});
		}

		var again = wrap.querySelector('.mcc-again');

		if (again) {
			again.addEventListener('click', function () {
				form.reset();
				form.hidden = false;
				clearErrors();
				refreshDays();

				if (sourceInput) {
					sourceInput.value = window.location.href;
				}

				if (success) {
					success.hidden = true;
				}

				var firstInput = form.querySelector('.mcc-input');

				if (firstInput) {
					firstInput.focus();
				}
			});
		}
	}

	function boot() {
		var forms = document.querySelectorAll('form[data-mcc-form]');
		var index;

		for (index = 0; index < forms.length; index++) {
			initForm(forms[index]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/widget', boot);
	} else {
		window.addEventListener('elementor/frontend/init', function () {
			if (window.elementorFrontend && window.elementorFrontend.hooks) {
				window.elementorFrontend.hooks.addAction('frontend/element_ready/widget', boot);
			}
		});
	}
})();
