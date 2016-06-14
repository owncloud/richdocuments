/*globals $,OC,fileDownloadPath,t,document,odf,alert,require,dojo,runtime */

$.widget('oc.documentGrid', {
	options : {
		context : '.documentslist',
		documents : {},
		sessions : {},
		members : {}
	},

	_create : function (){

	},

	render : function(fileId){
		var that = this;
		jQuery.when(this._load(fileId))
			.then(function(){
				that._render();
			});
	},

	add : function(document) {
		var docElem = $(this.options.context + ' .template').clone(),
			a = docElem.find('a')
		;

		//Fill an element
		docElem.removeClass('template').attr('data-id', document.fileid);
		a.css('background-image', 'url("'+document.icon+'")')
			.attr('href', OC.generateUrl('apps/files/download{file}',{file:document.path}))
			.attr('title', document.path)
			.attr('original-title', document.path)
			.attr('urlsrc', document.urlsrc)
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
		var that = this;
		var def = new $.Deferred();
		var url = 'apps/richdocuments/ajax/documents/list';
		var dataObj = {};
		if (fileId){
			url = 'apps/richdocuments/ajax/documents/get/{fileId}';
			dataObj = { fileId: fileId };
		}

		$.getJSON(OC.generateUrl(url, dataObj))
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
					that.options.sessions = result.sessions;
					that.options.members = result.members;
				}
				def.resolve();
			})
			.fail(function(data){
				console.log(t('richdocuments','Failed to load documents.'));
			});
		return def;
	},

	_render : function (data){
		var that = this,
			documents = data && data.documents || this.options.documents,
			sessions = data && data.sessions || this.options.sessions,
			members = data && data.members || this.options.members,
			hasDocuments = false
		;

		$(this.options.context + ' .document:not(.template,.progress)').remove();

		if (documentsMain.loadError) {
			$(this.options.context).after('<div id="errormessage">'
				+ '<p>' + documentsMain.loadErrorMessage + '</p><p>'
				+ documentsMain.loadErrorHint
				+ '</p></div>'
			);
			return;
		}

		$.each(documents, function(i, document){
			hasDocuments = true;
			that.add(document);
		});

		$.each(sessions, function(i, session){
			if (members[session.es_id].length > 0) {
				var docElem = $(that.options.context + ' .document[data-id="'+session.file_id+'"]');
				if (docElem.length > 0) {
					docElem.attr('data-esid', session.es_id);
					docElem.find('label').after('<img class="svg session-active" src="'+OC.imagePath('core','places/contacts-dark')+'">');
					docElem.addClass('session');
				} else {
					console.log('Could not find file '+session.file_id+' for session '+session.es_id);
				}
			}
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
	isEditormode : false,
	isGuest : false,
	memberId : false,
	esId : false,
	ready :false,
	fileName: null,
	baseName: null,
	canShare : false,
	loadError : false,
	loadErrorMessage : '',
	loadErrorHint : '',
	toolbar : '<div id="ocToolbar"><div id="ocToolbarInside"></div><span id="toolbar" class="claro"></span></div>',
	returnToDir : null, // directory where we started from in the 'Files' app

	UI : {
		/* Editor wrapper HTML */
		container : '<div id="mainContainer" class="claro">' +
					'</div>',

		/* Previous window title */
		mainTitle : '',

		init : function(){
			documentsMain.UI.mainTitle = $('title').text();
		},

		showEditor : function(title){
			if (documentsMain.isGuest){
				// !Login page mess wih WebODF toolbars
				$(document.body).attr('id', 'body-user');
			}

			if (documentsMain.loadError) {
				documentsMain.onEditorShutdown(t('richdocuments', documentsMain.loadErrorMessage + '\n' + documentsMain.loadErrorHint));
				return;
			}

			$(document.body).addClass("claro");
			$(document.body).prepend(documentsMain.UI.container);

			$('title').text(title + ' - ' + documentsMain.UI.mainTitle);

			$.get(OC.generateUrl('apps/richdocuments/wopi/token/{fileId}', { fileId: documentsMain.fileId }),
				function (result) {
					if (!result || result.status === 'error') {
						if (result && result.message){
							documentsMain.IU.notify(result.message);
						}
						documentsMain.onEditorShutdown(t('richdocuments', 'Failed to aquire access token. Please re-login and try again.'));
						return;
					}

					// WOPISrc - URL that loolwsd will access (ie. pointing to ownCloud)
					var wopiurl = window.location.protocol + '//' + window.location.host + OC.generateUrl('apps/richdocuments/wopi/files/{file_id}', {file_id: documentsMain.fileId});
					var wopisrc = encodeURIComponent(wopiurl);

					// urlsrc - the URL from discovery xml that we access for the particular
					// document; we add various parameters to that.
					// The discovery is available at
					//   https://<loolwsd-server>:9980/hosting/discovery
					var urlsrc = $('li[data-id='+ documentsMain.fileId +']>a').attr('urlsrc') +
						"WOPISrc=" + wopisrc +
						"&title=" + encodeURIComponent(title) +
						"&lang=" + $('li[data-id='+ documentsMain.fileId +']>a').attr('lolang') +
						"&closebutton=1";

					// access_token - must be passed via a form post
					var access_token = encodeURIComponent(result.token);

					// form to post the access token for WOPISrc
					var form = '<form id="loleafletform" name="loleafletform" target="loleafletframe" action="' + urlsrc + '" method="post">' +
						'<input name="access_token" value="' + access_token + '" type="hidden"/></form>';

					// iframe that contains the Collabora Online
					var frame = '<iframe id="loleafletframe" name= "loleafletframe" allowfullscreen style="width:100%;height:100%;position:absolute;"/>';

					$('#mainContainer').append(form);
					$('#mainContainer').append(frame);

					// handler for the 'Close' button - we have enabled it via closebutton=1
					$('#loleafletframe').load(function(){
						documentsMain.overlay.documentOverlay('hide');
						window.addEventListener('message', function(e){
							if (e.data === 'close') {
								documentsMain.onClose();
							}
						});
					});

					// submit that
					$('#loleafletform').submit();
			});
		},

		hideEditor : function(){
			if (documentsMain.isGuest){
				// !Login page mess wih WebODF toolbars
				$(document.body).attr('id', 'body-login');
				$('footer,nav').show();
			}

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
		var fileId;
		documentsMain.UI.init();

		if (!OC.currentUser){
			documentsMain.isGuest = true;

			if ($("[name='document']").val()){
				$(documentsMain.toolbar).appendTo('#header');
				documentsMain.prepareSession();
				documentsMain.joinSession(
					$("[name='document']").val()
				);
			}

		} else {
			// Does anything indicate that we need to autostart a session?
			fileId = parent.location.hash.replace(/^\W*/, '');

			if (fileId.indexOf('_') >= 0) {
				documentsMain.returnToDir = unescape(fileId.replace(/^[^_]*_/, ''));
				fileId = fileId.replace(/_.*/, '');
			}
		}

		documentsMain.show(fileId);

		if (fileId) {
			documentsMain.overlay.documentOverlay('show');
			documentsMain.prepareSession();
			documentsMain.joinSession(fileId);
		}

		documentsMain.ready = true;
	},

	prepareSession : function(){
		documentsMain.isEditorMode = true;
		documentsMain.overlay.documentOverlay('show');
		$(window).on("unload", documentsMain.onTerminate);
	},

	prepareGrid : function(){
		documentsMain.isEditorMode = false;
		documentsMain.overlay.documentOverlay('hide');
	},

	initSession: function(response) {
		if(response && (response.id && !response.es_id)){
			return documentsMain.view(response.id);
		}

		$('footer,nav').hide();
		$(documentsMain.toolbar).appendTo('#header');

		if (!response || !response.status || response.status==='error'){
			documentsMain.onEditorShutdown(t('richdocuments', 'Failed to load this document. Please check if it can be opened with an external editor. This might also mean it has been unshared or deleted recently.'));
			return;
		}

		//Wait for 3 sec if editor is still loading
		if (!documentsMain.ready){
			setTimeout(function(){ documentsMain.initSession(response); }, 3000);
			console.log('Waiting for the editor to start...');
			return;
		}

		var pollUrl = documentsMain.isGuest
				? OC.generateUrl('apps/richdocuments/session/guest/poll/{token}', {'token' : $("[name='document']").val()})
				: OC.generateUrl('apps/richdocuments/session/user/poll'),
			saveUrl = documentsMain.isGuest
				? OC.generateUrl('apps/richdocuments/session/guest/save/{token}', {'token' : $("[name='document']").val()})
				: OC.generateUrl('apps/richdocuments/session/user/save')
				;
		documentsMain.canShare = !documentsMain.isGuest
				&& typeof OC.Share !== 'undefined' && response.permissions & OC.PERMISSION_SHARE;

		// fade out file list and show the cloudsuite
		$('#content-wrapper').fadeOut('fast').promise().done(function() {

			documentsMain.fileId = response.file_id;
			documentsMain.fileName = response.title;

			documentsMain.esId = response.es_id;
			documentsMain.memberId = response.member_id;

			documentsMain.loadDocument();

			if (documentsMain.isGuest){
				$('#odf-close').text(t('richdocuments', 'Save') );
				$('#odf-close').removeClass('icon-view-close');
			}
		});
	},


	joinSession: function(fileId) {
		console.log('joining session '+fileId);
		var url;
		if (documentsMain.isGuest){
			url = OC.generateUrl('apps/richdocuments/session/guest/join/{token}', {token: fileId});
		} else {
			url = OC.generateUrl('apps/richdocuments/session/user/join/{file_id}', {file_id: fileId});
		}
		$.post(
			url,
			{ name : $("[name='memberName']").val() },
			documentsMain.initSession
		);
	},

	view : function(id){
		OC.addScript('richdocuments', 'viewer/viewer', function() {
			documentsMain.prepareGrid();
			$(window).off('beforeunload');
			$(window).off('unload');
			var path = $('li[data-id='+ id +']>a').attr('href');
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
					docElem.attr('data-id', response.fileid);
					docElem.find('a').attr('urlsrc', response.urlsrc);
					docElem.find('a').attr('lolang', response.lolang);
					documentsMain.prepareSession();
					documentsMain.joinSession(response.fileid);
				} else {
					if (response && response.message){
						documentsMain.UI.notify(response.message);
					}
					documentsMain.show();
				}
			}

		);
	},

	changeNick: function(memberId, name, node){
		var url = OC.generateUrl('apps/richdocuments/ajax/user/rename');
		$.ajax({
			url: url,
			type: "POST",
			data: JSON.stringify({
				name : name,
				memberId : memberId
			}),
			contentType: 'application/json; charset=utf-8',
			dataType:"json",
			success: function(result) {
				if (result && result.status === 'error') {
					if (result.message){
						documentsMain.UI.notify(result.message);
					}
					return;
				}
			}
		});
	},

	onNickChange: function(memberId, fullNameNode){
		if (!documentsMain.isGuest || memberId !== documentsMain.memberId){
			return;
		}
		if ($(fullNameNode.parentNode).children('input').length !== 0){
			return;
		}

		var input = $('<input type="text"/>').val($(fullNameNode).attr('fullname'));
		$(fullNameNode.parentNode).append(input);
		$(fullNameNode).hide();

		input.on('blur', function(){
			var newName = input.val();
			if (!newName || newName === name) {
				input.tipsy('hide');
				input.remove();
				$(fullNameNode).show();
				return;
			}
			else {
				try {
					input.tipsy('hide');
					input.removeClass('error');
					input.tipsy('hide');
					input.remove();
					$(fullNameNode).show();
					documentsMain.changeNick(memberId, newName, fullNameNode);
				}
				catch (error) {
					input.attr('title', error);
					input.tipsy({gravity: 'n', trigger: 'manual'});
					input.tipsy('show');
					input.addClass('error');
				}
			}
		});
		input.on('keyup', function(event){
			if (event.keyCode === 27) {
				// cancel by putting in an empty value
				$(this).val('');
				$(this).blur();
				event.preventDefault();
			}
			if (event.keyCode === 13) {
				$(this).blur();
				event.preventDefault();
			}
		});
		input.focus();
		input.selectRange(0, name.length);
	},

	loadDocument: function() {
		documentsMain.UI.showEditor(documentsMain.fileName);
	},

	saveDocumentBack: function() {
		var url = OC.generateUrl('apps/richdocuments/save/{file_id}', {file_id: documentsMain.fileId});
		$.post(
				url,
				{ basename : documentsMain.baseName },
				function(result) {
					if (result && result.status === 'error') {
						if (result.message){
							documentsMain.IU.notify(result.message);
						}
						documentsMain.onEditorShutdown(t('richdocuments', 'Failed to save this document. Please check if it can be saved with an external editor. This might also mean it has been unshared or deleted recently.'));
						return;
					}
				}
			  );
	},

	closeDocument: function() {
		var url = OC.generateUrl('apps/richdocuments/close/{file_id}', {file_id: documentsMain.fileId});
		$.post(
				url,
				{ basename : documentsMain.baseName }
			  );
	},

	renameDocument: function(name) {
		var url = OC.generateUrl('apps/richdocuments/ajax/documents/rename/{file_id}', {file_id: documentsMain.fileId});
		$.post(
			url,
			{ name : name },
			function(result) {
				if (result && result.status === 'error') {
					if (result.message){
						documentsMain.IU.notify(result.message);
					}
					return;
				}
				documentsMain.fileName = name;
				$('title').text(documentsMain.UI.mainTitle + '| ' + name);
				$('#document-title').text(name);
			}
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
		documentsMain.closeDocument();
		$('#ocToolbar').remove();

		if (documentsMain.returnToDir) {
			window.location = OC.generateUrl('apps/files?dir={dir}', {dir: documentsMain.returnToDir});
		} else {
			documentsMain.show();
		}
	},

	onTerminate: function(){
		var url = '';
		if (documentsMain.isGuest){
			url = OC.generateUrl('apps/richdocuments/ajax/user/disconnectGuest/{member_id}', {member_id: documentsMain.memberId});
		} else {
			url = OC.generateUrl('apps/richdocuments/ajax/user/disconnect/{member_id}', {member_id: documentsMain.memberId});
		}
		$.ajax({
				type: "POST",
				url: url,
				data: {esId: documentsMain.esId},
				dataType: "json",
				async: false // Should be sync to complete before the page is closed
		});

		if (documentsMain.isGuest){
			$('footer,nav').show();
		}
	},

	show: function(fileId){
		if (documentsMain.isGuest){
			return;
		}
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
}

FileList.isFileNameValid = function (name) {
	var trimmedName = name.trim();
	if (trimmedName === '.'	|| trimmedName === '..') {
		throw t('files', '"{name}" is an invalid file name.', {name: name});
	} else if (trimmedName.length === 0) {
		throw t('files', 'File name cannot be empty.');
	}
	return true;
}

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
		if ($(this).attr('data-id')){
			documentsMain.joinSession($(this).attr('data-id'));
		}
	});

	$('.add-document').on('click', '.add-odt', documentsMain.onCreateODT);
	$('.add-document').on('click', '.add-ods', documentsMain.onCreateODS);
	$('.add-document').on('click', '.add-odp', documentsMain.onCreateODP);

	var file_upload_start = $('#file_upload_start');
	if (typeof supportAjaxUploadWithProgress !== 'undefined' && supportAjaxUploadWithProgress()) {
		file_upload_start.on('fileuploadstart', function(e, data) {
			$('#upload').addClass('icon-loading');
			$('.add-document .upload').css({opacity:0});
		});
	}
	file_upload_start.on('fileuploaddone', function(){
		$('#upload').removeClass('icon-loading');
		$('.add-document .upload').css({opacity:0.7});
		documentsMain.show();
	});

	documentsMain.onStartup();
});
