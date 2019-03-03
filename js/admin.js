/*global OC, $ */

var documentsSettings = {
	_createExtApp: function() {
		var app1 = document.createElement('div');
		app1.setAttribute('class', 'external-app');

		var appname1 = document.createElement('input');
		appname1.setAttribute('class', 'external-apps-name');
		$(app1).append(appname1);

		var apptoken1 = document.createElement('input');
		apptoken1.setAttribute('class', 'external-apps-token');
		$(app1).append(apptoken1);

		var apptokenbutton = document.createElement('button');
		apptokenbutton.setAttribute('class', 'external-apps-gen-token-button');
		apptokenbutton.innerHTML = 'Generate Token';
		$(app1).append(apptokenbutton);

		var appremovebutton = document.createElement('button');
		appremovebutton.setAttribute('class', 'external-apps-remove-button');
		appremovebutton.innerHTML = 'Remove';
		$(app1).append(appremovebutton);

		return app1;
	},

	save : function() {
		$('#wopi_apply').attr('disabled', true);
		var data = {
			wopi_url  : $('#wopi_url').val().replace(/\/$/, '')
		};

		OC.msg.startAction('#documents-admin-msg', t('richdocuments', 'Saving...'));
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			data,
			documentsSettings.afterSave
		);
	},

	saveGroups: function(groups) {
		var data = {
			'edit_groups': groups
		};

		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			data
		);
	},

	saveDocFormat: function(format) {
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			{ 'doc_format': format }
		);
	},

	saveTestWopi: function(groups, server) {
		var data = {
			'test_wopi_url': server,
			'test_server_groups': groups
		};

		OC.msg.startAction('#test-documents-admin-msg', t('richdocuments', 'Saving...'));
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			data,
			documentsSettings.afterSaveTestWopi
		);
	},

	saveExternalApps: function(externalAppsData) {
		var data = {
			'external_apps': externalAppsData
		};

		OC.msg.startAction('#enable-external-apps-section-msg', t('richdocuments', 'Saving...'));
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			data,
			documentsSettings.afterSaveExternalApps
		);
	},

	saveWebroot: function(value) {
		var data = {
			'canonical_webroot': value
		};

		console.log('saving new webroot: ' + value);
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			data
		);
	},

	saveMenuOption: function(value) {
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			{ 'menu_option': value }
		);
	},

	saveWatermarkText: function(value) {
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			{ 'watermark_text': value }
		);

		OC.Notification.showTemporary(t('richdocuments', 'Saved watermark'), {timeout: 2});
	},

	saveSecureViewOption: function(value) {
		// FIXME: if version too low, throw error that this permission cannot be set for version <10.2 ?
		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			{ 'secure_view_option': value }
		);
	},

	saveAttributesOption: function(page) {
		// FIXME: if version too low, throw error that this permission cannot be set for version <10.2 ?
		var canDownload = page.find('#enable_can_download_option_cb-richdocuments').is(':checked');
		var canPrint = page.find('#enable_can_print_option_cb-richdocuments').is(':checked');

		$.post(
			OC.filePath('richdocuments', 'ajax', 'admin.php'),
			{ 'default_share_attributes': {
					'can_download': canDownload,
					'can_print': canPrint
				}
			}
		);
	},

	afterSaveExternalApps: function(response) {
		OC.msg.finishedAction('#enable-external-apps-section-msg', response);
	},

	afterSaveTestWopi: function(response) {
		$('#test_wopi_apply').attr('disabled', false);
		OC.msg.finishedAction('#test-documents-admin-msg', response);
	},

	afterSave : function(response){
		$('#wopi_apply').attr('disabled', false);
		OC.msg.finishedAction('#documents-admin-msg', response);
	},

	initEditGroups: function() {
		var groups = $('#edit_group_select').val();
		if (groups !== '') {
			OC.Settings.setupGroupsSelect($('#edit_group_select'));
			$('.edit-groups-enable').attr('checked', 'checked');
		} else {
			$('.edit-groups-enable').attr('checked', null);
		}
	},

	initTestWopiServer: function() {
		var groups = $(document).find('#test_server_group_select').val();
		var testserver = $(document).find('#test_wopi_url').val();

		if (groups === '' || testserver === '') {
			$('.test-server-enable').attr('checked', null);
		} else {
			OC.Settings.setupGroupsSelect($('#test_server_group_select'));
			$('.test-server-enable').attr('checked', 'checked');
		}
	},

	initExternalApps: function() {
		var externalAppsRaw = $(document).find('#external-apps-raw').val();
		var apps = externalAppsRaw.split(',');
		for (var i = 0; i < apps.length; ++i) {
			if (apps[i] !== '') {
				var app = apps[i].split(':');
				var app1 = this._createExtApp();
				// create a placeholder for adding new app
				$('#external-apps-section').append(app1);
				$(app1).find('.external-apps-name').val(app[0]);
				$(app1).find('.external-apps-token').val(app[1]);
			}
		}
	},

	initialize: function() {
		documentsSettings.initEditGroups();
		documentsSettings.initTestWopiServer();
		documentsSettings.initExternalApps();

		$('#wopi_apply').on('click', documentsSettings.save);

		$(document).on('change', '.test-server-enable', function() {
			var page = $(this).parent();
			var $select = page.find('#test_server_group_select');
			$select.val('');

			page.find('#test-server-section').toggleClass('hidden', !this.checked);
			if (this.checked) {
				OC.Settings.setupGroupsSelect($select, {
					placeholder: t('richdocuments', 'None')
				});
			} else {
				$select.select2('destroy');
				page.find('#test_wopi_url').val('');

				documentsSettings.saveTestWopi('', '');
			}
		});

		// destroy or create app name and token fields depending on whether the checkbox is on or off
		$(document).on('change', '#enable_external_apps_cb-richdocuments', function() {
			var page = $(this).parent();

			page.find('#enable-external-apps-section').toggleClass('hidden', !this.checked);
			if (this.checked) {
				var app1 = documentsSettings._createExtApp();
				$('#external-apps-section').append(app1);
			} else {
				page.find('.external-app').remove();
				page.find('#external-apps-raw').val('');
				documentsSettings.saveExternalApps('');
			}
		});

		$(document).on('click', '.external-apps-gen-token-button', function() {
			var appSection = $(this).parent();
			var appToken = appSection.find('.external-apps-token');

			// generate a random string
			var len = 3;
			var array = new Uint32Array(len);
			window.crypto.getRandomValues(array);
			var random = '';
			for (var i = 0; i < len; ++i) {
				random += array[i].toString(36);
			}

			// set the token in the field
			appToken.val(random);
		});

		$(document).on('click', '.external-apps-remove-button', function() {
			$(this).parent().remove();
		});

		$(document).on('click', '#external-apps-save-button', function() {
			// read all the data in input fields, save the data in input-raw and send to backedn
			var extAppsSection = $(this).parent();
			var apps = extAppsSection.find('.external-app');
			// convert all values into one single string and store it in raw input field
			// as well as send the data to server
			var raw = '';
			for (var i = 0; i < apps.length; ++i) {
				var appname = $(apps[i]).find('.external-apps-name');
				var apptoken = $(apps[i]).find('.external-apps-token');
				raw += appname.val() + ':' + apptoken.val() + ',';
			}

			extAppsSection.find('#external-apps-raw').val(raw);
			documentsSettings.saveExternalApps(raw);
		});

		$(document).on('click', '#external-apps-add-button', function() {
			// create a placeholder for adding new app
			var app1 = documentsSettings._createExtApp();
			$('#external-apps-section').append(app1);
		});

		$(document).on('click', '#test_wopi_apply', function() {
			var groups = $(this).parent().find('#test_server_group_select').val();
			var testserver = $(this).parent().find('#test_wopi_url').val();

			if (groups !== '' && testserver !== '') {
				documentsSettings.saveTestWopi(groups, testserver);
			} else {
				OC.msg.finishedError('#test-documents-admin-msg', 'Both fields required');
			}
		});

		$(document).on('change', '.doc-format-ooxml', function() {
			var ooxml = this.checked;
			documentsSettings.saveDocFormat(ooxml ? 'ooxml' : 'odf');
		});

		$(document).on('change', '#edit_group_select', function() {
			var element = $(this).parent().find('input.edit-groups-enable');
			var groups = $(this).val();
			documentsSettings.saveGroups(groups);
		});

		$(document).on('change', '.edit-groups-enable', function() {
			var $select = $(this).parent().find('#edit_group_select');
			$select.val('');

			if (this.checked) {
				OC.Settings.setupGroupsSelect($select, {
					placeholder: t('richdocuments', 'All')
				});
			} else {
				$select.select2('destroy');
			}

			$select.change();
		});

		$(document).on('change', '#enable_canonical_webroot_cb-richdocuments', function() {
			var page = $(this).parent();

			page.find('#enable-canonical-webroot-section').toggleClass('hidden', !this.checked);
			if (!this.checked) {
				documentsSettings.saveWebroot('');
			} else {
				var val = $('#canonical-webroot').val();
				if (val)
					documentsSettings.saveWebroot();
			}
		});

		$(document).on('change', '#canonical-webroot', function() {
			documentsSettings.saveWebroot(this.value);
		});

		$(document).on('change', '#enable_menu_option_cb-richdocuments', function() {
			var page = $(this).parent();
			documentsSettings.saveMenuOption(this.checked);
		});

		$(document).on('change', '#enable_secure_view_option_cb-richdocuments', function() {
			var page = $(this).parent();
			page.find('#enable-watermark-section').toggleClass('hidden', !this.checked);
			page.find('#enable-share-permissions-defaults').toggleClass('hidden', !this.checked);

			documentsSettings.saveSecureViewOption(this.checked);
			if (this.checked) {
				var val = $('#secure-view-watermark').val();
				documentsSettings.saveWatermarkText(val);
			}
		});

		$(document).on('change', '#enable_can_download_option_cb-richdocuments', function() {
			var page = $(this).parent();
			documentsSettings.saveAttributesOption(page);
		});
		$(document).on('change', '#enable_can_print_option_cb-richdocuments', function() {
			var page = $(this).parent();
			documentsSettings.saveAttributesOption(page);
		});

		$(document).on('change', '#secure-view-watermark', function() {
			documentsSettings.saveWatermarkText(this.value);
		});
	}
};

$(document).ready(function(){
	documentsSettings.initialize();
});
