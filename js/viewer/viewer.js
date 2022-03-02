/* globals FileList, OCA.Files.fileActions, oc_debug */
var odfViewer = {
	isDocuments : false,

	loadSettings: function () {
		return new Promise(function (resolve, reject) {
			$.ajax({
				type: "get",
				url: OC.filePath('richdocuments', 'ajax', 'settings.php'),
				success: function(data) {
					resolve(data);
				},
				error: function(xhr, status) {
					reject(Error(status));
				}
			});
		});
	},

	isSupportedMimeType: function(mimetype) {
		return (odfViewer.supportedMimes.indexOf(mimetype) !== -1);
	},

	register : function(settings) {
		for (var i = 0; i < settings.supported_mimetypes.length; ++i) {
			var mime = settings.supported_mimetypes[i];

			if(!$("#isPublic").val() &&
					OC.appConfig.richdocuments &&
					OC.appConfig.richdocuments.secureViewAllowed === true &&
					OC.appConfig.richdocuments.secureViewOpenActionDefault === true) {
				OCA.Files.fileActions.registerAction({
					name: 'RichdocumentsSecureView',
					actionHandler: odfViewer.onOpenWithSecureView,
					displayName: t('richdocuments', 'Open in Collabora with Secure View'),
					iconClass: 'icon-lock-closed',
					permissions: OC.PERMISSION_READ,
					mime: mime
				});
				OCA.Files.fileActions.setDefault(mime, 'RichdocumentsSecureView');
			} else {
				OCA.Files.fileActions.registerAction({
					name: 'Richdocuments',
					actionHandler: odfViewer.onOpen,
					displayName: t('richdocuments', 'Open in Collabora'),
					iconClass: 'icon-richdocuments-open',
					permissions: OC.PERMISSION_READ,
					mime: mime
				});
				OCA.Files.fileActions.setDefault(mime, 'Richdocuments');
			}
		}

	},

	onOpen : function(fileName, context){
		var fileId = context.$file.attr('data-id');
		var fileDir = context.dir;

		var url;
		if ($("#isPublic").val()) {
			// Generate url for click on file in public share folder
			url = OC.generateUrl("apps/richdocuments/public?fileId={file_id}&shareToken={shareToken}", { file_id: fileId, shareToken: encodeURIComponent($("#sharingToken").val()) });
		} else if (fileDir) {
			url = OC.generateUrl('apps/richdocuments/index?fileId={file_id}&dir={dir}', { file_id: fileId, dir: fileDir });
		} else {
			url = OC.generateUrl('apps/richdocuments/index?fileId={file_id}', {file_id: fileId});
		}

		if (OC.appConfig.richdocuments.openInNewTab === true) {
			window.open(url,'_blank');
		} else {
			window.location = url;
		}

	},

	onOpenWithSecureView : function(fileName, context){
		var fileId = context.$file.attr('data-id');
		var fileDir = context.dir;

		var url;
		if (fileDir) {
			url = OC.generateUrl('apps/richdocuments/index?fileId={file_id}&dir={dir}&enforceSecureView={enforceSecureView}', { file_id: fileId, dir: fileDir, enforceSecureView: "true" });
		} else {
			url = OC.generateUrl('apps/richdocuments/index?fileId={file_id}&enforceSecureView={enforceSecureView}', {file_id: fileId, enforceSecureView: "true" });
		}
		if (OC.appConfig.richdocuments.openInNewTab === true) {
			window.open(url,'_blank');
		} else {
			window.location = url;
		}

	},

	registerFilesMenu: function(settings) {
		var ooxml = settings.doc_format === 'ooxml';

		var docExt, spreadsheetExt, presentationExt;
		var docMime, spreadsheetMime, presentationMime;
		var drawExt, drawMime;
		if (ooxml) {
			docExt = 'docx';
			spreadsheetExt = 'xlsx';
			presentationExt = 'pptx';
			docMime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			spreadsheetMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
			presentationMime =	'application/vnd.openxmlformats-officedocument.presentationml.presentation';
		} else {
			docExt = 'odt';
			spreadsheetExt = 'ods';
			presentationExt = 'odp';
			drawExt = 'odg';
			docMime = 'application/vnd.oasis.opendocument.text';
			spreadsheetMime = 'application/vnd.oasis.opendocument.spreadsheet';
			presentationMime = 'application/vnd.oasis.opendocument.presentation';
			drawMime = 'application/vnd.oasis.opendocument.graphics';
		}

		(function(OCA){
			OCA.FilesLOMenu = {
				attach: function(newFileMenu) {
					var self = this;

					newFileMenu.addMenuEntry({
						id: 'add-' + docExt,
						displayName: t('richdocuments', 'Document'),
						templateName: 'New Document.' + docExt,
						iconClass: 'icon-filetype-document',
						fileType: 'x-office-document',
						actionHandler: function(filename) {
							self._createDocument(docMime, filename);
						}
					});

					newFileMenu.addMenuEntry({
						id: 'add-' + spreadsheetExt,
						displayName: t('richdocuments', 'Spreadsheet'),
						templateName: 'New Spreadsheet.' + spreadsheetExt,
						iconClass: 'icon-filetype-spreadsheet',
						fileType: 'x-office-spreadsheet',
						actionHandler: function(filename) {
							self._createDocument(spreadsheetMime, filename);
						}
					});

					newFileMenu.addMenuEntry({
						id: 'add-' + presentationExt,
						displayName: t('richdocuments', 'Presentation'),
						templateName: 'New Presentation.' + presentationExt,
						iconClass: 'icon-filetype-presentation',
						fileType: 'x-office-presentation',
						actionHandler: function(filename) {
							self._createDocument(presentationMime, filename);
						}
					});

					if (!ooxml) {
						newFileMenu.addMenuEntry({
							id: 'add-' + drawExt,
							displayName: t('richdocuments', 'Drawing'),
							templateName: 'New Drawing.' + drawExt,
							iconClass: 'icon-filetype-drawing',
							fileType: 'x-office-drawing',
							actionHandler: function(filename) {
								self._createDocument(drawMime, filename);
							}
						});
					}
				},

				_createDocument: function(mimetype, filename) {
					OCA.Files.Files.isFileNameValid(filename);
					filename = FileList.getUniqueName(filename);

					$.post(
						OC.generateUrl('apps/richdocuments/ajax/documents/create'),
						{ mimetype : mimetype, filename: filename, dir: $('#dir').val() },
						function(response){
							if (response && response.status === 'success'){
								FileList.add(response.data, {animate: true, scrollTo: true});
							} else {
								OC.dialogs.alert(response.message, t('core', 'Could not create file'));
							}
						}
					);
				}
			};
		})(OCA);

		OC.Plugins.register('OCA.Files.NewFileMenu', OCA.FilesLOMenu);
	},

	dispatch : function(filename){
		// FIXME: deprecated?
		if (this.isSupportedMimeType(OCA.Files.fileActions.getCurrentMimeType())
			&& OCA.Files.fileActions.getCurrentPermissions() & OC.PERMISSION_UPDATE
		){
			odfViewer.onOpen(filename);
		} else {
			odfViewer.onView(filename);
		}
	},

	onView: function(filename, context) {
		// FIXME: deprecated?
	    var attachTo = odfViewer.isDocuments ? '#documents-content' : '#controls';

	    FileList.setViewerMode(true);
	},

	onClose: function() {
		// FIXME: deprecated?
		FileList.setViewerMode(false);
		$('#loleafletframe').remove();
	},
};

$(document).ready(function() {
	if ( typeof OCA !== 'undefined'
		&& typeof OCA.Files !== 'undefined'
		&& typeof OCA.Files.fileActions !== 'undefined'
	) {

		odfViewer.loadSettings().then(function (settings) {
			odfViewer.register(settings);

			// Check if public file share button
			var mimetype = $("#mimetype").val();
			if (settings.supported_mimetypes.indexOf(mimetype) !== -1 && $("#isPublic").val()){
				// Single file public share, add button to allow view or edit
				var button = document.createElement("a");
				button.href = OC.generateUrl("apps/richdocuments/public?fileId={file_id}&shareToken={shareToken}", { file_id: null, shareToken: encodeURIComponent($("#sharingToken").val()) });
				button.className = "button";
				button.innerText = t('richdocuments', 'View/Edit in Collabora');

				$("#preview").append(button);
			}

			if (!$("#isPublic").val()) {
				odfViewer.registerFilesMenu(settings);
			}
		}, function (error) {
			console.error(error);
		});

	}

	if ($('#odf_close').length) {
		$('#odf_close').live('click', odfViewer.onClose);
	}
});
