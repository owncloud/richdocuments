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
			OC.appConfig.richdocuments &&
			OC.appConfig.richdocuments.secureViewAllowed &&
			OC.appConfig.richdocuments.defaultShareAttributes) {
            // Check if can download has been already added by other app,
			// as this is core permission
			if (model.getRegisteredShareAttribute("permissions", "download") === null) {
				/**
				 * With this permission being set, share receiver will be allowed
				 * to download file directly. Disabling this option will restrict
				 * receiver to viewing the document(s)
				 *
				 * @type OC.Share.Types.RegisteredShareAttribute
				 */
				var canDownload = {
					scope: "permissions",
					key: "download",
					default: JSON.parse(OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanDownload),
					label: t('richdocuments', 'can download'),
					shareType : [
						OC.Share.SHARE_TYPE_GROUP,
						OC.Share.SHARE_TYPE_USER
					],
					incompatiblePermissions: [
						OC.PERMISSION_UPDATE,
						OC.PERMISSION_CREATE,
						OC.PERMISSION_DELETE
					],
					requiredPermissions: [],
					incompatibleAttributes: []
				};
				model.registerShareAttribute(canDownload);
			}


			/**
			 * This option will secure the viewed document with watermark on
			 * each tale of the document
			 *
			 * @type OC.Share.Types.RegisteredShareAttribute
			 */
			var secureViewHasWatermark = {
				scope: "richdocuments",
				key: "watermark",
				default: JSON.parse(OC.appConfig.richdocuments.defaultShareAttributes.secureViewHasWatermark),
				label: t('richdocuments', 'protect with watermarks'),
				shareType : [
					OC.Share.SHARE_TYPE_GROUP,
					OC.Share.SHARE_TYPE_USER
				],
				incompatiblePermissions: [
					OC.PERMISSION_UPDATE,
					OC.PERMISSION_CREATE,
					OC.PERMISSION_DELETE
				],
				requiredPermissions: [],
				incompatibleAttributes: [
					{
						scope: "permissions",
						key: "download",
						enabled: true
					}
				]
			};
			model.registerShareAttribute(secureViewHasWatermark);

			/**
			 * With this permission being set, share receiver
			 * will be able to print document(s)
			 *
			 * @type OC.Share.Types.RegisteredShareAttribute
			 */
			var secureViewCanPrint = {
				scope: "richdocuments",
				key: "print",
				default: JSON.parse(OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanPrint),
				label: t('richdocuments', 'can print/export'),
				shareType : [
					OC.Share.SHARE_TYPE_GROUP,
					OC.Share.SHARE_TYPE_USER
				],
				incompatiblePermissions: [
					OC.PERMISSION_UPDATE,
					OC.PERMISSION_CREATE,
					OC.PERMISSION_DELETE
				],
				requiredPermissions: [],
				incompatibleAttributes: [
					{
						scope: "permissions",
						key: "download",
						enabled: true
					}
				]
			};
			model.registerShareAttribute(secureViewCanPrint);
		}
	}
});