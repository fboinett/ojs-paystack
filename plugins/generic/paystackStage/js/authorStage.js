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
			var config = window.pkpPaystackStage || {};
			a.setAttribute('href', config.fetchUrl || '#paystackPaymentPanel');
			a.textContent = config.label || 'Payment';
			li.appendChild(a);
			var copy = ul.querySelector('li.pkp_workflow_editorial');
			if (copy) {
				ul.insertBefore(li, copy);
			} else {
				ul.appendChild(li);
			}
		}
		var stale = document.getElementById('paystackPaymentPanel');
		if (stale && stale.parentNode) {
			stale.parentNode.removeChild(stale);
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
