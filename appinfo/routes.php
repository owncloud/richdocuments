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
		// Collabora API
		['name' => 'document#create', 'url' => 'ajax/documents/create', 'verb' => 'POST'],
		['name' => 'document#listAll', 'url' => 'ajax/documents/list', 'verb' => 'GET'],
		['name' => 'document#get', 'url' => 'ajax/documents/index/{fileId}', 'verb' => 'GET'],
		// Collabora for OC10 legacy frontend
		['name' => 'document#index', 'url' => 'documents.php/index', 'verb' => 'GET'],
		["name" => 'document#public', 'url' => 'documents.php/public', "verb" => "GET"],
		// Collabora for Owncloud Web frontend
		['name' => 'web_asset#get', 'url' => 'js/richdocuments.js', 'verb' => 'GET'],
		// Collabora Settings
		['name' => 'settings#setSettings', 'url' => 'ajax/admin.php', 'verb' => 'POST'],
		['name' => 'settings#getSettings', 'url' => 'ajax/settings.php', 'verb' => 'GET'],
		// WOPI protocol implementation
		['name' => 'wopi#wopiCheckFileInfo', 'url' => 'wopi/files/{documentId}', 'verb' => 'GET'],
		['name' => 'wopi#wopiFileOperation', 'url' => 'wopi/files/{documentId}', 'verb' => 'POST'],
		['name' => 'wopi#wopiGetFile', 'url' => 'wopi/files/{documentId}/contents', 'verb' => 'GET'],
		['name' => 'wopi#wopiPutFile', 'url' => 'wopi/files/{documentId}/contents', 'verb' => 'POST'],
	]
]);
