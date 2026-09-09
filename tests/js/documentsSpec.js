/**
 * @copyright Copyright (c) 2026, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

/* globals documentsMain */

/**
 * Specs for the parts of js/documents.js that decide where the browser may
 * navigate to and which origin it exchanges post messages with. The counterpart
 * on the server side is tests/unit/Controller/DocumentControllerTest.php.
 */
describe('documentsMain', function() {
	describe('_absoluteHttpUrl', function() {
		// the return value is used as a navigation target, so everything that is
		// not an absolute http(s) url has to be refused
		var refused = [
			undefined,
			null,
			'',
			'javascript:alert(document.domain)',
			'JaVaScRiPt:alert(document.domain)',
			'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
			'ftp://remote.example.com/',
			// scheme relative, would silently keep the current scheme
			'//evil.tld',
			'//evil.tld/owncloud',
			// relative, only a remote server is a valid return target
			'/index.php/apps/files',
			'index.php/apps/files',
			'remote.example.com',
			// a scheme is not enough, there has to be a host
			'https://'
		];

		refused.forEach(function(value) {
			it('refuses ' + value, function() {
				expect(documentsMain._absoluteHttpUrl(value)).toBeNull();
			});
		});

		// a path has to be accepted, ownCloud can be installed in a subdirectory
		var accepted = {
			'http://remote.example.com': 'http://remote.example.com/',
			'https://remote.example.com': 'https://remote.example.com/',
			'https://remote.example.com/': 'https://remote.example.com/',
			'https://remote.example.com/owncloud': 'https://remote.example.com/owncloud',
			'https://remote.example.com:8443/owncloud': 'https://remote.example.com:8443/owncloud',
			'HTTPS://remote.example.com': 'https://remote.example.com/'
		};

		Object.keys(accepted).forEach(function(value) {
			it('accepts ' + value, function() {
				expect(documentsMain._absoluteHttpUrl(value)).toBe(accepted[value]);
			});
		});
	});

	describe('_wopiOrigin', function() {
		var urlsrc;

		beforeEach(function() {
			urlsrc = documentsMain.urlsrc;
		});

		afterEach(function() {
			documentsMain.urlsrc = urlsrc;
		});

		it('takes the origin from an absolute discovery urlsrc', function() {
			documentsMain.urlsrc = 'https://collabora.example.com/browser/1a2b3c/cool.html?';

			expect(documentsMain._wopiOrigin()).toBe('https://collabora.example.com');
		});

		it('resolves a discovery urlsrc that is relative to this server', function() {
			documentsMain.urlsrc = '/collabora/browser/1a2b3c/cool.html?';

			expect(documentsMain._wopiOrigin()).toBe(window.location.origin);
		});

		// new URL() throws for none of these, so each one would otherwise end up
		// as an origin that post messages are accepted from and sent to
		var noOrigin = {
			// discovery returns no urlsrc when it cannot be read. resolving an
			// empty value against the base url would make this server its own
			// Collabora Online origin
			'nothing at all': '',
			'an undefined urlsrc': undefined,
			'a null urlsrc': null,
			// opaque origins serialize to the string 'null', which is what a
			// sandboxed frame reports as its own origin
			'a javascript: urlsrc': 'javascript:alert(document.domain)',
			'a data: urlsrc': 'data:text/html,<script>alert(1)</script>',
			'a urlsrc that is not http(s)': 'ftp://collabora.example.com/cool.html'
		};

		Object.keys(noOrigin).forEach(function(name) {
			it('has no origin for ' + name, function() {
				documentsMain.urlsrc = noOrigin[name];

				// null, and in particular not this server and not the string
				// 'null' - without a known Collabora Online server the messages
				// have to go nowhere rather than somewhere
				expect(documentsMain._wopiOrigin()).toBeNull();
			});
		});
	});

	describe('WOPIPostMessage', function() {
		var urlsrc, iframe;

		beforeEach(function() {
			urlsrc = documentsMain.urlsrc;
			documentsMain.urlsrc = 'https://collabora.example.com/browser/1a2b3c/cool.html?';
			iframe = {contentWindow: {postMessage: jasmine.createSpy('postMessage')}};
		});

		afterEach(function() {
			documentsMain.urlsrc = urlsrc;
		});

		it('posts to the Collabora Online origin and not to any origin', function() {
			documentsMain.WOPIPostMessage(iframe, 'Action_Save', {Notify: true});

			expect(iframe.contentWindow.postMessage.calls.count()).toBe(1);

			var args = iframe.contentWindow.postMessage.calls.mostRecent().args;
			expect(args[1]).toBe('https://collabora.example.com');
			expect(args[1]).not.toBe('*');

			var message = JSON.parse(args[0]);
			expect(message.MessageId).toBe('Action_Save');
			expect(message.Values).toEqual({Notify: true});
		});

		it('posts nothing when the Collabora Online origin is unknown', function() {
			spyOn(documentsMain, '_wopiOrigin').and.returnValue(null);

			documentsMain.WOPIPostMessage(iframe, 'Action_Save', {Notify: true});

			expect(iframe.contentWindow.postMessage).not.toHaveBeenCalled();
		});

		it('does nothing without an iframe', function() {
			expect(function() {
				documentsMain.WOPIPostMessage(null, 'Action_Save', {Notify: true});
			}).not.toThrow();
		});
	});

	describe('onStartup', function() {
		var getURLParameterOriginal, urlParameters;

		beforeEach(function() {
			urlParameters = {};
			getURLParameterOriginal = window.getURLParameter;
			// core returns the string 'null' for a parameter that is not set,
			// see getURLParameter() in core/js/js.js
			window.getURLParameter = function(name) {
				return name in urlParameters ? urlParameters[name] : 'null';
			};

			documentsMain.returnToServer = null;
			documentsMain.returnToShare = null;
			documentsMain.returnToDir = null;

			spyOn(documentsMain, 'show');
			spyOn(documentsMain.UI, 'init');
		});

		afterEach(function() {
			window.getURLParameter = getURLParameterOriginal;

			documentsMain.returnToServer = null;
			documentsMain.returnToShare = null;
		});

		it('takes the return url from the hidden input rendered by the server', function() {
			$('#testArea').append(
				'<input type="hidden" id="return-to-server" value="https://remote.example.com/owncloud"/>'
			);
			urlParameters = {shareToken: 'sharetoken'};

			documentsMain.onStartup();

			expect(documentsMain.returnToServer).toBe('https://remote.example.com/owncloud');
			expect(documentsMain.returnToShare).toBeFalsy();
		});

		it('ignores a server url parameter', function() {
			urlParameters = {
				shareToken: 'sharetoken',
				server: 'javascript:alert(document.domain)'
			};

			documentsMain.onStartup();

			expect(documentsMain.returnToServer).toBeFalsy();
			// a share that was not opened from a remote server returns to itself
			expect(documentsMain.returnToShare).toBe('sharetoken');
		});
	});

	describe('onClose', function() {
		beforeEach(function() {
			documentsMain.isEditorMode = true;
			documentsMain.returnToDir = null;
			documentsMain.returnToShare = null;

			spyOn(documentsMain, 'show');
			spyOn(documentsMain.overlay, 'documentOverlay');
			spyOn(documentsMain.UI, 'hideEditor');
		});

		afterEach(function() {
			documentsMain.isEditorMode = false;
			documentsMain.returnToServer = null;
		});

		// only the refusal can be asserted here, following the navigation branch
		// would send the test runner itself somewhere else
		['javascript:alert(document.domain)', '//evil.tld', '/index.php/apps/files'].forEach(function(value) {
			it('does not navigate to ' + value, function() {
				documentsMain.returnToServer = value;

				documentsMain.onClose();

				// the overlay is only shown while navigating away
				expect(documentsMain.overlay.documentOverlay).not.toHaveBeenCalledWith('show');
				// back to the document list instead
				expect(documentsMain.show).toHaveBeenCalled();
			});
		});
	});

	describe('incoming post messages', function() {
		var urlsrc, title, canonicalWebroot;

		beforeEach(function() {
			urlsrc = documentsMain.urlsrc;
			title = $('title').text();
			canonicalWebroot = window.rd_canonical_webroot;

			// karma serves the frame itself, so this server is the WOPI client
			// origin for the duration of these specs and nothing leaves the host
			documentsMain.urlsrc = window.location.origin + '/collabora/browser/1a2b3c/cool.html?';
			// normally rendered into the page by templates/documents.php
			window.rd_canonical_webroot = '';

			documentsMain.loadError = false;
			documentsMain.renderComplete = true;
			documentsMain.fileName = 'document.odt';
			documentsMain.wopiClientFeatures = null;
		});

		afterEach(function() {
			$('#mainContainer').remove();
			$('#ocToolbar').remove();
			$(document.body).removeClass('claro');
			$('title').text(title);

			documentsMain.urlsrc = urlsrc;
			window.rd_canonical_webroot = canonicalWebroot;
			documentsMain.renderComplete = false;
			documentsMain.wopiClientFeatures = null;
		});

		function loadingStatusFrom(origin) {
			window.dispatchEvent(new MessageEvent('message', {
				origin: origin,
				data: JSON.stringify({
					MessageId: 'App_LoadingStatus',
					Values: {Features: {ViewFileOnly: true}}
				})
			}));
		}

		it('ignores a message that does not come from Collabora Online', function(done) {
			documentsMain.UI.showEditor('view');

			// the listener is attached from a jQuery ready callback
			setTimeout(function() {
				loadingStatusFrom('https://evil.tld');

				expect(documentsMain.wopiClientFeatures).toBeNull();
				done();
			}, 0);
		});

		it('accepts a message from Collabora Online', function(done) {
			documentsMain.UI.showEditor('view');

			setTimeout(function() {
				loadingStatusFrom(documentsMain._wopiOrigin());

				expect(documentsMain.wopiClientFeatures).toEqual({ViewFileOnly: true});
				done();
			}, 0);
		});
	});
});
