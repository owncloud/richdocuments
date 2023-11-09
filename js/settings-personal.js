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

	OCA.Richdocuments.SettingsPersonal = {

		initZoteroAPIPrivateKey: function () {
			$('input:text[id="change_zotero_key-richdocuments"]').keyup(function (event) {
				var zoteroAPIPrivateKey = $('input:text[id="change_zotero_key-richdocuments"]').val();
				if (zoteroAPIPrivateKey !== '') {
					$('button:button[id="save_zotero_key-richdocuments"]').removeAttr("disabled");
					if (event.which === 13) {
						OCA.Richdocuments.SettingsPersonal.updateZoteroAPIPrivateKey();
					}
				}
			});
		
			$('button:button[id="save_zotero_key-richdocuments"]').click(function () {
				OCA.Richdocuments.SettingsPersonal.updateZoteroAPIPrivateKey();
			});
		},
	
		updateZoteroAPIPrivateKey: function () {
			var zoteroAPIPrivateKey = $('input:text[id="change_zotero_key-richdocuments"]').val();
			OC.msg.startAction('#richdocuments-zotero-personal-msg', t('richdocuments', 'Saving...'));
	
			OCA.Richdocuments.SettingsPersonal.setPersonalSettings(
				{
					settings: {
						zoteroAPIPrivateKey: zoteroAPIPrivateKey
					}
				},
				function (response) {
					OC.msg.finishedSuccess('#richdocuments-zotero-personal-msg', response.data.message);
					$('button:button[id="save_zotero_key-richdocuments"]').attr("disabled", "true");
				},
				function (jqXHR) {
					OC.msg.finishedError('#richdocuments-zotero-personal-msg', JSON.parse(jqXHR.responseText).data.message);
				}
			);
		},
	
		setPersonalSettings: function(data, doneCallback, failCallback) {
			$.ajax(OC.generateUrl("apps/richdocuments/ajax/settings/setPersonalSettings"), {
				type: 'POST',
				data: JSON.stringify(data),
				contentType: 'application/json',
				dataType: 'json'
			}).done(function (response) {
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
		OCA.Richdocuments.SettingsPersonal.initZoteroAPIPrivateKey();
	});

})(jQuery, OC, OCA);
