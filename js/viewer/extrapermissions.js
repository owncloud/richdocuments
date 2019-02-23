/*globals */

OC.Plugins.register('OC.Share.ShareItemModel', {
	attach: function (model) {

		if (_.isUndefined(model)) {
			throw 'missing OC.Share.ShareItemModel';
		}

		// Make can-download available permission as checkbox
		var mimetype = model.getFileInfo().getMimeType();
		if (odfViewer.isSupportedMimeType(mimetype)) {
			// With read-only permission set for the file, download will be disabled. Only viewing will be allowed
			var defaultValue = true;
			var incompatiblePermissions = [OC.PERMISSION_UPDATE];
			model.registerExtraSharePermission(
				"dav",
				"can-download",
				t('richdocuments', 'allow download'),
				defaultValue,
				incompatiblePermissions
			);
			model.registerExtraSharePermission(
				"collabora",
				"can-print",
				t('richdocuments', 'allow printing'),
				defaultValue,
				incompatiblePermissions
			);
		}
	}
});