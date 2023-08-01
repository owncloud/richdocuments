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

	OCA.RichdocumentsPersonalSettings = {

		initZoteroAPIPrivateKey: function () {
			$('input:text[name="changeAPIPrivateKey"]').keyup(function (event) {
				var zoteroAPIPrivateKey = $('input:text[id="changeAPIPrivateKey"]').val();
				if (zoteroAPIPrivateKey !== '') {
					$('button:button[name="submitChangeAPIPrivateKey"]').removeAttr("disabled");
					if (event.which === 13) {
						OCA.RichdocumentsPersonalSettings.updateZoteroAPIPrivateKey();
					}
				}
			});
		
			$('button:button[name="submitChangeAPIPrivateKey"]').click(function () {
				OCA.RichdocumentsPersonalSettings.updateZoteroAPIPrivateKey();
			});
		},
	
		updateZoteroAPIPrivateKey: function () {
			var zoteroAPIPrivateKey = $('input:text[id="changeAPIPrivateKey"]').val();
			OC.msg.startSaving('#richdocuments .msg');
	
			OCA.RichdocumentsPersonalSettings.setPersonalSettings(
				{
					zoteroAPIPrivateKey: zoteroAPIPrivateKey
				},
				function (response) {
					OC.msg.finishedSuccess('#richdocuments .msg', response.data.message);
					$('button:button[name="submitChangeAPIPrivateKey"]').attr("disabled", "true");
				},
				function (jqXHR) {
					OC.msg.finishedError('#richdocuments .msg', JSON.parse(jqXHR.responseText).data.message);
				}
			);
		},
	
		setPersonalSettings: function(data, doneCallback, failCallback) {
			$.post(
				OC.generateUrl("apps/richdocuments/ajax/settings/setPersonalSettings"),
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
		OCA.RichdocumentsPersonalSettings.initZoteroAPIPrivateKey();
	});

})(jQuery, OC, OCA);
