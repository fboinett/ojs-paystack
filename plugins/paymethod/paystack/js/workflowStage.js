(function ($) {
	function panelMarkup(config) {
		if (config.panelHtml) {
			return config.panelHtml;
		}
		var status = config.statusLabel || '';
		var html = '<div class="paystack-workflow">';
		html += '<h2>' + $('<div/>').text(config.heading || config.label).html() + '</h2>';
		html += '<div class="paystack-workflow__status paystack-workflow__status--' + $('<div/>').text(config.status || '').html() + '">';
		html += '<p><strong>' + $('<div/>').text(config.feeLabel || 'Amount due').html() + ':</strong> ';
		html += $('<div/>').text(config.amountFormatted || '').html() + '</p>';
		html += '<p><strong>' + $('<div/>').text(config.statusTitle || 'Status').html() + ':</strong> ';
		html += $('<div/>').text(status).html() + '</p></div>';
		if (config.help) {
			html += '<p>' + $('<div/>').text(config.help).html() + '</p>';
		}
		if (config.canPay && config.payUrl) {
			html += '<p><a class="pkpButton" href="' + $('<div/>').text(config.payUrl).html() + '">';
			html += $('<div/>').text(config.payLabel || 'Pay now').html() + '</a></p>';
		}
		html += '</div>';
		return html;
	}

	function insertStage(config) {
		var $container = $('#stageTabs');
		if (!$container.length) {
			return false;
		}
		var $ul = $container.children('ul').first();
		if (!$ul.length) {
			return false;
		}

		if (!$ul.children('li.pkp_workflow_paystack').length) {
			var $review = $ul.children(
				'li.pkp_workflow_externalReview, li.pkp_workflow_internalReview, li.pkp_workflow_review'
			).last();
			var $copy = $ul.children('li.pkp_workflow_editorial');
			var $li = $('<li/>', {
				'class': 'pkp_workflow_paystack stageIdPayment' +
					(config.status === 'due' ? ' initiated paystack-stage--awaiting' : ' initiated')
			});
			var $a = $('<a/>', {href: '#paystackPaymentPanel'}).text(config.label);
			if (config.status === 'due') {
				$a.append($('<span/>', {'class': 'paystack-stage-badge'}).text(config.statusLabel));
			}
			$li.append($a);
			if ($review.length) {
				$review.after($li);
			} else if ($copy.length) {
				$copy.before($li);
			} else {
				$ul.append($li);
			}
		}

		if (!$container.children('#paystackPaymentPanel').length) {
			$container.append(
				$('<div/>', {id: 'paystackPaymentPanel', 'class': 'ui-tabs-panel paystack-workflow-panel'})
					.html(panelMarkup(config))
			);
		}

		try {
			if ($container.hasClass('ui-tabs')) {
				$container.tabs('refresh');
				if (config.autoSelect) {
					var index = $ul.children('li').index($ul.children('li.pkp_workflow_paystack'));
					if (index >= 0) {
						$container.tabs('option', 'active', index);
					}
				}
			}
		} catch (e) {
			// PKP tab handler may not expose tabs() until later.
		}
		return true;
	}

	function boot() {
		var config = window.pkpPaystackStage;
		if (!config) {
			return;
		}
		if (insertStage(config)) {
			return;
		}
		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			if (insertStage(config) || tries > 40) {
				clearInterval(timer);
			}
		}, 250);
	}

	$(boot);
})(jQuery);
