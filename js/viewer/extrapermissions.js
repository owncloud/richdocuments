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
		}

		// Display notification when can-download gets disabled
		var oldUpdateShare = model.updateShare;
		model.updateShare = function (shareId, attrs, options) {
			oldUpdateShare.apply(this, [shareId, attrs, options]);
			_.each(attrs.extraPermissions, function(extraPermission) {
				if (extraPermission.app === "dav" &&
					extraPermission.name === "can-download" &&
					!extraPermission.enabled) {
					OC.Notification.showTemporary(t('richdocuments', 'Secure-view mode on the file has been enabled for this user'));
				}
			});
		};
	}
});