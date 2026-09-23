(function () {
	function sourceHtml() {
		var source = document.getElementById('paystackPaymentSource');
		return source ? source.innerHTML : '';
	}

	function paymentPanel() {
		var link = document.querySelector('#stageTabs li.pkp_workflow_paystack a');
		if (!link) {
			return null;
		}
		var panelId = link.getAttribute('aria-controls');
		return panelId ? document.getElementById(panelId) : null;
	}

	function fillIfEmpty() {
		var panel = paymentPanel();
		var html = sourceHtml();
		if (!panel || !html) {
			return;
		}
		if ((panel.textContent || '').replace(/\s+/g, '') !== '') {
			return;
		}
		panel.innerHTML = html;
	}

	function boot() {
		fillIfEmpty();
		if (!window.jQuery) {
			return;
		}
		var $tabs = window.jQuery('#stageTabs');
		$tabs.on('tabsactivate tabsload', function () {
			window.setTimeout(fillIfEmpty, 50);
			window.setTimeout(fillIfEmpty, 400);
		});
		window.jQuery(document).on('click', '#stageTabs li.pkp_workflow_paystack a', function () {
			window.setTimeout(fillIfEmpty, 50);
			window.setTimeout(fillIfEmpty, 600);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
