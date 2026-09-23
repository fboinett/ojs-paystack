(function ($) {
	function panelFromConfig(config) {
		if (config.panelHtml) {
			return config.panelHtml;
		}
		return '<div class="paystack-workflow"><h2>Payment</h2><p>Pending Payment</p></div>';
	}

	function ensureTab(config) {
		var $container = $('#stageTabs');
		if (!$container.length) {
			return false;
		}
		var $ul = $container.children('ul').first();
		if (!$ul.length) {
			return false;
		}

		if (!$ul.children('li.pkp_workflow_paystack').length) {
			var $review = $ul.children('li.pkp_workflow_externalReview, li.pkp_workflow_internalReview, li.pkp_workflow_review').last();
			var $copy = $ul.children('li.pkp_workflow_editorial');
			var $li = $('<li/>', { 'class': 'pkp_workflow_paystack stageIdPayment initiated' });
			$li.append($('<a/>', {
				href: '#paystackPaymentPanel',
				'class': 'paystack',
				text: config.label || 'Payment'
			}));
			if ($copy.length) {
				$copy.before($li);
			} else if ($review.length) {
				$review.after($li);
			} else {
				$ul.append($li);
			}
		}

		if (!$container.children('#paystackPaymentPanel').length) {
			$container.append(
				$('<div/>', { id: 'paystackPaymentPanel', 'class': 'paystack-workflow-panel' }).html(panelFromConfig(config))
			);
		}

		if ($container.hasClass('ui-tabs')) {
			try {
				$container.tabs('refresh');
			} catch (e) {}
			if (config.autoSelect) {
				var index = $ul.children('li').index($ul.children('li.pkp_workflow_paystack'));
				if (index >= 0) {
					try {
						$container.tabs('option', 'active', index);
					} catch (e2) {}
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
		var i;
		for (i = 0; i < ids.length; i++) {
			wanted[String(ids[i])] = true;
		}
		$('a[href]').each(function () {
			var href = String($(this).attr('href') || '');
			var match = href.match(/(?:workflow\/access|authorDashboard\/submission)\/(\d+)/);
			if (!match) {
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
		var config = window.pkpPaystackStage || null;
		relabelQueue();
		if (!config) {
			return;
		}
		if (ensureTab(config)) {
			return;
		}
		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			relabelQueue();
			if (ensureTab(config) || tries > 60) {
				clearInterval(timer);
			}
		}, 200);
	}

	$(boot);
})(jQuery);
