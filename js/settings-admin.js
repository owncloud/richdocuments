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

	OCA.RichdocumentsAdminSettings = {

		initSaveWopiServer: function() {
			$('button:button[id="wopi_url_save-richdocuments"]').click(function () {
				$('button:button[id="wopi_url_save-richdocuments"]').attr('disabled', true);
				var data = {
					wopi_url  : $('#wopi_url').val().replace(/\/$/, '')
				};

				OC.msg.startAction('#documents-admin-msg', t('richdocuments', 'Saving...'));
				OCA.RichdocumentsAdminSettings.setAdminSettings(
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
			
					OCA.RichdocumentsAdminSettings.setAdminSettings(
						{
							'test_wopi_url': '',
							'test_server_groups': ''
						},
						function (response) {
							OC.msg.finishedAction('#test-documents-admin-msg', response);
						},
						function (response) {
							OC.msg.finishedError('#test-documents-admin-msg', response.data.message);
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
					OCA.RichdocumentsAdminSettings.setAdminSettings(
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
				OCA.RichdocumentsAdminSettings.setAdminSettings(
					{
						'edit_groups': groups
					},
					function (response) {},
					function (response) {}
				);
			});

		},

		initEnableOOXMLByDefaultForNewFiles: function() {

			$('input:checkbox[id="doc_format_ooxml_enable-richdocuments"]').on('change', function() {
				var ooxml = this.checked;
				OCA.RichdocumentsAdminSettings.setAdminSettings(
					{
						'doc_format': ooxml ? 'ooxml' : 'odf'
					},
					function (response) {},
					function (response) {}
				);
			});
		},

		initEnableCanonicalWebroot: function() {
			//
			//'canonical_webroot': value

			$('input:checkbox[id="enable_canonical_webroot_cb-richdocuments"]').on('change', function() {
				$('#enable_canonical_webroot_section-richdocuments').toggleClass('hidden', !this.checked);
				if (!this.checked) {
					OCA.RichdocumentsAdminSettings.setAdminSettings(
						{
							'canonical_webroot': ''
						},
						function (response) {},
						function (response) {}
					);
				}
			});
	
			$('button:button[id="canonical_webroot_enable-richdocuments"]').click(function() {

				OC.msg.startAction('#cannonical-webroot-admin-msg', t('richdocuments', 'Saving...'));
				var val = $('input:text[id="canonical_webroot-richdocuments"]').val();
				OCA.RichdocumentsAdminSettings.setAdminSettings(
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
				OCA.RichdocumentsAdminSettings.setAdminSettings(
					{
						'menu_option': enabled
					},
					function (response) {},
					function (response) {}
				);
			});
		},

		setAdminSettings: function(data, doneCallback, failCallback) {
			$.post(
				OC.generateUrl("apps/richdocuments/ajax/settings/setAdminSettings"),
				data
			).done(function (response) {
				doneCallback(response);
			})
			.fail(function (jqXHR) {
				var response = JSON.parse(jqXHR.responseText);
				console.log(response);
				failCallback(response);
			});
		},

	};

	$(document).ready(function () {
		OCA.RichdocumentsAdminSettings.initSaveWopiServer();
		OCA.RichdocumentsAdminSettings.initEnableTestServer();
		OCA.RichdocumentsAdminSettings.initSaveWopiTestServer();
		OCA.RichdocumentsAdminSettings.initEnableEditOnlyForGroups();
		OCA.RichdocumentsAdminSettings.initEnableOOXMLByDefaultForNewFiles();
		OCA.RichdocumentsAdminSettings.initEnableMenuOption();
		OCA.RichdocumentsAdminSettings.initEnableCanonicalWebroot();
	});

})(jQuery, OC, OCA);
