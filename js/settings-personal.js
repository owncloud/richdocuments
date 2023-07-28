/*global OC, $ */

OC.Richdocuments = _.extend(OC.Richdocuments || {}, {
	updateZoteroAPIPrivateKey: function () {
		var zoteroAPIPrivateKey = $('input:text[id="changeAPIPrivateKey"]').val();
		OC.msg.startSaving('#richdocuments .msg');
		$.post(
			OC.generateUrl('/apps/richdocuments/ajax/settings/setPersonalSettings'),
			{
				zoteroAPIPrivateKey: zoteroAPIPrivateKey
			}
		).done(function (response) {
			OC.msg.finishedSuccess('#richdocuments .msg', response.data.message);
			$('button:button[name="submitChangeAPIPrivateKey"]').attr("disabled", "true");
		})
		.fail(function (jqXHR) {
			OC.msg.finishedError('#richdocuments .msg', JSON.parse(jqXHR.responseText).message);
		});
	}
});

$(document).ready(function () {

	// update api private key

	$('input:text[name="changeAPIPrivateKey"]').keyup(function (event) {
		var changeAPIPrivateKey = $('input:text[id="changeAPIPrivateKey"]').val();
		if (changeAPIPrivateKey !== '') {
			$('button:button[name="submitChangeAPIPrivateKey"]').removeAttr("disabled");
			if (event.which === 13) {
				OC.Richdocuments.updateZoteroAPIPrivateKey();
			}
		}
	});

	$('button:button[name="submitChangeAPIPrivateKey"]').click(function () {
		OC.Richdocuments.updateZoteroAPIPrivateKey();
	});

});
