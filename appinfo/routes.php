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
		// Collabora Document API
		['name' => 'Document#create', 'url' => 'ajax/documents/create', 'verb' => 'POST'],
		['name' => 'Document#listAll', 'url' => 'ajax/documents/list', 'verb' => 'GET'],
		['name' => 'Document#get', 'url' => 'ajax/documents/index/{fileId}', 'verb' => 'GET'],
		// Collabora Document Revision API
		['name' => 'DocumentRevision#list', 'url' => 'ajax/documents/revisions/{fileId}', 'verb' => 'GET'],
		// Collabora Settings API
		['name' => 'Settings#list', 'url' => 'ajax/settings/list', 'verb' => 'GET'],
		['name' => 'Settings#update', 'url' => 'ajax/settings/update', 'verb' => 'POST'],
		['name' => 'Settings#setPersonalSettings', 'url' => 'ajax/settings/setPersonalSettings', 'verb' => 'POST'],
		// Collabora for OC10 legacy frontend
		['name' => 'Document#index', 'url' => 'documents.php/index', 'verb' => 'GET'],
		["name" => 'Document#public', 'url' => 'documents.php/public', "verb" => "GET"],
		["name" => 'Document#federated', 'url' => 'documents.php/federated', "verb" => "GET"],
		// Collabora for Owncloud Web frontend
		['name' => 'WebAsset#get', 'url' => 'js/richdocuments.js', 'verb' => 'GET'],
		// WOPI protocol implementation
		['name' => 'Wopi#wopiCheckFileInfo', 'url' => 'wopi/files/{documentId}', 'verb' => 'GET'],
		['name' => 'Wopi#wopiFileOperation', 'url' => 'wopi/files/{documentId}', 'verb' => 'POST'],
		['name' => 'Wopi#wopiGetFile', 'url' => 'wopi/files/{documentId}/contents', 'verb' => 'GET'],
		['name' => 'Wopi#wopiPutFile', 'url' => 'wopi/files/{documentId}/contents', 'verb' => 'POST'],
	],
	'ocs' => [
		['name' => 'OCSFederation#index', 'url' => '/api/v1/federation', 'verb' => 'GET'],
		['name' => 'OCSFederation#getWopiForToken', 'url' => '/api/v1/federation', 'verb' => 'POST'],
	]
]);
