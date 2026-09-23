(function ($) {
	function activatePaymentTab() {
		var config = window.pkpPaystackStage || {};
		if (!config.autoSelect) {
			return true;
		}
		var $tabs = $('#stageTabs');
		if (!$tabs.length || !$tabs.hasClass('ui-tabs')) {
			return false;
		}
		var index = $tabs.children('ul').children('li.pkp_workflow_paystack').index();
		if (index < 0) {
			return false;
		}
		try {
			$tabs.tabs('option', 'active', index);
		} catch (e) {}
		return true;
	}

	function relabelQueue() {
		var ids = window.pkpPaystackPendingIds || [];
		if (!ids.length) {
			return;
		}
		var wanted = {};
		var i;
		for (i = 0; i < ids.length; i++) {
			wanted[String(ids[i])] = true;
		}
		$('a[href]').each(function () {
			var href = String($(this).attr('href') || '');
			var match = href.match(/(?:workflow\/access|authorDashboard\/submission)\/(\d+)/);
			if (!match && /[?&]submissionId=(\d+)/.test(href)) {
				match = href.match(/[?&]submissionId=(\d+)/);
			}
			if (!match || !wanted[match[1]]) {
				return;
			}
			var $item = $(this).closest('.listPanel__item, .pkpListPanel__item, li');
			if (!$item.length) {
				return;
			}
			$item.find('button, span, a').each(function () {
				if (this.children.length) {
					return;
				}
				var text = $.trim($(this).text());
				if (text === 'Copyediting' || text === 'Review' || text === 'Editorial') {
					$(this).text('Pending Payment');
				}
			});
		});
	}

	function boot() {
		relabelQueue();
		if (activatePaymentTab()) {
			return;
		}
		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			relabelQueue();
			if (activatePaymentTab() || tries > 40) {
				clearInterval(timer);
			}
		}, 250);
	}

	$(boot);
})(jQuery);
