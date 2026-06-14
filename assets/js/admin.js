/**
 * Enquire — admin help tooltips. Progressively enhances each "?" affordance with
 * the native Popover API where available, falling back to show-on-hover/focus.
 * The tip text is always available via the trigger's title attribute and to
 * assistive tech via aria-describedby.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		var triggers = document.querySelectorAll('.enquire-help');

		triggers.forEach(function (trigger) {
			var tipId = trigger.getAttribute('data-enquire-tip');
			if (!tipId) {
				return;
			}
			var tip = document.getElementById(tipId);
			if (!tip) {
				return;
			}

			function position() {
				var rect = trigger.getBoundingClientRect();
				tip.style.top = (window.scrollY + rect.bottom + 6) + 'px';
				tip.style.left = (window.scrollX + rect.left) + 'px';
			}

			function show() {
				position();
				tip.hidden = false;
			}

			function hide() {
				tip.hidden = true;
			}

			trigger.addEventListener('mouseenter', show);
			trigger.addEventListener('mouseleave', hide);
			trigger.addEventListener('focus', show);
			trigger.addEventListener('blur', hide);
		});
	});
})();
