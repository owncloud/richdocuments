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

/**
 * Configuration for the karma test runner, run by `make test-js`.
 *
 * The classic frontend in js/ has no module system, it expects the globals the
 * server puts on the page - jQuery with the jQuery UI widget factory, OC, t().
 * They are loaded here from the surrounding core checkout the same way the
 * server loads them, so the scripts under test run unmodified. This is why the
 * app has to sit inside a core tree for the tests to run, just like the PHPUnit
 * suite.
 *
 * Named .cjs on purpose: package.json declares "type": "module", so a .js
 * config would be parsed as an ES module and karma cannot require() it.
 */
module.exports = function(config) {
	// no wildcard, the scripts in js/ are never loaded on the same page and
	// share one global namespace
	var srcFiles = [
		'js/documents.js'
	];

	var testFiles = [
		'tests/js/*Spec.js'
	];

	// this file lives in <core>/apps/<app>/tests/js/
	var basePath = '../../';
	var ownCloudPath = '../../';

	// require() resolves relative to this file, karma patterns relative to basePath
	var coreModules = require(ownCloudPath + '../../core/js/core.json');

	// specHelper brings the globals config.php would normally define, and a
	// fake XHR server so that no spec can reach out to the network
	var coreLibs = [
		ownCloudPath + 'core/js/tests/specHelper.js'
	];

	coreLibs = coreLibs.concat(coreModules.vendor.map(function prependPath(path) {
		return ownCloudPath + 'core/vendor/' + path;
	}));

	coreLibs = coreLibs.concat(coreModules.modules.map(function prependPath(path) {
		return ownCloudPath + 'core/js/' + path;
	}));

	var files = [].concat(coreLibs, srcFiles, testFiles);

	config.set({

		// base path, that will be used to resolve files and exclude
		basePath: basePath,

		// frameworks to use - specHelper expects sinon
		frameworks: ['jasmine', 'jasmine-sinon'],

		// listed explicitly, karma's default 'karma-*' discovery only scans the
		// directory it sits in itself and finds nothing in a pnpm layout
		plugins: [
			require('karma-jasmine'),
			require('karma-jasmine-sinon'),
			require('karma-firefox-launcher'),
			require('karma-chrome-launcher')
		],

		// list of files / patterns to load in the browser
		files: files,

		// list of files to exclude
		exclude: [

		],

		proxies: {
			// prevent warnings for images
			'/context.html//core/img/': 'http://localhost:9876/base/core/img/',
			'/context.html//core/css/': 'http://localhost:9876/base/core/css/',
			'/context.html//core/fonts/': 'http://localhost:9876/base/core/fonts/'
		},

		// test results reporter to use
		reporters: ['progress'],

		// web server port
		port: 9876,

		// enable / disable colors in the output (reporters and logs)
		colors: true,

		// level of logging
		logLevel: config.LOG_INFO,

		// enable / disable watching file and executing tests whenever any file changes
		autoWatch: true,

		// CI runs Firefox, KARMA_BROWSER=ChromeHeadless for machines without it
		browsers: [process.env.KARMA_BROWSER || 'FirefoxHeadless'],

		// If browser does not capture in given timeout [ms], kill it
		captureTimeout: 60000,
		browserNoActivityTimeout: 60000,
		browserDisconnectTimeout: 30000,

		// Continuous Integration mode - make test-js passes --single-run
		singleRun: false
	});
};
