/**
 * Mazino Customer Club – SMS composer behaviour.
 */
(function () {
	'use strict';

	var PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

	function toPersian(value) {
		return String(value).replace(/\d/g, function (digit) {
			return PERSIAN_DIGITS.charAt(parseInt(digit, 10));
		});
	}

	function parts(text) {
		var length = text.length;
		var unicode = /[^\x00-\x7F]/.test(text);

		if (!length) {
			return 0;
		}

		if (unicode) {
			return length <= 70 ? 1 : Math.ceil(length / 67);
		}

		return length <= 160 ? 1 : Math.ceil(length / 153);
	}

	function parseJson(value, fallback) {
		try {
			var parsed = JSON.parse(value);

			return parsed === null ? fallback : parsed;
		} catch (error) {
			return fallback;
		}
	}

	function init() {
		var group = document.querySelector('[data-mcc-group]');
		var manualWrap = document.querySelector('[data-mcc-manual]');

		if (group && manualWrap) {
			var syncManual = function () {
				manualWrap.hidden = 'manual' !== group.value;
			};

			group.addEventListener('change', syncManual);
			syncManual();
		}

		var schedule = document.querySelector('[data-mcc-schedule]');
		var scheduleFields = document.querySelector('[data-mcc-schedule-fields]');

		if (schedule && scheduleFields) {
			var syncSchedule = function () {
				scheduleFields.hidden = !schedule.checked;
			};

			schedule.addEventListener('change', syncSchedule);
			syncSchedule();
		}

		var message = document.querySelector('[data-mcc-message]');
		var chars = document.querySelector('[data-mcc-chars]');
		var partsLabel = document.querySelector('[data-mcc-parts]');

		if (message && chars && partsLabel) {
			var syncCounter = function () {
				var value = message.value || '';

				chars.textContent = toPersian(value.length) + ' کاراکتر';
				partsLabel.textContent = toPersian(Math.max(0, parts(value))) + ' پیامک';
			};

			message.addEventListener('input', syncCounter);
			syncCounter();
		}

		var inserts = document.querySelectorAll('.mcc-insert');

		for (var i = 0; i < inserts.length; i++) {
			inserts[i].addEventListener('click', function () {
				if (!message) {
					return;
				}

				var token = this.getAttribute('data-token') || '';
				var start = message.selectionStart || message.value.length;
				var end = message.selectionEnd || message.value.length;

				message.value = message.value.slice(0, start) + token + message.value.slice(end);
				message.focus();
				message.selectionStart = message.selectionEnd = start + token.length;

				message.dispatchEvent(new Event('input'));
			});
		}

		// ---- template picker -------------------------------------------------

		var templateSelect = document.querySelector('[data-mcc-template]');
		var paramsBox = document.querySelector('[data-mcc-params]');
		var preview = document.querySelector('[data-mcc-preview]');

		if (templateSelect && paramsBox) {
			var prefix = paramsBox.getAttribute('data-prefix') || 'params';
			var saved = parseJson(paramsBox.getAttribute('data-values') || '{}', {});
			var first = true;

			var collect = function () {
				var values = {};
				var inputs = paramsBox.querySelectorAll('input');

				for (var index = 0; index < inputs.length; index++) {
					var match = inputs[index].name.match(/\[(.+)\]/);

					if (match) {
						values[match[1]] = inputs[index].value;
					}
				}

				return values;
			};

			var render = function () {
				var option = templateSelect.options[templateSelect.selectedIndex];
				var params = option ? parseJson(option.getAttribute('data-params') || '[]', []) : [];
				var values = first ? saved : collect();

				first = false;
				paramsBox.innerHTML = '';

				for (var index = 0; index < params.length; index++) {
					var param = params[index];
					var row = document.createElement('div');
					var label = document.createElement('label');
					var input = document.createElement('input');

					row.className = 'mcc-param';

					label.className = 'mcc-label';
					label.textContent = param.name + (param.description ? ' — ' + param.description : '');

					input.type = 'text';
					input.className = 'mcc-input';
					input.name = prefix + '[' + param.name + ']';
					input.value = param.auto ? param.auto : (values[param.name] || '');
					input.placeholder = 'مثلاً {name}';
					input.dir = 'rtl';

					if (param.auto) {
						input.readOnly = true;
						input.classList.add('mcc-input-auto');
						label.textContent = param.name + ' — خودکار';
					}

					row.appendChild(label);
					row.appendChild(input);
					paramsBox.appendChild(row);
				}

				if (preview) {
					var text = option ? (option.getAttribute('data-text') || '') : '';

					preview.hidden = !text || !templateSelect.value;
					preview.textContent = text ? 'متن قالب: ' + text : '';
				}
			};

			templateSelect.addEventListener('change', render);
			render();
		}

		var deletes = document.querySelectorAll('.mcc-row-link.mcc-delete');

		for (var j = 0; j < deletes.length; j++) {
			deletes[j].addEventListener('click', function (event) {
				if (!window.confirm('این کمپین و سابقهٔ آن حذف شود؟')) {
					event.preventDefault();
				}
			});
		}

		// ---- single recipient modal ------------------------------------------

		var modal = document.querySelector('[data-mcc-modal]');

		if (modal) {
			var triggers = document.querySelectorAll('.mcc-send-one');
			var toLabel = modal.querySelector('[data-mcc-to]');

			var openModal = function (button) {
				var mobile = button.getAttribute('data-mobile') || '';
				var name = button.getAttribute('data-name') || '';

				modal.hidden = false;

				modal.querySelector('[name="mobile"]').value = mobile;
				modal.querySelector('[name="name"]').value = name;
				modal.querySelector('[name="member_id"]').value = button.getAttribute('data-id') || '0';

				if (toLabel) {
					toLabel.textContent = name ? name + ' — ' + mobile : mobile;
				}

				document.body.classList.add('mcc-modal-open');
			};

			var closeModal = function () {
				modal.hidden = true;
				document.body.classList.remove('mcc-modal-open');
			};

			for (var t = 0; t < triggers.length; t++) {
				triggers[t].addEventListener('click', function () {
					openModal(this);
				});
			}

			var closers = modal.querySelectorAll('[data-mcc-close]');

			for (var c = 0; c < closers.length; c++) {
				closers[c].addEventListener('click', closeModal);
			}

			document.addEventListener('keydown', function (event) {
				if ('Escape' === event.key && !modal.hidden) {
					closeModal();
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
