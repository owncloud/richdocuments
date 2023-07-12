/*globals */

var RichdocumentsShareOptions = {

	/**
	 * @type {OCA.Share.ShareItemModel}
	 */
	model: null,

	_shareOptionsTemplate: null,

	/**
	 * Extend ShareItemModel.addShare with richdocuments attributes.
	 *
	 * Note: This should be triggered only by core or other apps on
	 * call to addShare
	 *
	 * @param properties
	 */
	addShareProperties: function(properties) {
		var extendedProperties = properties;

		// check if secure view feature is allowed
		if (OC.appConfig.richdocuments &&
			OC.appConfig.richdocuments.secureViewAllowed) {
			// Get default permissions and disable resharing as it is not supported
			extendedProperties.permissions = this.model.getDefaultPermissions();
			extendedProperties.permissions = this._removePermission(
				extendedProperties.permissions, OC.PERMISSION_SHARE
			);

			// set default attributes for secure-view / print
			if (OC.appConfig.richdocuments.defaultShareAttributes.secureViewHasWatermark) {
				// with secure view default, we need to remove edit permissions
				extendedProperties.permissions = this._removePermission(extendedProperties.permissions, OC.PERMISSION_UPDATE);
				extendedProperties.permissions = this._removePermission(extendedProperties.permissions, OC.PERMISSION_CREATE);
				extendedProperties.permissions = this._removePermission(extendedProperties.permissions, OC.PERMISSION_DELETE);

				// disable download of the file from server
				// but allow user to view the document in the editor (with watermarks)
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "permissions", "download", false
				);
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "view-with-watermark", true
				);

				// allow/disallow user from printing the document in the editor
				var canPrint = OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanPrint;
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "print", canPrint
				);
			} else {
				// with secure-view disabled do not add restriction on download, watermark and print
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "permissions", "download", null
				);
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "view-with-watermark", null
				);
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "print", null
				);
			}
		}

		return extendedProperties;
	},

	/**
	 * Extend ShareItemModel.updateShare with richdocuments attributes.
	 *
	 * Note: This should be triggered only by core or other apps on change of
	 * permissions or apps attributes
	 *
	 * @param shareId
	 * @param properties
	 */
	updateShareProperties: function(shareId, properties) {
		if (_.isUndefined(properties.permissions) && _.isUndefined(properties.attributes)) {
			// no attribute or permission change, ignore
			return properties;
		}

		var updatedProperties = properties;
		updatedProperties.attributes = properties.attributes || {};

		// if download permission got disabled, enable secure-view
		// feature (allow viewing, but only with watermark)
		var canDownloadAttr = this._getAttribute(properties.attributes, "permissions", "download");
		if (canDownloadAttr && canDownloadAttr.enabled === false) {
			updatedProperties.attributes = this._updateAttributes(
				updatedProperties.attributes, "richdocuments", "view-with-watermark", true
			);
			updatedProperties.attributes = this._updateAttributes(
				updatedProperties.attributes, "richdocuments", "print", false
			);

			return updatedProperties;
		}

		// otherwise on permission update, set always secure-view disabled (download allowed)
		updatedProperties.attributes = this._updateAttributes(
			updatedProperties.attributes, "permissions", "download", null
		);
		updatedProperties.attributes = this._updateAttributes(
			updatedProperties.attributes, "richdocuments", "view-with-watermark", null
		);
		updatedProperties.attributes = this._updateAttributes(
			updatedProperties.attributes, "richdocuments", "print", null
		);

		return updatedProperties;
	},

	/**
	 * Click on custom checkbox handler. Adjust required app/core attributes and
	 * permissions
	 *
	 * @param event
	 */
	onRichdocumentsOptionChange: function(event) {
		var share;
		var $element = $(event.target);
		var $li = $element.closest('li');
		var shareId = $li.data('share-id');
		var that = this;

		var shares = this.model.getSharesWithCurrentItem();
		for(var shareIndex = 0; shareIndex < shares.length; shareIndex++) {
			if (shares[shareIndex].id === shareId) {
				share = shares[shareIndex];
				break;
			}
		}

		if (!share) {
			console.error("Share with id " + shareId + " not found");
			return;
		}

		var secureView = null;
		var secureViewPrint = null;
		$(".richdocumentsShareOption").each(function(index, checkbox) {
			if ($(checkbox).data('share-id') === shareId) {
				var shareOptionName = $(checkbox).attr('name');
				var shareOptionEnabled = $(checkbox).is(':checked');
				if (shareOptionName === "secure-view") {
					secureView = shareOptionEnabled;
				} else if (shareOptionName === "secure-view-print") {
					secureViewPrint = shareOptionEnabled;
				}
			}
		});

		var attributes = share.attributes || {};
		var permissions = share.permissions || 1;

		if (secureView === true) {
			// if secure view option got enabled
			// - disable edit/sharing
			// - disable download, allow viewing but only with watermark
			permissions = that._removePermission(permissions, OC.PERMISSION_UPDATE);
			permissions = that._removePermission(permissions, OC.PERMISSION_CREATE);
			permissions = that._removePermission(permissions, OC.PERMISSION_DELETE);
			permissions = that._removePermission(permissions, OC.PERMISSION_SHARE);

			attributes = that._updateAttributes(
				attributes, "permissions", "download", false
			);
			attributes = that._updateAttributes(
				attributes, "richdocuments", "view-with-watermark", true
			);

			if (secureViewPrint !== null) {
				// if secure-view is enabled and print checkbox is filled, update print from checkbox
				attributes = that._updateAttributes(
					attributes, "richdocuments", "print", secureViewPrint
				);
			} else {
				// if secure-view is enabled and there was no checkbox, set default print value
				var printDefault = OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanPrint;
				attributes = that._updateAttributes(
					attributes, "richdocuments", "print", printDefault
				);
			}
		} else {
			// if secure view option got disabled
			// - enable download
			// - unset view-with-watermark and print as not available
			attributes = that._updateAttributes(
				attributes, "permissions", "download", null
			);
			attributes = that._updateAttributes(
				attributes, "richdocuments", "view-with-watermark", null
			);
			attributes = that._updateAttributes(
				attributes, "richdocuments", "print", null
			);
		}

		// trigger updateShare which will call updateShare wrappers
		that.model.updateShare(
			shareId,
			{
				permissions: permissions,
				attributes: attributes
			},
			{
				richdocumentsUpdatedShareProperties: true
			}
		);
	},

	/**
	 * Based on attributes set for the share, render proper share options
	 *
	 * @param view
	 */
	render: function (view) {
		var shares = this.model.getSharesWithCurrentItem();
		for(var shareIndex = 0; shareIndex < shares.length; shareIndex++) {
			var share = shares[shareIndex];

			// get existing share element if already initialized
			var $share = view.$el.find('li[data-share-id=' + share.id + ']');
			if ($share) {
				// extend with share options for the richdocuments
				var shareOptionsData = [];

				var download = this._getAttribute(share.attributes, "permissions", "download");
				var viewWithWatermark = this._getAttribute(share.attributes, "richdocuments", "view-with-watermark");
				var print = this._getAttribute(share.attributes, "richdocuments", "print");
				var secureViewEnabled = download !== null &&
					download.enabled === false &&
					viewWithWatermark.enabled === true;

				// secure-view: download permission disabled, allow viewing but only with watermark
				shareOptionsData.push({
					cid: view.cid,
					shareId: share.id,
					shareWith: share.share_with,
					name: "secure-view",
					label: t('richdocuments', 'Secure View (with watermarks)'),
					enabled: secureViewEnabled
				});

				// print can only be set when secure-view is enabled
				if (secureViewEnabled && print !== null) {
					shareOptionsData.push({
						cid: view.cid,
						shareId: share.id,
						shareWith: share.share_with,
						name: "secure-view-print",
						label: t('richdocuments', 'can print / export'),
						enabled: print.enabled
					});
				}

				$share.append(
					this._template({
						shareOptions: shareOptionsData
					})
				);
			}
		}

		// On click trigger logic to update for new richdocuments attributes
		$(".richdocumentsShareOption").on('click', $.proxy(this.onRichdocumentsOptionChange, this));
	},

	/**
	 * Fill share options template based on supplied data map of {{ data-item }}
	 * @private
	 */
	_template: function (data) {
		if (!this._shareOptionsTemplate) {
			this._shareOptionsTemplate = Handlebars.compile(
				'<div class="richdocumentsShareOptions">' +
					'{{#each shareOptions}}' +
					'<span class="shareOption">' +
						'<input id="attr-{{name}}-{{cid}}-{{shareWith}}" type="checkbox" name="{{name}}" class="richdocumentsShareOption checkbox" {{#if enabled}}checked="checked"{{/if}} data-share-id="{{shareId}}"/>' +
						'<label for="attr-{{name}}-{{cid}}-{{shareWith}}">{{label}}</label>' +
					'</span' +
					'{{/each}}' +
				'</div>'
			);
		}
		return this._shareOptionsTemplate(data);
	},

	_getAttribute: function(attributes, scope, key) {
		for(var i in attributes) {
			if (attributes[i].scope === scope
				&& attributes[i].key === key
				&& attributes[i].enabled !== null) {
				return attributes[i];
			}
		}

		return null;
	},

	_updateAttributes: function(attributes, scope, key, enabled) {
		var updatedAttributes = [];

		// copy existing scope-key pairs from attributes
		for(var i in attributes) {
			if (attributes[i].scope !== scope
				|| attributes[i].key !== key) {
				updatedAttributes.push({
					scope: attributes[i].scope,
					key: attributes[i].key,
					enabled: attributes[i].enabled
				});
			}
		}

		// update attributes with scope-key pair to update
		if (scope && key && enabled !== null) {
			updatedAttributes.push({
				scope: scope,
				key: key,
				enabled: enabled
			});
		}

		return updatedAttributes;
	},

	_hasPermission: function(permissions, permission) {
		return (permissions & permission) === permission;
	},

	_removePermission: function(permissions, permission) {
		return (permissions & ~permission);
	}

};

OC.Plugins.register('OC.Share.ShareDialogView', {

	attach: function (view) {
		if (_.isUndefined(view.model)) {
			console.error("missing OC.Share.ShareItemModel");
			return;
		}
		
		RichdocumentsShareOptions.model = view.model;

		// Register additional available share attributes
		var mimetype = RichdocumentsShareOptions.model.getFileInfo().getMimeType();
		var folderMimeType = 'httpd/unix-directory';
		if ((!odfViewer.isSupportedMimeType(mimetype) && mimetype !== folderMimeType)) {
			return;
		}

		// customize rendering of checkboxes
		var baseRenderCall = view.render;
		view.render = function() {
			baseRenderCall.call(view);
			RichdocumentsShareOptions.render(view);
		};

		var model = view.model;
		var baseAddShareCall = model.addShare;
		model.addShare = function(properties, options) {
			var newProperties = RichdocumentsShareOptions.addShareProperties(properties);

			var newOptions = options || {};

			baseAddShareCall.call(model, newProperties, newOptions);
		};

		var baseUpdateShareCall = model.updateShare;
		model.updateShare = function(shareId, properties, options) {
			var newProperties = properties || {};
			var newOptions = options || {};

			// update for richdocuments attributes if not updated already
			if (!options.hasOwnProperty('richdocumentsUpdatedShareProperties')) {
				newProperties = RichdocumentsShareOptions.updateShareProperties(shareId, newProperties);

				_.extend(newOptions, { richdocumentsUpdatedShareProperties: true });
			}

			baseUpdateShareCall.call(model, shareId, newProperties, newOptions);
		};

		// Add call to watch for changes of shares
		model.on('change:shares', function(event) {
			RichdocumentsShareOptions.render(view);
		});
	}
});


OC.Plugins.register('OCA.Share.ShareDialogLinkShareView', {

	attach: function (view) {
		if (_.isUndefined(view.model)) {
			console.error("missing OC.Share.ShareItemModel");
			return;
		}

		// NOTE: folder is not supported at the moment
		var mimetype = view.itemModel.getFileInfo().getMimeType();
		if (!odfViewer.isSupportedMimeType(mimetype)) {
			return;
		}

		var baseGetRolesCall = view.getRoles;
		view.getRoles = function(properties, options) {
			/** @type {OC.Share.ShareDialogLink.PublicLinkRole[]} **/
			var roles = baseGetRolesCall.call(view);
			
			roles.push({
				name: "allowPreviewRichdocuments",
				permissions: OC.PERMISSION_READ,
				attributes: [
					{
						scope: "permissions",
						key: "download",
						enabled: false
					}
				]
			});
			return roles;
		};

		// customize rendering of checkboxes
		var baseRenderCall = view.render;
		view.render = function() {
			baseRenderCall.call(view);

			var _template = Handlebars.compile(
				'<div id="allowPreviewRichdocuments" class="public-link-modal--item">' +
					'<input type="radio" value="allowPreviewRichdocuments" name="publicLinkRole" id="sharingDialogAllowPreviewRichdocuments-{{cid}}" class="checkbox publicLinkRole" {{#if allowPreviewRichdocumentSelected}}checked{{/if}}/>' +
					'<label class="bold" for="sharingDialogAllowPreviewRichdocuments-{{cid}}">{{allowPreviewRichdocumentsLabel}}</label>' +
					'<p><em>{{allowPreviewRichdocumentsDescription}}</em></p>' +
				'</div>'
			)
			
			var $container = view.$el.find('div[id=appManagedPublicLinkModelItems]');

			$container.append(
				_template({
					cid: view.cid,
					allowPreviewRichdocumentsLabel: "Preview",
					allowPreviewRichdocumentsDescription: "Recipients can only view contents online. Download is restricted.",
					allowPreviewRichdocumentSelected: view._checkRoleEnabled('allowPreviewRichdocuments'),
				})
			);
			return;
		};
		//
		
	}
});