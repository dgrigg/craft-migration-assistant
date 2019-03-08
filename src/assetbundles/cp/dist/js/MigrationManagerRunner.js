Craft.MigrationManagerRunner = Garnish.Base.extend({
	$graphic: null,
	$status: null,
	$errorDetails: null,
	$errors: null,
	data: null,

	init: function (data, nextAction) {
		this.$graphic = $('#graphic');
		this.$status = $('#status');
		this.data = data;
		this.postActionRequest(nextAction);
		this.$errorDetails = '';
	},

	updateStatus: function (msg) {
		this.$status.html(msg);
	},

	showError: function (msg) {
		this.updateStatus(msg);
		this.$graphic.addClass('error');
	},

	postActionRequest: function (action) {
		var data = {
			'data': this.data
		};

		Craft.postActionRequest(action, data, $.proxy(function (response, textStatus, jqXHR) {
			if (textStatus == 'success' && response.alive) {
				this.onSuccessResponse(response);
			} else {
				this.onErrorResponse(jqXHR);
			}

		}, this), {
			complete: $.noop
		});
	},

	onSuccessResponse: function (response) {
		if (response.data) {
			this.data = response.data;
		}

		if (response.errors) {
			this.$errors = response.errors;
		}

		if (response.errorDetails) {
			this.$errorDetails = response.errorDetails;
		}

		if (response.nextStatus) {
			this.updateStatus(response.nextStatus);
		}

		if (response.nextAction) {
			this.postActionRequest(response.nextAction);
		}

		if (response.finished) {
			var rollBack = false;

			if (response.rollBack) {
				rollBack = true;
			}

			this.onFinish(response.returnUrl, rollBack);
		}
	},

	onErrorResponse: function (jqXHR) {
		this.$graphic.addClass('error');
		var errorText =
			'<p>' + Craft.t('app', 'A fatal error has occurred:') + '</p>' +
			'<div id="error" class="code">' +
			'<p><strong class="code">' + Craft.t('app', 'Status:') + '</strong> ' + Craft.escapeHtml(jqXHR.statusText) + '</p>' +
			'<p><strong class="code">' + Craft.t('app', 'Response:') + '</strong> ' + Craft.escapeHtml(jqXHR.responseText) + '</p>' +
			'</div>' +
			'<a class="btn submit big" href="mailto:support@craftcms.com' +
			'?subject=' + encodeURIComponent('Craft update failure') +
			'&body=' + encodeURIComponent(
				'Describe what happened here.\n\n' +
				'-----------------------------------------------------------\n\n' +
				'Status: ' + jqXHR.statusText + '\n\n' +
				'Response: ' + jqXHR.responseText
			) +
			'">' +
			Craft.t('app', 'Send for help') +
			'</a>';

		this.updateStatus(errorText);
	},

	onFinish: function (returnUrl, rollBack) {

		if (this.$errorDetails) {
			this.$graphic.addClass('error');
			var errorText = Craft.t('app', 'Craft was unable to run this migration :(') + '<br /><p>';

			if (rollBack) {
				errorText += Craft.t('app', 'The site has been restored to the state it was in before the attempted migration.') + '</p><p>';
			} else {
				errorText += Craft.t('app', 'No files have been updated and the database has not been touched.') + '</p><p>';
			}

			errorText += '</p>';

			if (this.$errors) {
				errorText += '<ul>';
				for (var err in this.$errors) {
					errorText += '<li>' + this.$errors[err] + '</li>';
				}
			}
			errorText += '</ul>';;
			errorText += '<p>' + this.$errorDetails + '</p>'

			this.updateStatus(errorText);
		} else {
			this.updateStatus(Craft.t('app', 'All done!'));
			this.$graphic.addClass('success');

			// Redirect to the Dashboard in half a second
			setTimeout(function () {
				if (returnUrl) {
					window.location = Craft.getUrl(returnUrl);
				} else {
					window.location = Craft.getUrl('dashboard');
				}
			}, 1000);
		}
	}
});