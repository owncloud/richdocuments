/*globals */

OC.Plugins.register('OC.Share.ShareItemModel', {
	attach: function (model) {

		if (_.isUndefined(model)) {
			throw 'missing OC.Share.ShareItemModel';
		}

		// Register additional available share attributes
		var mimetype = model.getFileInfo().getMimeType();
		var folderMimeType = 'httpd/unix-directory';
		if ((odfViewer.isSupportedMimeType(mimetype) || mimetype === folderMimeType) &&
			OC.appConfig.richdocuments && OC.appConfig.richdocuments.defaultShareAttributes) {
			/** @type OC.Share.Types.RegisteredShareAttribute **/
			var secureViewEnabled = {
				"scope": "core",
				"name": "secure-view-enabled",
				"default": JSON.parse(OC.appConfig.richdocuments.defaultShareAttributes.secureViewEnabled),
				"label": t('richdocuments', 'secure viewing only'),
				"incompatiblePermissions": [OC.PERMISSION_UPDATE],
				"incompatibleAttributes": []
			};
			model.registerShareAttribute(secureViewEnabled);

			/** @type OC.Share.Types.RegisteredShareAttribute **/
			var secureViewCanPrint = {
				"scope": "richdocuments",
				"name": "secure-view-can-print",
				"default": JSON.parse(OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanPrint),
				"label": t('richdocuments', 'allow printing (will include watermarks)'),
				"incompatiblePermissions": [OC.PERMISSION_UPDATE],
				"incompatibleAttributes": [
					{
						"scope": "core",
						"name": "secure-view-enabled",
						"enabled": false
					}
				]
			};
			model.registerShareAttribute(secureViewCanPrint);
		}
	}
});