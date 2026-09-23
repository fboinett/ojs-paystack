(function () {
	function ensure() {
		var container = document.getElementById('stageTabs');
		if (!container) {
			return false;
		}
		var ul = container.querySelector('ul');
		if (!ul) {
			return false;
		}
		if (!ul.querySelector('li.pkp_workflow_paystack')) {
			var li = document.createElement('li');
			li.className = 'pkp_workflow_paystack stageIdPayment';
			var a = document.createElement('a');
			a.setAttribute('href', '#paystackPaymentPanel');
			a.textContent = (window.pkpPaystackStage && window.pkpPaystackStage.label) || 'Payment';
			li.appendChild(a);
			var copy = ul.querySelector('li.pkp_workflow_editorial');
			if (copy) {
				ul.insertBefore(li, copy);
			} else {
				ul.appendChild(li);
			}
		}
		if (!document.getElementById('paystackPaymentPanel')) {
			var config = window.pkpPaystackStage || {};
			var panel = document.createElement('div');
			panel.id = 'paystackPaymentPanel';
			panel.className = 'paystack-workflow-panel';
			panel.innerHTML = config.panelHtml || '<div class="paystack-workflow"><h2>Payment</h2><p><strong>Status:</strong> Waiting for the editor to request payment</p></div>';
			container.appendChild(panel);
		}
		if (window.jQuery) {
			var $tabs = window.jQuery('#stageTabs');
			if ($tabs.hasClass('ui-tabs')) {
				try { $tabs.tabs('refresh'); } catch (e) {}
			}
		}
		return true;
	}

	var tries = 0;
	var timer = setInterval(function () {
		tries += 1;
		if (ensure() && tries > 2) {
			clearInterval(timer);
		}
		if (tries > 80) {
			clearInterval(timer);
		}
	}, 250);
	if (document.readyState !== 'loading') {
		ensure();
	}
})();
