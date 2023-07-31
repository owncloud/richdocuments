/*global OC, $ */

var documentsSettings = {

	save : function() {
		$('#wopi_apply').attr('disabled', true);
		var data = {
			wopi_url  : $('#wopi_url').val().replace(/\/$/, '')
		};

		OC.msg.startAction('#documents-admin-msg', t('richdocuments', 'Saving...'));
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			data,
			documentsSettings.afterSave
		);
	},

	saveGroups: function(groups) {
		var data = {
			'edit_groups': groups
		};

		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			data
		);
	},

	saveDocFormat: function(format) {
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
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
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			data,
			documentsSettings.afterSaveTestWopi
		);
	},

	saveWebroot: function(value) {
		var data = {
			'canonical_webroot': value
		};

		console.log('saving new webroot: ' + value);
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			data
		);
	},

	saveMenuOption: function(value) {
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			{ 'menu_option': value }
		);
	},

	saveWatermarkText: function(value) {
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			{ 'watermark_text': value }
		);

		OC.Notification.showTemporary(t('richdocuments', 'Saved watermark'), {timeout: 2});
	},

	saveSecureViewOption: function(value) {
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			{ 'secure_view_option': value }
		);
	},

	saveSecureViewOpenActionDefault: function(value) {
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			{ 'secure_view_open_action_default': value }
		);
	},

	saveCanPrintDefaultOption: function(value) {
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			{ 'secure_view_can_print_default': value }
		);
	},

	saveHasWatermarkDefaultOption: function(value) {
		$.post(
			OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
			{ 'secure_view_has_watermark_default': value }
		);
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

	initialize: function() {
		documentsSettings.initEditGroups();
		documentsSettings.initTestWopiServer();

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
			
			page.find('#enable-open-action-with-secure-view-default').toggleClass('hidden', !this.checked);
			page.find('#enable-watermark-section').toggleClass('hidden', !this.checked);
			page.find('#enable-share-attributes-defaults').toggleClass('hidden', !this.checked);

			documentsSettings.saveSecureViewOption(this.checked);
			if (this.checked) {
				var val = $('#secure-view-watermark').val();
				documentsSettings.saveWatermarkText(val);
			}
		});

		$(document).on('change', '#enable_secure_view_open_action_default_cb-richdocuments', function() {
			documentsSettings.saveSecureViewOpenActionDefault(this.checked);
		});

		$(document).on('change', '#secure_view_has_watermark_default_option_cb-richdocuments', function() {
			var page = $(this).parent();

			// if secure-view checkbox got changed make sure print checkbox is checked
			// and if secure-view unchecked print is disabled (click action disabled)
			page.find('#secure_view_can_print_default_option_cb-richdocuments')
				.prop('checked', true);
			if (!this.checked) {
				page.find('#secure_view_can_print_default_option_cb-richdocuments')
					.prop('disabled', true);
			} else {
				page.find('#secure_view_can_print_default_option_cb-richdocuments')
					.prop('disabled', false);
			}

			// save secure-view (changed) and print (true) options
			documentsSettings.saveCanPrintDefaultOption(true);
			documentsSettings.saveHasWatermarkDefaultOption(this.checked);
		});

		$(document).on('change', '#secure_view_can_print_default_option_cb-richdocuments', function() {
			documentsSettings.saveCanPrintDefaultOption(this.checked);
		});

		$(document).on('change', '#secure-view-watermark', function() {
			documentsSettings.saveWatermarkText(this.value);
		});
	}
};

$(document).ready(function(){
	documentsSettings.initialize();
});
