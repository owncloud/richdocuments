/*globals */

OC.Plugins.register('OC.Share.ShareItemModel', {
	attach: function (model) {

		if (_.isUndefined(model)) {
			throw 'missing OC.Share.ShareItemModel';
		}

		// Make can-download available permission as checkbox
		var mimetype = model.getFileInfo().getMimeType();
		var folderMimeType = 'httpd/unix-directory';
		if (odfViewer.isSupportedMimeType(mimetype) || mimetype === folderMimeType) {
			// With read-only permission set for the file, download will be disabled. Only viewing will be allowed
			$.get(
				OC.filePath('richdocuments', 'ajax', 'settings.php'),
				{},
				function(response) {
					var defaultValueCanDownload, defaultValueCanPrint;
					if (response.default_share_attributes) {
						defaultValueCanDownload = response.default_share_attributes.can_download;
						defaultValueCanPrint = response.default_share_attributes.can_print;
					} else {
						defaultValueCanDownload = true;
						defaultValueCanPrint = true;
					}

					var incompatiblePermissions = [OC.PERMISSION_UPDATE];
					model.registerShareAttribute(
						"core",
						"can-download",
						t('richdocuments', 'allow download'),
						defaultValueCanDownload,
						incompatiblePermissions
					);
					model.registerShareAttribute(
						"richdocuments",
						"can-print",
						t('richdocuments', 'allow printing of documents'),
						defaultValueCanPrint,
						incompatiblePermissions
					);
				}
			);

		}
	}
});