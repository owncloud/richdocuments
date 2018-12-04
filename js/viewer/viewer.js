/* globals FileList, OCA.Files.fileActions, oc_debug */
var odfViewer = {
	isDocuments : false,
	supportedMimes: [
		'application/vnd.oasis.opendocument.text',
		'application/vnd.oasis.opendocument.spreadsheet',
		'application/vnd.oasis.opendocument.graphics',
		'application/vnd.oasis.opendocument.presentation',
		'application/vnd.oasis.opendocument.text-flat-xml',
		'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
		'application/vnd.oasis.opendocument.graphics-flat-xml',
		'application/vnd.oasis.opendocument.presentation-flat-xml',
		'application/vnd.lotus-wordpro',
		'image/svg+xml',
		'application/vnd.visio',
		'application/vnd.wordperfect',
		'application/msonenote',
		'application/msword',
		'application/rtf',
		'text/rtf',
		'text/plain',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		'application/vnd.ms-word.document.macroEnabled.12',
		'application/vnd.ms-word.template.macroEnabled.12',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
		'application/vnd.ms-excel.sheet.macroEnabled.12',
		'application/vnd.ms-excel.template.macroEnabled.12',
		'application/vnd.ms-excel.addin.macroEnabled.12',
		'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/vnd.openxmlformats-officedocument.presentationml.template',
		'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
		'application/vnd.ms-powerpoint.addin.macroEnabled.12',
		'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		'application/vnd.ms-powerpoint.template.macroEnabled.12',
		'application/vnd.ms-powerpoint.slideshow.macroEnabled.12'
	],

	register : function(){
		var i,
		    mime;

		for (i = 0; i < odfViewer.supportedMimes.length; ++i) {
			mime = odfViewer.supportedMimes[i];
			OCA.Files.fileActions.register(
				    mime,
					'Edit',
					OC.PERMISSION_UPDATE | OC.PERMISSION_READ,
					OC.imagePath('core', 'actions/rename'),
					odfViewer.onEdit,
					t('richdocuments', 'Edit')
			);
			OCA.Files.fileActions.setDefault(mime, 'Edit');

			// Allow to add collabora permissions only if UPDATE permission is set on file
			OCA.Files.fileActions.registerAction({
				name: 'Secure',
				displayName: '',
				mime: mime,
				actionHandler: odfViewer.onSecure,
				permissions: OC.PERMISSION_UPDATE,
				icon: OC.imagePath('core', 'apps/shield'),
				type: OCA.Files.FileActions.TYPE_INLINE
			});
		}
	},

	dispatch : function(filename){
		if (odfViewer.supportedMimes.indexOf(OCA.Files.fileActions.getCurrentMimeType()) !== -1
			&& OCA.Files.fileActions.getCurrentPermissions() & OC.PERMISSION_UPDATE
		){
			odfViewer.onEdit(filename);
		} else {
			odfViewer.onView(filename);
		}
	},

	onEdit : function(fileName, context){
		var fileId = context.$file.attr('data-id');

		var url;
		if ($("#isPublic").val()) {
			// Generate url for click on file in public share folder
			url = OC.generateUrl("apps/richdocuments/s/{token}?fileId={file_id}", { token: encodeURIComponent($("#sharingToken").val()), file_id: fileId });
		} else {
			url = OC.generateUrl('apps/richdocuments/index?fileId={file_id}', {file_id: fileId});
		}

		window.location = url;
	},


	_onClickSaveSecure: function() {
		var $dialogShell = $('.oc-dialog:visible');
		$dialog = $dialogShell.find('.oc-dialog-content');
		$dialog.ocdialog('close');
	},

	onSecure : function(filename, context){
		var buttons = [
			{
				text: t('core', 'Save'),
				click: _.bind(odfViewer._onClickSaveSecure, this)
			}
		];
		var title = t('core', 'Collabora permissions: {name}', {name: filename});
		OC.dialogs.message(
			'',
			title,
			'custom',
			buttons,
			null,
			true,
			'public-link-modal'
		).then(function adjustDialog() {
			var $dialogShell = $('.oc-dialog:visible');
			$dialog = $dialogShell.find('.oc-dialog-content');
			var TEMPLATE =
				'<div id="linkPass-1" class="public-link-modal--item">' +
				'<div id="test-0" class="public-link-modal--item">' +
				'<p><b>Read-only share found</b>. Add special security permissions:</p>' +
				'</div>' +
				'<div id="test-1" class="public-link-modal--item">' +
				'<input type="checkbox" value="1" name="readOnlyPermissions" id="test-1" class="checkbox readOnlyPermissions" checked />' +
				'<label class="bold" for="test-1">Enable priting</label>' +
				'<p><em>Read-only file can be printed in Collabora</em></p>' +
				'</div>' +
				'<div id="test-2" class="public-link-modal--item">' +
				'<input type="checkbox" value="1" name="readOnlyPermissions" id="test-2" class="checkbox readOnlyPermissions" checked/>' +
				'<label class="bold" for="test-2">Enable downloading</label>' +
				'<p><em>Read-only file can be downloaded in Collabora</em></p>' +
				'</div>' +
				'<div id="test-2" class="public-link-modal--item">' +
				'<input type="checkbox" value="1" name="readOnlyPermissions" id="test-2" class="checkbox readOnlyPermissions"/>' +
				'<label class="bold" for="test-2">Restrict only to secure editor</label>' +
				'<p><em>Read-only file can only by downloaded/viewed through Collabora</em></p>' +
				'</div>' +
				'<div id="test-3" class="public-link-modal--item">' +
				'<input type="checkbox" value="1" name="readOnlyPermissions" id="test-3" class="checkbox readOnlyPermissions"/>' +
				'<label class="bold" for="test-3">Include watermarks</label>' +
				'<p><em>Printing or download will include watermark</em></p>' +
				'</div>' +
				'</div>'
			;
			$dialog.html(TEMPLATE);
		});
	},

	onView: function(filename, context) {
	    var attachTo = odfViewer.isDocuments ? '#documents-content' : '#controls';

	    FileList.setViewerMode(true);
	},

	onClose: function() {
		FileList.setViewerMode(false);
		$('#loleafletframe').remove();
	},

	registerFilesMenu: function(response) {
		var ooxml = response.doc_format === 'ooxml';

		var docExt, spreadsheetExt, presentationExt;
		var docMime, spreadsheetMime, presentationMime;
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
			docMime = 'application/vnd.oasis.opendocument.text';
			spreadsheetMime = 'application/vnd.oasis.opendocument.spreadsheet';
			presentationMime = 'application/vnd.oasis.opendocument.presentation';
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
								OC.dialogs.alert(response.data.message, t('core', 'Could not create file'));
							}
						}
					);
				}
			};
		})(OCA);

		OC.Plugins.register('OCA.Files.NewFileMenu', OCA.FilesLOMenu);
	}
};

$(document).ready(function() {
	if ( typeof OCA !== 'undefined'
		&& typeof OCA.Files !== 'undefined'
		&& typeof OCA.Files.fileActions !== 'undefined'
	) {
		odfViewer.register();

		// Check if public file share button
		var mimetype = $("#mimetype").val();
		if (odfViewer.supportedMimes.indexOf(mimetype) !== -1 && $("#isPublic").val()){
			// Single file public share, add button to allow view or edit
			var button = document.createElement("a");
			button.href = OC.generateUrl("apps/richdocuments/s/{token}?fileId={file_id}", { token: encodeURIComponent($("#sharingToken").val()), file_id: null });
			button.className = "button";
			button.innerText = t('richdocuments', 'View/Edit in Collabora');

			$("#preview").append(button);
		}

		if (!$("#isPublic").val()) {
			// Dont register file menu with public links
			$.get(
				OC.filePath('richdocuments', 'ajax', 'settings.php'),
				{},
				odfViewer.registerFilesMenu
			);
		}
	}

	$('#odf_close').live('click', odfViewer.onClose);
});
