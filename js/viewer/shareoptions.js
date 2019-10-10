/*globals */

var RichdocumentsShareOptions = {

	/**
	 * @type {OCA.Share.ShareItemModel}
	 */
	model: null,

	_shareOptionsTemplate: null,

	/**
	 * Extend ShareItemModel.addShare with richdocuments attributes. This
	 * is triggered on click on call to updateShare from core or other app
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
				// with secure view enabled, we need to remove edit permissions
				extendedProperties.permissions = this._removePermission(extendedProperties.permissions, OC.PERMISSION_UPDATE);
				extendedProperties.permissions = this._removePermission(extendedProperties.permissions, OC.PERMISSION_CREATE);
				extendedProperties.permissions = this._removePermission(extendedProperties.permissions, OC.PERMISSION_DELETE);

				// add secure view attributes
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "permissions", "download", false
				);
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "watermark", true
				);

				// get default value for printing
				var canPrint = OC.appConfig.richdocuments.defaultShareAttributes.secureViewCanPrint;
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "print", canPrint
				);
			} else {
				// disabled secure-view attributes
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "permissions", "download", true
				);
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "watermark", false
				);

				// without secure-view option enabled, print checkbox should not be visible
				extendedProperties.attributes = this._updateAttributes(
					extendedProperties.attributes, "richdocuments", "print", null
				);
			}
		}

		return extendedProperties;
	},

	/**
	 * Extend ShareItemModel.updateShare with richdocuments attributes. This is
	 * triggered only on click on richdocuments attribute
	 *
	 * @param shareId
	 * @param properties
	 */
	updateShareProperties: function(shareId, properties) {
		var that = this;
		var updatedProperties = properties;
		updatedProperties.attributes = properties.attributes || {};

		// if resharing permission got enabled, disable attributes as not compatible
		var canReshare = that._hasPermission(properties.permissions, OC.PERMISSION_SHARE);
		if (canReshare) {
			updatedProperties.attributes = that._updateAttributes(
				updatedProperties.attributes, "permissions", "download", null
			);
			updatedProperties.attributes = that._updateAttributes(
				updatedProperties.attributes, "richdocuments", "watermark", null
			);
			updatedProperties.attributes = that._updateAttributes(
				updatedProperties.attributes, "richdocuments", "print", null
			);

			return updatedProperties;
		}

		// if download permission got disabled, enable also secure-view
		var canDownloadAttr = that._getAttribute(properties.attributes, "permissions", "download");
		if (canDownloadAttr && canDownloadAttr.enabled === false) {
			updatedProperties.attributes = this._updateAttributes(
				updatedProperties.attributes, "richdocuments", "watermark", true
			);
			updatedProperties.attributes = this._updateAttributes(
				updatedProperties.attributes, "richdocuments", "print", false
			);
		}

		// otherwise on permission update, set always secure-view disabled
		updatedProperties.attributes = this._updateAttributes(
			updatedProperties.attributes, "permissions", "download", true
		);
		updatedProperties.attributes = this._updateAttributes(
			updatedProperties.attributes, "richdocuments", "watermark", false
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

		// update secure-view attributes
		if (secureView === true) {
			attributes = that._updateAttributes(
				attributes, "permissions", "download", false
			);
			attributes = that._updateAttributes(
				attributes, "richdocuments", "watermark", true
			);
			permissions = that._removePermission(permissions, OC.PERMISSION_UPDATE);
			permissions = that._removePermission(permissions, OC.PERMISSION_CREATE);
			permissions = that._removePermission(permissions, OC.PERMISSION_DELETE);
			permissions = that._removePermission(permissions, OC.PERMISSION_SHARE);

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
		} else if (secureView === false) {
			attributes = that._updateAttributes(
				attributes, "permissions", "download", true
			);
			attributes = that._updateAttributes(
				attributes, "richdocuments", "watermark", false
			);
			attributes = that._updateAttributes(
				attributes, "richdocuments", "print", null
			);
		} else {
			attributes = that._updateAttributes(
				attributes, "permissions", "download", null
			);
			attributes = that._updateAttributes(
				attributes, "richdocuments", "watermark", null
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
				var watermark = this._getAttribute(share.attributes, "richdocuments", "watermark");
				var print = this._getAttribute(share.attributes, "richdocuments", "print");

				// secure view means download not allowed, and watermarks set
				if (download !== null && watermark !== null) {
					shareOptionsData.push({
						cid: view.cid,
						shareId: share.id,
						shareWith: share.share_with,
						name: "secure-view",
						label: t('richdocuments', 'Secure View (with watermarks)'),
						enabled: (download.enabled === false && watermark.enabled === true)
					});

					// print can only be set when secure-view is enabled
					if (download.enabled === false && watermark.enabled === true && print !== null) {
						shareOptionsData.push({
							cid: view.cid,
							shareId: share.id,
							shareWith: share.share_with,
							name: "secure-view-print",
							label: t('richdocuments', 'can print / export'),
							enabled: print.enabled
						});
					}
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