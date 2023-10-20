/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2023, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
(function ($, OC, OCA) {

	OCA.Richdocuments = _.extend({}, OCA.Richdocuments);
	if (!OCA.Richdocuments) {
		/**
		 * @namespace
		 */
		OCA.Richdocuments = {};
	}

	OCA.Richdocuments.SettingsAdmin = {

		initSaveWopiServer: function() {
			$('button:button[id="wopi_url_save-richdocuments"]').click(function () {
				$('button:button[id="wopi_url_save-richdocuments"]').attr('disabled', true);
				var data = {
					wopi_url  : $('#wopi_url-richdocuments').val().replace(/\/$/, '')
				};

				OC.msg.startAction('#documents-admin-msg', t('richdocuments', 'Saving...'));
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					data,
					function (response) {
						OC.msg.finishedAction('#documents-admin-msg', response);
					},
					function (response) {
						OC.msg.finishedError('#documents-admin-msg', response.data.message);
					}
				);
				$('button:button[id="wopi_url_save-richdocuments"]').attr('disabled', false);
			});
		},

		initEnableTestServer: function() {

			var $select = $('input:hidden[id="test_server_group_select-richdocuments"]');
			var groups = $select.val();
			var testserver = $('input:text[id="test_wopi_url-richdocuments"]').val();

			if (groups === '' || testserver === '') {
				$('input:checkbox[id="test_server_enable-richdocuments"]').attr('checked', null);
			} else {
				OC.Settings.setupGroupsSelect($select);
				$('input:checkbox[id="test_server_enable-richdocuments"]').attr('checked', 'checked');
			}

			$('input:checkbox[id="test_server_enable-richdocuments"]').on('change', function() {
				var $select = $('input:hidden[id="test_server_group_select-richdocuments"]');
				$select.val('');

				$('#test_server_section-richdocuments').toggleClass('hidden', !this.checked);
				if (this.checked) {
					OC.Settings.setupGroupsSelect($select, {
						placeholder: t('richdocuments', 'None')
					});
				} else {
					$select.select2('destroy');
					$('input:text[id="test_wopi_url-richdocuments"]').val('');
			
					OCA.Richdocuments.SettingsAdmin.setAdminSettings(
						{
							'test_wopi_url': '',
							'test_server_groups': ''
						}
					);
				}
			});
		},

		initSaveWopiTestServer: function() {

			$('button:button[id="test_wopi_url_save-richdocuments"]').click(function () {
				var groups = $('input:hidden[id="test_server_group_select-richdocuments"]').val();
				var testserver = $('input:text[id="test_wopi_url-richdocuments"]').val();

				if (groups !== '' && testserver !== '') {

					OC.msg.startAction('#test-documents-admin-msg', t('richdocuments', 'Saving...'));
					$('button:button[id="test_wopi_url_save-richdocuments"]').attr('disabled', true);
					OCA.Richdocuments.SettingsAdmin.setAdminSettings(
						{
							'test_wopi_url': testserver,
							'test_server_groups': groups
						},
						function (response) {
							OC.msg.finishedAction('#test-documents-admin-msg', response);
						},
						function (response) {
							OC.msg.finishedError('#test-documents-admin-msg', response.data.message);
						}
					);
					$('button:button[id="test_wopi_url_save-richdocuments"]').attr('disabled', false);
				} else {
					OC.msg.finishedError('#test-documents-admin-msg', 'Both fields required');
				}
			});
		},

		initEnableEditOnlyForGroups: function() {

			var $select = $('input:hidden[id="edit_group_select-richdocuments"]');
			var groups = $select.val();
			if (groups !== '') {
				OC.Settings.setupGroupsSelect($select);
				$('input:checkbox[id="edit_groups_enable-richdocuments"]').attr('checked', 'checked');
			} else {
				$('input:checkbox[id="edit_groups_enable-richdocuments"]').attr('checked', null);
			}

			$('input:checkbox[id="edit_groups_enable-richdocuments"]').on('change', function() {
				var $select = $('input:hidden[id="edit_group_select-richdocuments"]');
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

			$('input:hidden[id="edit_group_select-richdocuments"]').on('change', function() {
				var $select = $('input:hidden[id="edit_group_select-richdocuments"]');
				var groups = $select.val();
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'edit_groups': groups
					}
				);
			});

		},

		initEnableOOXMLByDefaultForNewFiles: function() {

			$('input:checkbox[id="doc_format_ooxml_enable-richdocuments"]').on('change', function() {
				var ooxml = this.checked;
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'doc_format': ooxml ? 'ooxml' : 'odf'
					}
				);
			});
		},

		initEnableCanonicalWebroot: function() {
			$('input:checkbox[id="enable_canonical_webroot_cb-richdocuments"]').on('change', function() {
				$('#enable_canonical_webroot_section-richdocuments').toggleClass('hidden', !this.checked);
				if (!this.checked) {
					OCA.Richdocuments.SettingsAdmin.setAdminSettings(
						{
							'canonical_webroot': ''
						}
					);
				}
			});
	
			$('button:button[id="canonical_webroot_enable-richdocuments"]').click(function() {

				OC.msg.startAction('#cannonical-webroot-admin-msg', t('richdocuments', 'Saving...'));
				var val = $('input:text[id="canonical_webroot-richdocuments"]').val();
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'canonical_webroot': val
					},
					function (response) {
						OC.msg.finishedAction('#cannonical-webroot-admin-msg', response);
					},
					function (response) {
						OC.msg.finishedError('#cannonical-webroot-admin-msg', response.data.message);
					}
				);
			});
		},

		initEnableMenuOption: function() {

			$('input:checkbox[id="enable_menu_option_cb-richdocuments"]').on('change', function() {
				var enabled = this.checked;
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'menu_option': enabled
					}
				);
			});
		},

		initEnableSecureViewOption: function() {

			$('input:checkbox[id="enable_secure_view_option_cb-richdocuments"]').on('change', function() {
				var watermarkText = $('input:text[id="secure_view_watermark-richdocuments"]').val();
				if (this.checked && watermarkText === '') {
					// set default watermark text
					watermarkText = t('richdocuments', 'Strictly confidential. Only for {viewer-email}');
					$('input:text[id="secure_view_watermark-richdocuments"]').val(watermarkText);
				}

				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'secure_view_option': this.checked,
						'watermark_text': watermarkText
					}
				);
				$('#richdocuments-secure-view-preferences-section').toggleClass('hidden', !this.checked);
				$('#richdocuments-watermark-section').toggleClass('hidden', !this.checked);
			});

			$('button:button[id="save_secure_view_watermark-richdocuments"]').click(function() {
				var watermarkText = $('input:text[id="secure_view_watermark-richdocuments"]').val();
				
				OC.msg.startAction('#save-secure-view-watermark-admin-msg', t('richdocuments', 'Saving...'));
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'watermark_text': watermarkText
					},
					function (response) {
						OC.msg.finishedAction('#save-secure-view-watermark-admin-msg', response);
					},
					function (response) {
						OC.msg.finishedError('#save-secure-view-watermark-admin-msg', response.data.message);
					}
				);
			});

			$('input:checkbox[id="enable_secure_view_open_action_default_cb-richdocuments"]').on('change', function() {
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'secure_view_open_action_default': this.checked
					}
				);
			});

			$('input:checkbox[id="secure_view_can_print_default_option_cb-richdocuments"]').on('change', function() {
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'secure_view_can_print_default': this.checked
					}
				);
			});

			$('input:checkbox[id="secure_view_has_watermark_default_option_cb-richdocuments"]').on('change', function() {
				// on change of secure-view checkbox make sure print checkbox is checked to rollback to default
				$('input:checkbox[id="secure_view_can_print_default_option_cb-richdocuments"]').prop('checked', true);
				
				// if secure-view checkbox is unchecked disable print checkbox (click action disabled)
				// because this permission is only available together with secure-view permission
				if (!this.checked) {
					$('input:checkbox[id="secure_view_can_print_default_option_cb-richdocuments"]').prop('disabled', true);
				} else {
					$('input:checkbox[id="secure_view_can_print_default_option_cb-richdocuments"]').prop('disabled', false);
				}

				// save secure-view (changed) and print (true) options
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'secure_view_can_print_default': true,
						'secure_view_has_watermark_default': this.checked

					}
				);
			});
		},

		initEnableZotero: function() {

			$('input:checkbox[id="enable_zotero-richdocuments"]').on('change', function() {
				var enabled = this.checked;
				OCA.Richdocuments.SettingsAdmin.setAdminSettings(
					{
						'zotero': enabled
					}
				);
			});
		},

		setAdminSettings: function(data, doneCallback, failCallback) {
			$.post(
				OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
				{ settings : data }
			).done(function (response) {
				if (doneCallback) {
					doneCallback(response);
				}
			})
			.fail(function (jqXHR) {
				var response = JSON.parse(jqXHR.responseText);
				console.log(response);
				if (failCallback) {
					failCallback(response);
				}
			});
		},

	};

	$(document).ready(function () {
		OCA.Richdocuments.SettingsAdmin.initSaveWopiServer();
		OCA.Richdocuments.SettingsAdmin.initEnableTestServer();
		OCA.Richdocuments.SettingsAdmin.initSaveWopiTestServer();
		OCA.Richdocuments.SettingsAdmin.initEnableEditOnlyForGroups();
		OCA.Richdocuments.SettingsAdmin.initEnableOOXMLByDefaultForNewFiles();
		OCA.Richdocuments.SettingsAdmin.initEnableCanonicalWebroot();
		OCA.Richdocuments.SettingsAdmin.initEnableMenuOption();
		OCA.Richdocuments.SettingsAdmin.initEnableSecureViewOption();
		OCA.Richdocuments.SettingsAdmin.initEnableZotero();
	});

})(jQuery, OC, OCA);
