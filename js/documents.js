/*globals $,OC,fileDownloadPath,t,document,odf,alert,require,dojo,runtime,Handlebars,instanceId */

$.widget('oc.documentGrid', {
	options : {
		context : '.documentslist',
		documents : {}
	},

	render : function(fileId){
		var that = this;
		jQuery.when(this._load(fileId))
			.then(function(){
				that._render();
				documentsMain.renderComplete = true;
			});
	},

	add : function(document) {
		var docElem = $(this.options.context + ' .template').clone(),
			a = docElem.find('a')
		;

		//Fill an element
		docElem.removeClass('template').attr('data-fileid', document.fileid);
		a.css('background-image', 'url("'+document.icon+'")')
			.attr('href', OC.generateUrl('apps/files/download{file}',{file:document.path}))
			.attr('version', document.version)
			.attr('title', document.path)
			.attr('original-title', document.path)
			.attr('urlsrc', document.urlsrc)
			.attr('action', document.action)
			.attr('lolang', document.lolang)
			.find('label').text(document.name)
		;

		docElem.appendTo(this.options.context).show();

		//Preview
		var previewURL,
			urlSpec = {
			file : document.path.replace(/^\/\//, '/'),
			x : 200,
			y : 200,
			c : document.etag,
			forceIcon : 0
		};

		if ( $('#isPublic').length ) {
			urlSpec.t = $('#dirToken').val();
		}

		if (!urlSpec.x) {
			urlSpec.x = $('#filestable').data('preview-x');
		}
		if (!urlSpec.y) {
			urlSpec.y = $('#filestable').data('preview-y');
		}
		urlSpec.y *= window.devicePixelRatio;
		urlSpec.x *= window.devicePixelRatio;

		previewURL = OC.generateUrl('/core/preview.png?') + $.param(urlSpec);
		previewURL = previewURL.replace('(', '%28').replace(')', '%29');

		if ( $('#previews_enabled').length && document.hasPreview) {
			var img = new Image();
			img.onload = function(){
				var ready = function (node){
					return function(path){
						node.css('background-image', 'url("'+ path +'")');
					};
				}(a);
				ready(previewURL);
			};
			img.src = previewURL;
		}
	},

	_load : function (fileId){
		if (fileId){
			documentsMain.initSession();
			return null;
		}

		// show the document list template
		$('.documentslist').show();

		var that = this;
		var url = 'apps/richdocuments/ajax/documents/list';
		return $.getJSON(OC.generateUrl(url))
			.done(function (result) {
				if (!result || result.status === 'error') {
					documentsMain.loadError = true;
					if (result && result.message) {
						documentsMain.loadErrorMessage = result.message;
					}
					else {
						documentsMain.loadErrorMessage = t('richdocuments', 'Failed to load the document, please contact your administrator.');
					}

					if (result && result.hint) {
						documentsMain.loadErrorHint = result.hint;
					}
				}
				else {
					that.options.documents = result.documents;
				}
			})
			.fail(function(data){
				console.log(t('richdocuments','Failed to load documents.'));
			});
	},

	_render : function (){
		var that = this,
		    documents = this.options.documents,
		    hasDocuments = !!documents;

		if (documentsMain.loadError) {
			$(this.options.context).after('<div id="errormessage">'
				+ '<p>' + documentsMain.loadErrorMessage + '</p><p>'
				+ documentsMain.loadErrorHint
				+ '</p></div>'
			);
			return;
		}

		// no need to render anything if we don't have any documents and we know which fileId to open
		if (!hasDocuments && documentsMain.fileId)
			return;

		$(this.options.context + ' .document:not(.template,.progress)').remove();
		$.each(documents, function(i, document){
			hasDocuments = true;
			that.add(document);
		});

		if (!hasDocuments){
			$(this.options.context).before('<div id="emptycontent">'
				+ t('richdocuments', 'No documents were found. Upload or create a document to get started!')
				+ '</div>'
			);
		} else {
			$('#emptycontent').remove();
		}
	}
});

$.widget('oc.documentOverlay', {
	options : {
		parent : 'document.body'
	},
	_create : function (){
		$(this.element).hide().appendTo(document.body);
	},
	show : function(){
		$(this.element).fadeIn('fast');
	},
	hide : function(){
		$(this.element).fadeOut('fast');
	}
});

var documentsMain = {
	isEditorMode : false,
	isViewerMode: false,
	fileName: null,
	canShare : false,
	canEdit: false,
	loadError : false,
	loadErrorMessage : '',
	loadErrorHint : '',
	renderComplete: false, // false till page is rendered with all required data about the document(s)
	toolbar : '<div id="ocToolbar"><div id="ocToolbarInside"></div><span id="toolbar" class="claro"></span></div>',
	returnToDir : null, // directory where we started from in the 'Files' app
	$deferredVersionRestoreAck: null,
	wopiClientFeatures: null,

	// generates docKey for given fileId
	_generateDocKey: function(wopiSessionId) {
		var ocurl = OC.generateUrl('apps/richdocuments/wopi/files/{sessionId}', {sessionId: wopiSessionId});
		if (rd_canonical_webroot) {
			if (!rd_canonical_webroot.startsWith('/'))
				rd_canonical_webroot = '/' + rd_canonical_webroot;

			ocurl = ocurl.replace(OC.webroot, rd_canonical_webroot);
		}

		return ocurl;
	},

	// generates owncloud web url to access given fileId
	_generateFullUrl: function(fileId, dir) {
		var ocurl;
		if (dir)
			ocurl = OC.generateUrl('apps/richdocuments/index?fileId={fileId}&dir={dir}', {fileId: fileId, dir: dir});
		else
			ocurl = OC.generateUrl('apps/richdocuments/index?fileId={fileId}', {fileId: fileId});

		return window.location.protocol + '//' + window.location.host + ocurl;
	},

	UI : {
		/* Editor wrapper HTML */
		container : '<div id="mainContainer" class="claro">' +
					'</div>',

		viewContainer: '<div id="revViewerContainer" class="claro">' +
					   '<div id="revViewer"></div>' +
					   '</div>',

		revHistoryContainerTemplate: '<div id="revPanelContainer" class="loleaflet-font">' +
			'<div id="revPanelHeader">' +
			'<h2>Revision History</h2>' +
			'<span>{{filename}}</span>' +
			'<a class="closeButton"><img src={{closeButtonUrl}} width="22px" height="22px"></a>' +
			'</div>' +
			'<div id="revisionsContainer" class="loleaflet-font">' +
			'<ul></ul>' +
			'</div>' +
			'<input type="button" id="show-more-versions" class="loleaflet-font" value="{{moreVersionsLabel}}" />' +
			'</div>',

		revHistoryItemTemplate: '<li>' +
			'<a href="{{downloadUrl}}" class="downloadVersion has-tooltip" title="' + t('richdocuments', 'Download this revision') + '"><img src="{{downloadIconUrl}}" />' +
			'<a class="versionPreview"><span class="versiondate has-tooltip" title="{{formattedTimestamp}}">{{relativeTimestamp}}</span></a>' +
			'<a href="{{restoreUrl}}" class="restoreVersion has-tooltip" title="' + t('richdocuments', 'Restore this revision') + '"><img src="{{restoreIconUrl}}" />' +
			'</a>' +
			'</li>',

		/* Previous window title */
		mainTitle : '',
		/* Number of revisions already loaded */
		revisionsStart: 0,

		init : function(){
			documentsMain.UI.mainTitle = $('title').text();
		},

		// viewer has version revision-only permission,
		// title will reflect the version selected
		showViewer: function(version, title){
			// remove previous viewer, if open, and set a new one
			if (documentsMain.isViewerMode) {
				$('#revViewer').remove();
				$('#revViewerContainer').prepend($('<div id="revViewer">'));
			}

			var wopiSessionId = documentsMain.fileId + "_" +
				documentsMain.instanceId + "_" +
				version + "_" +
				documentsMain.sessionId;
			var ocurl = documentsMain._generateDocKey(wopiSessionId);
			// WOPISrc - URL that loolwsd will access (ie. pointing to ownCloud)
			var wopiurl = window.location.protocol + '//' + window.location.host + ocurl;
			var wopisrc = encodeURIComponent(wopiurl);

			// urlsrc - the URL from discovery xml that we access for the particular
			// document; we add various parameters to that.
			// The discovery is available at
			//   https://<loolwsd-server>:9980/hosting/discovery
			var urlsrc = documentsMain.urlsrc +
			    "WOPISrc=" + wopisrc +
			    "&title=" + encodeURIComponent(title) +
			    "&lang=" + OC.getLocale().replace('_', '-') +
			    "&permission=readonly";

			// access_token - must be passed via a form post
			var access_token = encodeURIComponent(documentsMain.token);

			// form to post the access token for WOPISrc
			var form = '<form id="loleafletform_viewer" name="loleafletform_viewer" target="loleafletframe_viewer" action="' + urlsrc + '" method="post">' +
			    '<input name="access_token" value="' + access_token + '" type="hidden"/></form>';

			// iframe that contains the Collabora Online Viewer
			var frame = '<iframe id="loleafletframe_viewer" name= "loleafletframe_viewer" style="width:100%;height:100%;position:absolute;"/>';

			$('#revViewer').append(form);
			$('#revViewer').append(frame);

			// submit that
			$('#loleafletform_viewer').submit();
			documentsMain.isViewerMode = true;


			// for closing revision mode
			$('#revPanelHeader .closeButton').click(function(e) {
				e.preventDefault();
				documentsMain.onCloseViewer();
			});
		},

		addRevision: function(fileId, version, relativeTimestamp, documentPath) {
			var formattedTimestamp = OC.Util.formatDate(parseInt(version) * 1000);
			var fileName = documentsMain.fileName.substring(0, documentsMain.fileName.indexOf('.'));
			var downloadUrl, restoreUrl;

			if (version === 0) {
				formattedTimestamp = t('richdocuments', 'Latest revision');
				downloadUrl = OC.generateUrl('apps/files/download'+ documentPath);
			} else {
				// FIXME: this is no longer supported in OC10
				downloadUrl = OC.generateUrl('apps/files_versions/download.php?file={file}&revision={revision}',
				                             {file: documentPath, revision: version});
				fileId = fileId + '_' + version;
				restoreUrl = OC.generateUrl('apps/files_versions/ajax/rollbackVersion.php?file={file}&revision={revision}',
				                            {file: documentPath, revision: version});
			}

			var revHistoryItemTemplate = Handlebars.compile(documentsMain.UI.revHistoryItemTemplate);
			var html = revHistoryItemTemplate({
				downloadUrl: downloadUrl,
				downloadIconUrl: OC.imagePath('core', 'actions/download'),
				restoreUrl: restoreUrl,
				restoreIconUrl: OC.imagePath('core', 'actions/history'),
				relativeTimestamp: relativeTimestamp,
				formattedTimestamp: formattedTimestamp
			});

			html = $(html).attr('data-fileid', fileId)
				.attr('data-version', version)
				.attr('data-title', fileName + ' - ' + formattedTimestamp);
			$('#revisionsContainer ul').append(html);
		},

		fetchAndFillRevisions: function(documentPath) {
			// fill #rev-history with file versions
			// FIXME: this is no longer supported in OC10
			$.get(OC.generateUrl('apps/files_versions/ajax/getVersions.php?source={documentPath}&start={start}',
			                     { documentPath: documentPath, start: documentsMain.UI.revisionsStart }),
				  function(result) {
					  for(var key in result.data.versions) {
						  documentsMain.UI.addRevision(documentsMain.fileId,
						                               result.data.versions[key].version,
						                               result.data.versions[key].humanReadableTimestamp,
						                               documentPath);
					  }

					  // owncloud only gives 5 version at max in one go
					  documentsMain.UI.revisionsStart += 5;

					  if (result.data.endReached) {
						  // Remove 'More versions' button
						  $('#show-more-versions').addClass('hidden');
					  }
				  });
		},

		showRevHistory: function(documentPath) {
			$(document.body).prepend(documentsMain.UI.viewContainer);

			var revHistoryContainerTemplate = Handlebars.compile(documentsMain.UI.revHistoryContainerTemplate);
			var revHistoryContainer = revHistoryContainerTemplate({
				filename: documentsMain.fileName,
				moreVersionsLabel: t('richdocuments', 'More versions...'),
				closeButtonUrl: OC.imagePath('core', 'actions/close')
			});
			$('#revViewerContainer').prepend(revHistoryContainer);

			documentsMain.UI.revisionsStart = 0;

			// append current document first
			documentsMain.UI.addRevision(documentsMain.fileId, 0, t('richdocuments', 'Just now'), documentPath);

			// add "Show more versions" button
			$('#show-more-versions').click(function(e) {
				e.preventDefault();
				documentsMain.UI.fetchAndFillRevisions(documentPath);
			});

			// fake click to load first 5 versions
			$('#show-more-versions').click();

			// make these revisions clickable/attach functionality
			$('#revisionsContainer').on('click', '.versionPreview', function(e) {
				e.preventDefault();
				documentsMain.UI.showViewer(e.currentTarget.parentElement.dataset.version,
				                            e.currentTarget.parentElement.dataset.title);

				// mark only current <li> as active
				$(e.currentTarget.parentElement.parentElement).find('li').removeClass('active');
				$(e.currentTarget.parentElement).addClass('active');
			});

			$('#revisionsContainer').on('click', '.restoreVersion', function(e) {
				e.preventDefault();

				// close the viewer
				documentsMain.onCloseViewer();

				documentsMain.WOPIPostMessage($('#loleafletframe')[0], 'Host_VersionRestore', {Status: 'Pre_Restore'});

				documentsMain.$deferredVersionRestoreAck = $.Deferred();
				jQuery.when(documentsMain.$deferredVersionRestoreAck).
					done(function(args) {
						// restore selected version
						$.ajax({
							type: 'GET',
							url: e.currentTarget.href,
							success: function(response) {
								if (response.status === 'error') {
									documentsMain.UI.notify(t('richdocuments', 'Failed to revert the document to older version'));
								}

								// load the file again, it should get reverted now
								window.location.reload();
								documentsMain.overlay.documentOverlay('hide');
							}
						});
					});

				// resolve the deferred object immediately if client doesn't support version states
				if (!documentsMain.wopiClientFeatures || !documentsMain.wopiClientFeatures.VersionStates) {
					documentsMain.$deferredVersionRestoreAck.resolve();
				}
			});

			// fake click on first revision (i.e current revision)
			$('#revisionsContainer li').first().find('.versionPreview').click();
		},

		showEditor : function(action){
			if (documentsMain.loadError) {
				documentsMain.onEditorShutdown(documentsMain.loadErrorMessage + '\n' + documentsMain.loadErrorHint);
				return;
			}

			if (!documentsMain.renderComplete) {
				setTimeout(function() { documentsMain.UI.showEditor(action); }, 500);
				console.log('Waiting for page to render ...');
				return;
			}

			$(document.body).addClass("claro");
			$(document.body).prepend(documentsMain.UI.container);

			$('title').text(documentsMain.fileName + ' - ' + documentsMain.UI.mainTitle);

			var wopiSessionId = documentsMain.fileId + "_" +
				documentsMain.instanceId + "_" +
				documentsMain.version + "_" +
				documentsMain.sessionId;
			var ocurl = documentsMain._generateDocKey(wopiSessionId);
			// WOPISrc - URL that loolwsd will access (ie. pointing to ownCloud)
			// Include the unique instanceId in the WOPI URL as part of the fileId
			var wopiurl = window.location.protocol + '//' + window.location.host + ocurl;
			var wopisrc = encodeURIComponent(wopiurl);

			// urlsrc - the URL from discovery xml that we access for the particular
			// document; we add various parameters to that.
			// The discovery is available at
			//	 https://<loolwsd-server>:9980/hosting/discovery
			var urlsrc = documentsMain.urlsrc +
			    "WOPISrc=" + wopisrc +
			    "&title=" + encodeURIComponent(documentsMain.fileName) +
			    "&lang=" + OC.getLocale().replace('_', '-') +
			    "&closebutton=1" +
			    "&revisionhistory=1";
			if (!documentsMain.canEdit || action === "view") {
				urlsrc += "&permission=readonly";
			}

			// access_token - must be passed via a form post
			var access_token = encodeURIComponent(rd_token);

			// form to post the access token for WOPISrc
			var form = '<form id="loleafletform" name="loleafletform" target="loleafletframe" action="' + urlsrc + '" method="post">' +
			    '<input name="access_token" value="' + access_token + '" type="hidden"/></form>';

			// iframe that contains the Collabora Online
			var frame = '<iframe id="loleafletframe" name= "loleafletframe" allowfullscreen style="width:100%;height:100%;position:absolute;" onload="this.contentWindow.focus()"/>';

			$('#mainContainer').append(form);
			$('#mainContainer').append(frame);

			// Listen for App_LoadingStatus as soon as possible
			$('#loleafletframe').ready(function() {
				var editorInitListener = function(e) {
					var msg = JSON.parse(e.data);
					if (msg.MessageId === 'App_LoadingStatus') {
						documentsMain.wopiClientFeatures = msg.Values.Features;
						window.removeEventListener('message', editorInitListener, false);
					}
				};
				window.addEventListener('message', editorInitListener, false);
			});

			$('#loleafletframe').load(function(){
				// And start listening to incoming post messages
				window.addEventListener('message', function(e){
					if (documentsMain.isViewerMode) {
						return;
					}

					var msg, msgId;
					try {
						msg = JSON.parse(e.data);
						msgId = msg.MessageId;
						var args = msg.Values;
						var deprecated = !!args.Deprecated;
					} catch(exc) {
						msgId = e.data;
					}

					if (msgId === 'UI_Close' || msgId === 'close' /* deprecated */) {
						// If a postmesage API is deprecated, we must ignore it and wait for the standard postmessage
						// (or it might already have been fired)
						if (deprecated)
							return;

						documentsMain.onClose();
					} else if (msgId === 'UI_FileVersions' || msgId === 'rev-history' /* deprecated */) {
						if (deprecated)
							return;

						documentsMain.UI.showRevHistory(documentsMain.fullPath);
					} else if (msgId === 'UI_SaveAs') {
						// TODO it's not possible to enter the
						// filename into the OC.dialogs.filepicker; so
						// it will be necessary to use an own tree
						// view or something :-(
						//OC.dialogs.filepicker(t('richdocuments', 'Save As'),
						//	function(val) {
						//		console.log(val);
						//		documentsMain.WOPIPostMessage($('#loleafletframe')[0], 'Action_SaveAs', {'Filename': val});
						//	}, false, null, true);
						OC.dialogs.prompt(t('richdocuments', 'Please enter filename to which this document should be stored.'),
						                  t('richdocuments', 'Save As'),
						                  function(result, value) {
							                  if (result === true && value) {
								                  documentsMain.WOPIPostMessage($('#loleafletframe')[0], 'Action_SaveAs', {'Filename': value});
							                  }
						                  },
						                  true,
						                  t('richdocuments', 'New filename'),
						                  false).then(function() {
							                var $dialog = $('.oc-dialog:visible');
							                var $buttons = $dialog.find('button');
							                $buttons.eq(0).text(t('richdocuments', 'Cancel'));
							                $buttons.eq(1).text(t('richdocuments', 'Save'));
							                });
					} else if (msgId === 'App_VersionRestore') {
						if (!documentsMain.$deferredVersionRestoreAck)
						{
							console.warn('No version restore deferred object found.');
							return;
						}

						if (args.Status === 'Pre_Restore_Ack') {
							// user instructed to restore the version
							documentsMain.$deferredVersionRestoreAck.resolve();
						}
					}
				});

				// Tell the LOOL iframe that we are ready now
				documentsMain.WOPIPostMessage($('#loleafletframe')[0], 'Host_PostmessageReady', {});

				// LOOL Iframe is ready, turn off our overlay
				// This should ideally be taken off when we receive App_LoadingStatus, but
				// for backward compatibility with older lool, lets keep it here till we decide
				// to break older lools
				documentsMain.overlay.documentOverlay('hide');
			});

			// submit that
			$('#loleafletform').submit();
		},

		hideEditor : function(){
			// Fade out editor
			$('#mainContainer').fadeOut('fast', function() {
				$('#mainContainer').remove();
				$('#content-wrapper').fadeIn('fast');
				$(document.body).removeClass('claro');
				$('title').text(documentsMain.UI.mainTitle);
			});
		},

		showSave : function (){
			$('#odf-close').hide();
			$('#saving-document').show();
		},

		hideSave : function(){
			$('#saving-document').hide();
			$('#odf-close').show();
		},

		showProgress : function(message){
			if (!message){
				message = '&nbsp;';
			}
			$('.documentslist .progress div').text(message);
			$('.documentslist .progress').show();
		},

		hideProgress : function(){
			$('.documentslist .progress').hide();
		},

		showLostConnection : function(){
			$('#memberList .memberListButton').css({opacity : 0.3});
			$('#ocToolbar').children(':not(#document-title)').hide();
			$('<div id="connection-lost"></div>').prependTo('#memberList');
			$('<div id="warning-connection-lost">' + t('richdocuments', 'No connection to server. Trying to reconnect.') +'<img src="'+ OC.imagePath('core', 'loading-dark.gif') +'" alt="" /></div>').prependTo('#ocToolbar');
		},

		hideLostConnection : function() {
			$('#connection-lost,#warning-connection-lost').remove();
			$('#ocToolbar').children(':not(#document-title,#saving-document)').show();
			$('#memberList .memberListButton').css({opacity : 1});
		},

		notify : function(message){
			OC.Notification.show(message);
			setTimeout(OC.Notification.hide, 10000);
		}
	},

	onStartup: function() {
		documentsMain.UI.init();

		// Does anything indicate that we need to autostart a session?
		var fileId = getURLParameter('fileId');
		if (fileId != 'null')
			documentsMain.fileId = fileId;
		var dir = getURLParameter('dir');
		if (dir != 'null')
			documentsMain.returnToDir = dir;
		var shareToken = getURLParameter('shareToken');
		if (shareToken != 'null')
			documentsMain.returnToShare = shareToken;

		// this will launch the document with given fileId
		documentsMain.show(documentsMain.fileId);

		if (documentsMain.fileId) {
			documentsMain.overlay.documentOverlay('show');
			documentsMain.prepareSession();
		}
	},

	WOPIPostMessage: function(iframe, msgId, values) {
		if (iframe) {
			var msg = {
				'MessageId': msgId,
				'SendTime': Date.now(),
				'Values': values
			};

			iframe.contentWindow.postMessage(JSON.stringify(msg), '*');
		}
	},

	prepareSession : function(){
		documentsMain.isEditorMode = true;
		documentsMain.overlay.documentOverlay('show');
	},

	prepareGrid : function(){
		documentsMain.isEditorMode = false;
		documentsMain.overlay.documentOverlay('hide');
	},

	initSession: function() {
		documentsMain.urlsrc = rd_urlsrc;
		documentsMain.fullPath = rd_path;
		documentsMain.token = rd_token;

		$(documentsMain.toolbar).appendTo('#header');

		documentsMain.canShare = typeof OC.Share !== 'undefined' && rd_permissions & OC.PERMISSION_SHARE;

		// fade out file list and show the document
		$('#content-wrapper').fadeOut('fast').promise().done(function() {
			documentsMain.fileId = rd_fileId;
			documentsMain.instanceId = rd_instanceId;
			documentsMain.version = rd_version;
			documentsMain.sessionId = rd_sessionId;
			documentsMain.fileName = rd_title;
			documentsMain.canEdit = Boolean(rd_permissions & OC.PERMISSION_UPDATE);

			documentsMain.loadDocument();
		});
	},

	view: function(id){
		OC.addScript('richdocuments', 'viewer/viewer', function() {
			documentsMain.prepareGrid();
			$(window).off('beforeunload');
			$(window).off('unload');
			var path = $('li[data-fileid='+ id +']>a').attr('href');
			odfViewer.isDocuments = true;
			odfViewer.onView(path);
		});
	},

	onCreateODT: function(event){
		event.preventDefault();
		documentsMain.create('application/vnd.oasis.opendocument.text');
	},

	onCreateODS: function(event){
		event.preventDefault();
		documentsMain.create('application/vnd.oasis.opendocument.spreadsheet');
	},

	onCreateODP: function(event){
		event.preventDefault();
		documentsMain.create('application/vnd.oasis.opendocument.presentation');
	},

	onCreateDOCX: function(event){
		event.preventDefault();
		documentsMain.create('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	},

	onCreateXLSX: function(event){
		event.preventDefault();
		documentsMain.create('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	},

	onCreatePPTX: function(event){
		event.preventDefault();
		documentsMain.create('application/vnd.openxmlformats-officedocument.presentationml.presentation');
	},

	create: function(mimetype){
		var docElem = $('.documentslist .template').clone();
		docElem.removeClass('template');
		docElem.addClass('document');
		docElem.insertAfter('.documentslist .template');
		docElem.show();
		$.post(
			OC.generateUrl('apps/richdocuments/ajax/documents/create'),
			{ mimetype : mimetype },
			function(response){
				if (response && response.fileid){
					documentsMain.prepareSession();
					window.location = documentsMain._generateFullUrl(response.fileid);
				} else {
					if (response && response.message){
						documentsMain.UI.notify(response.message);
					}
					documentsMain.show();
				}
			}
		);
	},

	loadDocument: function() {
		var action = $('li[data-fileid='+ documentsMain.fileId +']>a').attr('action');
		documentsMain.UI.showEditor(
			action
		);
	},

	onEditorShutdown : function (message){
		OC.Notification.show(message);

		$(window).off('beforeunload');
		$(window).off('unload');
		if (documentsMain.isEditorMode){
			documentsMain.isEditorMode = false;
			parent.location.hash = "";
		} else {
			setTimeout(OC.Notification.hide, 7000);
		}
		documentsMain.prepareGrid();
		documentsMain.UI.hideEditor();

		documentsMain.show();
		$('footer,nav').show();
	},

	onClose: function() {
		if (!documentsMain.isEditorMode){
			return;
		}

		documentsMain.isEditorMode = false;
		$(window).off('beforeunload');
		$(window).off('unload');
		parent.location.hash = "";

		$('footer,nav').show();
		documentsMain.UI.hideEditor();
		$('#ocToolbar').remove();

		if (documentsMain.returnToDir) {
			documentsMain.overlay.documentOverlay('show');
			window.location = OC.generateUrl('apps/files?dir={dir}', {dir: documentsMain.returnToDir}, {escape: false});
		} else if (documentsMain.returnToShare) {
			documentsMain.overlay.documentOverlay('show');
			window.location = OC.generateUrl('s/{shareToken}', {shareToken: documentsMain.returnToShare}, {escape: false});
		} else {
			documentsMain.show();
		}
	},

	onCloseViewer: function() {
		$('#revisionsContainer *').off();

		$('#revPanelContainer').remove();
		$('#revViewerContainer').remove();
		documentsMain.isViewerMode = false;
		documentsMain.UI.revisionsStart = 0;

		$('#loleafletframe').focus();
	},

	show: function(fileId){
		documentsMain.UI.showProgress(t('richdocuments', 'Loading documents...'));
		documentsMain.docs.documentGrid('render', fileId);
		documentsMain.UI.hideProgress();
	}
};

//init
var Files = Files || {
	// FIXME: copy/pasted from Files.isFileNameValid, needs refactor into core
	isFileNameValid:function (name) {
		if (name === '.') {
			throw t('files', '\'.\' is an invalid file name.');
		} else if (name.length === 0) {
			throw t('files', 'File name cannot be empty.');
		}

		// check for invalid characters
		var invalid_characters = ['\\', '/', '<', '>', ':', '"', '|', '?', '*'];
		for (var i = 0; i < invalid_characters.length; i++) {
			if (name.indexOf(invalid_characters[i]) !== -1) {
				throw t('files', "Invalid name, '\\', '/', '<', '>', ':', '\"', '|', '?' and '*' are not allowed.");
			}
		}
		return true;
	},

	updateStorageStatistics: function(){}
},
FileList = FileList || {};

FileList.getCurrentDirectory = function(){
	return $('#dir').val() || '/';
};

FileList.highlightFiles = function(files, highlightFunction) {
};

FileList.findFile = function(filename) {
	var documents = documentsMain.docs.documentGrid('option').documents;
	return _.find(documents, function(aFile) {
				return (aFile.name === filename);
			}) || false;
};

FileList.generatePreviewUrl = function(urlSpec) {
	urlSpec = urlSpec || {};
	if (!urlSpec.x) {
		urlSpec.x = 32;
	}
	if (!urlSpec.y) {
		urlSpec.y = 32;
	}
	urlSpec.x *= window.devicePixelRatio;
	urlSpec.y *= window.devicePixelRatio;
	urlSpec.x = Math.ceil(urlSpec.x);
	urlSpec.y = Math.ceil(urlSpec.y);
	urlSpec.forceIcon = 0;
	return OC.generateUrl('/core/preview.png?') + $.param(urlSpec);
};

FileList.isFileNameValid = function (name) {
	var trimmedName = name.trim();
	if (trimmedName === '.'	|| trimmedName === '..') {
		throw t('files', '"{name}" is an invalid file name.', {name: name});
	} else if (trimmedName.length === 0) {
		throw t('files', 'File name cannot be empty.');
	}
	return true;
};

FileList.setViewerMode = function(){
};
FileList.findFile = function(fileName){
	fullPath = escapeHTML(FileList.getCurrentDirectory + '/' + fileName);
	return !!$('.documentslist .document:not(.template,.progress) a[original-title="' + fullPath + '"]').length;
};

$(document).ready(function() {
	if (!OCA.Files) {
		OCA.Files = {};
		OCA.Files.App = {};
		OCA.Files.App.fileList = FileList;
	}

	if (!OC.Share) {
		OC.Share = {};
	}

	window.Files = FileList;

	documentsMain.docs = $('.documentslist').documentGrid();
	documentsMain.overlay = $('<div id="documents-overlay" class="icon-loading"></div><div id="documents-overlay-below" class="icon-loading-dark"></div>').documentOverlay();

	$('li.document a').tipsy({fade: true, live: true});

	$('.documentslist').on('click', 'li:not(.add-document)', function(event) {
		event.preventDefault();

		if (documentsMain.isEditorMode){
			return;
		}

		var item = $(this).find('a');
		if (item.attr('urlsrc') === undefined) {
			OC.Notification.showTemporary(t('richdocuments', 'Failed to open ' + item.attr('original-title') + ', file not supported.'));
			return;
		}

		documentsMain.prepareSession();
		var fileId = $(this).attr('data-fileid');
		if (fileId) {
			window.location = documentsMain._generateFullUrl(fileId);
		}
	});

	$('.add-document').on('click', '.add-odt', documentsMain.onCreateODT);
	$('.add-document').on('click', '.add-ods', documentsMain.onCreateODS);
	$('.add-document').on('click', '.add-odp', documentsMain.onCreateODP);
	$('.add-document').on('click', '.add-docx', documentsMain.onCreateDOCX);
	$('.add-document').on('click', '.add-xlsx', documentsMain.onCreateXLSX);
	$('.add-document').on('click', '.add-pptx', documentsMain.onCreatePPTX);

	var supportAjaxUploadFn;
	if (OC.Uploader) { // OC.Uploader in oc10 but OC.Upload in < 10
		OC.Uploader._isReceivedSharedFile = function () {
			return false;
		};
		supportAjaxUploadFn = OC.Uploader.prototype._supportAjaxUploadWithProgress;
	} else if (OC.Upload) {
		OC.Upload._isReceivedSharedFile = function () {
			return false;
		};
		supportAjaxUploadFn = supportAjaxUploadWithProgress;
	}

	var file_upload_start = $('#file_upload_start');
	if (typeof supportAjaxUploadFn !== 'undefined' &&
	    supportAjaxUploadFn()) {
		file_upload_start.bind('fileuploadstart', function(e, data) {
			$('#upload').addClass('icon-loading');
			$('.add-document .upload').css({opacity:0});
		});
	}
	file_upload_start.bind('fileuploaddone', function() {
		$('#upload').removeClass('icon-loading');
		$('.add-document .upload').css({opacity:0.7});
		documentsMain.show();
	});
	file_upload_start.fileupload();

	// hide the documentlist until we know we don't have any fileId
	$('.documentslist').hide();
	documentsMain.onStartup();
});
