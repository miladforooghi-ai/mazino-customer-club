/**
 * Mazino Customer Club – admin scripts.
 */
(function () {
	'use strict';

	function init() {
		var all = document.querySelector('.mcc-check-all');

		if (all) {
			all.addEventListener('change', function () {
				var boxes = document.querySelectorAll('input[name="member[]"]');
				var index;

				for (index = 0; index < boxes.length; index++) {
					boxes[index].checked = all.checked;
				}
			});
		}

		var bulkForm = document.querySelector('.mcc-table-form');

		if (bulkForm) {
			bulkForm.addEventListener('submit', function (event) {
				var action = bulkForm.querySelector('select[name="action"]');

				if (!action || 'delete' !== action.value) {
					return;
				}

				var checked = bulkForm.querySelectorAll('input[name="member[]"]:checked');

				if (!checked.length) {
					event.preventDefault();
					window.alert('ابتدا حداقل یک عضو را انتخاب کنید.');
					return;
				}

				if (!window.confirm('اعضای انتخاب‌شده حذف شوند؟ این کار قابل بازگشت نیست.')) {
					event.preventDefault();
				}
			});
		}

		var links = document.querySelectorAll('[data-mcc-confirm]');

		for (var i = 0; i < links.length; i++) {
			links[i].addEventListener('click', function (event) {
				var message = this.getAttribute('data-mcc-confirm') || 'آیا مطمئن هستید؟';

				if (!window.confirm(message)) {
					event.preventDefault();
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
