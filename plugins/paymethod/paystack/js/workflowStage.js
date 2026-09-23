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
			a.setAttribute('href', config.fetchUrl || '#paystackPaymentPanel');
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
		} else if (config.fetchUrl) {
			var existing = ul.querySelector('li.pkp_workflow_paystack a');
			var keptPanel = document.getElementById('paystackPaymentPanel');
			var keepLocal = keptPanel && (keptPanel.textContent || '').replace(/\s+/g, '') !== '';
			if (existing && !keepLocal && existing.getAttribute('href') !== config.fetchUrl) {
				existing.setAttribute('href', config.fetchUrl);
			}
		}
		var existingPanel = document.getElementById('paystackPaymentPanel');
		if (existingPanel && (existingPanel.textContent || '').replace(/\s+/g, '') !== '') {
			var hashLink = ul.querySelector('li.pkp_workflow_paystack a');
			if (hashLink && hashLink.getAttribute('href') !== '#paystackPaymentPanel') {
				hashLink.setAttribute('href', '#paystackPaymentPanel');
			}
			return true;
		}
		if (window.jQuery && !ul.querySelector('li.pkp_workflow_paystack[data-paystack-ready]')) {
			var created = ul.querySelector('li.pkp_workflow_paystack');
			if (created) {
				created.setAttribute('data-paystack-ready', '1');
				var $tabs = window.jQuery('#stageTabs');
				if ($tabs.hasClass('ui-tabs')) {
					try { $tabs.tabs('refresh'); } catch (e) {}
				}
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
		var items = document.querySelectorAll('.listPanel__item, .pkpListPanel__item');
		Array.prototype.forEach.call(items, function (item) {
			if (item.closest && item.closest('#stageTabs')) {
				return;
			}
			var link = item.querySelector('a[href]');
			if (!link) {
				return;
			}
			var href = link.getAttribute('href') || '';
			var match = href.match(/(?:workflow\/access|authorDashboard\/submission)\/(\d+)/) || href.match(/[?&]submissionId=(\d+)/);
			if (!match || !wanted[match[1]]) {
				return;
			}
			var nodes = item.querySelectorAll('.pkpBadge, button, span');
			Array.prototype.forEach.call(nodes, function (el) {
				if (el.closest && el.closest('#stageTabs')) {
					return;
				}
				for (var i = 0; i < el.childNodes.length; i++) {
					var node = el.childNodes[i];
					if (node.nodeType !== 3) {
						continue;
					}
					var text = (node.nodeValue || '').trim();
					if (text === 'Review' || text === 'Copyediting' || text === 'Editorial') {
						node.nodeValue = node.nodeValue.replace(text, 'Pending Payment');
					}
				}
			});
		});
	}

	function boot() {
		relabelQueue();
		var config = window.pkpPaystackStage || null;
		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			relabelQueue();
			if (config) {
				ensureTab(config);
			}
			if (tries > 40) {
				clearInterval(timer);
			}
		}, 250);
		if (config) {
			ensureTab(config);
		}
		if (window.MutationObserver && document.body) {
			var observer = new MutationObserver(function () {
				relabelQueue();
			});
			observer.observe(document.body, {childList: true, subtree: true, characterData: true});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
