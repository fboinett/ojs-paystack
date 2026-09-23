(function () {
	var saved = '';

	function panel() {
		return document.getElementById('paystackPaymentPanel');
	}

	function capture() {
		var node = panel();
		if (node && (node.textContent || '').replace(/\s+/g, '') !== '') {
			saved = node.innerHTML;
		}
	}

	function restore() {
		var node = panel();
		if (!node || !saved) {
			return;
		}
		if ((node.textContent || '').replace(/\s+/g, '') === '') {
			node.innerHTML = saved;
		}
	}

	function boot() {
		capture();
		restore();
		if (!window.jQuery) {
			return;
		}
		var $tabs = window.jQuery('#stageTabs');
		$tabs.on('tabsbeforeactivate tabsactivate tabsload', function () {
			window.setTimeout(restore, 0);
			window.setTimeout(restore, 200);
		});
		window.jQuery(document).on('click', '#stageTabs li.pkp_workflow_paystack a', function () {
			window.setTimeout(restore, 0);
			window.setTimeout(restore, 300);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
