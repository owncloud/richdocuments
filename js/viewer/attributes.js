/*globals */

OC.Plugins.register('OC.Share.ShareItemModel', {
	attach: function (model) {

		if (_.isUndefined(model)) {
			throw 'missing OC.Share.ShareItemModel';
		}

		// Make can-download available permission as checkbox
		var mimetype = model.getFileInfo().getMimeType();
		var folderMimeType = 'httpd/unix-directory';
		if ((odfViewer.isSupportedMimeType(mimetype) || mimetype === folderMimeType) &&
			OC.appConfig.richdocuments && OC.appConfig.richdocuments.defaultShareAttributes) {
			// With read-only permission set for the file, download will be disabled. Only viewing will be allowed
			var incompatiblePermissions = [OC.PERMISSION_UPDATE];

			model.registerShareAttribute(
				"core",
				"can-download",
				t('richdocuments', 'allow download'),
				OC.appConfig.richdocuments.defaultShareAttributes.canDownload,
				incompatiblePermissions
			);
			model.registerShareAttribute(
				"richdocuments",
				"can-print",
				t('richdocuments', 'allow printing of documents'),
				OC.appConfig.richdocuments.defaultShareAttributes.canPrint,
				incompatiblePermissions
			);
		}
	}
});