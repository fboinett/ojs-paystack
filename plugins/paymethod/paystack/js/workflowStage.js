(function () {
	function $(sel, root) {
		return (root || document).querySelector(sel);
	}
	function $all(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function ensureTab(config) {
		var container = document.getElementById('stageTabs');
		if (!container) {
			return false;
		}
		var ul = container.querySelector(':scope > ul') || container.querySelector('ul');
		if (!ul) {
			return false;
		}
		if (!ul.querySelector('li.pkp_workflow_paystack')) {
			var li = document.createElement('li');
			li.className = 'pkp_workflow_paystack stageIdPayment';
			var a = document.createElement('a');
			a.setAttribute('href', '#paystackPaymentPanel');
			a.className = '';
			a.textContent = config.label || 'Payment';
			li.appendChild(a);
			var copy = ul.querySelector('li.pkp_workflow_editorial');
			var review = ul.querySelector('li.pkp_workflow_externalReview, li.pkp_workflow_internalReview, li.pkp_workflow_review');
			if (copy) {
				ul.insertBefore(li, copy);
			} else if (review) {
				review.parentNode.insertBefore(li, review.nextSibling);
			} else {
				ul.appendChild(li);
			}
		}
		if (!document.getElementById('paystackPaymentPanel')) {
			var panel = document.createElement('div');
			panel.id = 'paystackPaymentPanel';
			panel.className = 'paystack-workflow-panel';
			panel.innerHTML = config.panelHtml || '<div class="paystack-workflow"><h2>Payment</h2><p>Pending Payment</p></div>';
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

	function relabelQueue() {
		var ids = window.pkpPaystackPendingIds || [];
		if (!ids.length) {
			return;
		}
		var wanted = {};
		ids.forEach(function (id) { wanted[String(id)] = true; });
		$all('a[href]').forEach(function (a) {
			var href = a.getAttribute('href') || '';
			var match = href.match(/(?:workflow\/access|authorDashboard\/submission)\/(\d+)/) || href.match(/[?&]submissionId=(\d+)/);
			if (!match || !wanted[match[1]]) {
				return;
			}
			var item = a.closest('.listPanel__item, .pkpListPanel__item, li');
			if (!item) {
				return;
			}
			$all('button, span, a', item).forEach(function (el) {
				if (el.children.length) {
					return;
				}
				var text = (el.textContent || '').trim();
				if (text === 'Copyediting' || text === 'Review' || text === 'Editorial') {
					el.textContent = 'Pending Payment';
				}
			});
		});
	}

	function boot() {
		relabelQueue();
		var config = window.pkpPaystackStage || null;
		if (!config) {
			return;
		}
		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			relabelQueue();
			if (ensureTab(config) && tries > 2) {
				clearInterval(timer);
			}
			if (tries > 80) {
				clearInterval(timer);
			}
		}, 250);
		ensureTab(config);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
