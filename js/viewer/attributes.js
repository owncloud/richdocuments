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
			var secureViewCanDownload = {
				"scope": "permissions",
				"key": "download",
				"default": JSON.parse(OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanDownload),
				"label": t('richdocuments', 'can view without watermark'),
				"description": t('richdocuments', 'With this permission being' +
					' set, share receiver will be allowed to download file ' +
					' directly. Disabling this option will restrict receiver ' +
					' to viewing the document(s) in secure mode with watermark included'),
				"shareType" : [OC.Share.SHARE_TYPE_GROUP, OC.Share.SHARE_TYPE_USER],
				"incompatiblePermissions": [OC.PERMISSION_UPDATE],
				"incompatibleAttributes": []
			};
			model.registerShareAttribute(secureViewCanDownload);

			/** @type OC.Share.Types.RegisteredShareAttribute **/
			var secureViewCanPrint = {
				"scope": "richdocuments",
				"key": "print",
				"default": JSON.parse(OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanPrint),
				"label": t('richdocuments', 'can print/export (will include watermark)'),
				"description": t('richdocuments', 'With this permission being' +
					' set, share receiver will be able to print document(s) ' +
					' with watermark included'),
				"shareType" : [OC.Share.SHARE_TYPE_GROUP, OC.Share.SHARE_TYPE_USER],
				"incompatiblePermissions": [OC.PERMISSION_UPDATE],
				"incompatibleAttributes": [
					{
						"scope": "permissions",
						"key": "download",
						"enabled": true
					}
				]
			};
			model.registerShareAttribute(secureViewCanPrint);
		}
	}
});