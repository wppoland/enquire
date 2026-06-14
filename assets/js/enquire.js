/**
 * Enquire — front-end behaviour.
 *
 * Opens an accessible <dialog> from the trigger, submits the enquiry over AJAX,
 * and renders inline success/error feedback without leaving the page. No
 * dependencies (no jQuery). Progressive: if <dialog> is unsupported the form
 * still posts via the browser, but modern browsers all support it.
 */
(function () {
	'use strict';

	var data = window.enquireData || {};

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function setStatus(statusEl, message, type) {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = message || '';
		statusEl.classList.remove('enquire__status--error', 'enquire__status--success');
		if (type) {
			statusEl.classList.add('enquire__status--' + type);
		}
		statusEl.hidden = !message;
	}

	function initWidget(widget) {
		var dialog = widget.querySelector('[data-enquire-dialog]');
		var openBtn = widget.querySelector('[data-enquire-open]');
		var form = widget.querySelector('[data-enquire-form]');

		if (!dialog || !openBtn || !form) {
			return;
		}

		var statusEl = form.querySelector('[data-enquire-status]');
		var submitBtn = form.querySelector('[data-enquire-submit]');
		var lastFocused = null;

		function openDialog() {
			lastFocused = document.activeElement;
			setStatus(statusEl, '', null);
			if (typeof dialog.showModal === 'function') {
				dialog.showModal();
			} else {
				dialog.setAttribute('open', '');
			}
			var firstField = form.querySelector('input:not([type="hidden"]), textarea');
			if (firstField) {
				firstField.focus();
			}
		}

		function closeDialog() {
			if (typeof dialog.close === 'function') {
				dialog.close();
			} else {
				dialog.removeAttribute('open');
			}
			if (lastFocused && typeof lastFocused.focus === 'function') {
				lastFocused.focus();
			}
		}

		openBtn.addEventListener('click', openDialog);

		widget.querySelectorAll('[data-enquire-close]').forEach(function (btn) {
			btn.addEventListener('click', closeDialog);
		});

		// Close when clicking the backdrop (outside the panel).
		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) {
				closeDialog();
			}
		});

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			if (!data.ajaxUrl) {
				return;
			}

			// Native required-field validation first.
			if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
				return;
			}

			setStatus(statusEl, '', null);
			if (submitBtn) {
				submitBtn.setAttribute('aria-busy', 'true');
				submitBtn.disabled = true;
			}

			var payload = new FormData(form);

			fetch(data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: payload
			})
				.then(function (response) {
					return response.json().then(function (json) {
						return { ok: response.ok, json: json };
					});
				})
				.then(function (result) {
					var body = result.json || {};
					var message = (body.data && body.data.message) || '';

					if (body.success) {
						setStatus(statusEl, message || data.successMessage, 'success');
						form.reset();
					} else {
						setStatus(statusEl, message || data.errorMessage, 'error');
					}
				})
				.catch(function () {
					setStatus(statusEl, data.errorMessage, 'error');
				})
				.finally(function () {
					if (submitBtn) {
						submitBtn.removeAttribute('aria-busy');
						submitBtn.disabled = false;
					}
				});
		});
	}

	ready(function () {
		document.querySelectorAll('[data-enquire]').forEach(initWidget);
	});
})();
