<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Victor Dubiniuk
 * @copyright 2013-2014 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments;

$application = new \OCA\Richdocuments\AppInfo\Application();
/* @phan-suppress-next-line PhanUndeclaredThis */
$application->registerRoutes($this, [
	'routes' => [
		// Collabora Documents API
		['name' => 'document#create', 'url' => 'ajax/documents/create', 'verb' => 'POST'],
		['name' => 'document#listAll', 'url' => 'ajax/documents/list', 'verb' => 'GET'],
		['name' => 'document#get', 'url' => 'ajax/documents/index/{fileId}', 'verb' => 'GET'],
		// Collabora Settings API
		['name' => 'settings#list', 'url' => 'ajax/settings/list', 'verb' => 'GET'],
		['name' => 'settings#update', 'url' => 'ajax/settings/update', 'verb' => 'POST'],
		// Collabora for OC10 legacy frontend
		['name' => 'document#index', 'url' => 'documents.php/index', 'verb' => 'GET'],
		["name" => 'document#public', 'url' => 'documents.php/public', "verb" => "GET"],
		["name" => 'document#remote', 'url' => 'documents.php/remote', "verb" => "GET"],
		// Collabora for Owncloud Web frontend
		['name' => 'web_asset#get', 'url' => 'js/richdocuments.js', 'verb' => 'GET'],
		// WOPI protocol implementation
		['name' => 'wopi#wopiCheckFileInfo', 'url' => 'wopi/files/{documentId}', 'verb' => 'GET'],
		['name' => 'wopi#wopiFileOperation', 'url' => 'wopi/files/{documentId}', 'verb' => 'POST'],
		['name' => 'wopi#wopiGetFile', 'url' => 'wopi/files/{documentId}/contents', 'verb' => 'GET'],
		['name' => 'wopi#wopiPutFile', 'url' => 'wopi/files/{documentId}/contents', 'verb' => 'POST'],
	],
	'ocs' => [
		['name' => 'Federation#getWopiUrl', 'url' => '/api/v1/federation', 'verb' => 'GET'],
		['name' => 'Federation#getRemoteWopiInfo', 'url' => '/api/v1/federation', 'verb' => 'POST'],
	]
]);
