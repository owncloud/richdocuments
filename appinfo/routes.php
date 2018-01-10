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
$application->registerRoutes($this, [
	'routes' => [
		//session
		['name' => 'session#join', 'url' => 'session/user/join/{fileId}', 'verb' => 'POST'],
		//documents
		['name' => 'document#index', 'url' => 'index', 'verb' => 'GET'],
		['name' => 'document#create', 'url' => 'ajax/documents/create', 'verb' => 'POST'],
		['name' => 'document#get', 'url' => 'ajax/documents/get/{fileId}', 'verb' => 'GET'],
		['name' => 'document#listAll', 'url' => 'ajax/documents/list', 'verb' => 'GET'],
		//documents - for WOPI access
		['name' => 'document#extAppWopiGetData', 'url' => 'wopi/extapp/data/{fileId}', 'verb' => 'POST'],
		['name' => 'document#wopiCheckFileInfo', 'url' => 'wopi/files/{fileId}', 'verb' => 'GET'],
		['name' => 'document#wopiGetFile', 'url' => 'wopi/files/{fileId}/contents', 'verb' => 'GET'],
		['name' => 'document#wopiPutFile', 'url' => 'wopi/files/{fileId}/contents', 'verb' => 'POST'],
		['name' => 'document#wopiPutRelativeFile', 'url' => 'wopi/files/{fileId}', 'verb' => 'POST'],
		//settings
		['name' => 'settings#setSettings', 'url' => 'ajax/admin.php', 'verb' => 'POST'],
		['name' => 'settings#getSupportedMimes', 'url' => 'ajax/mimes.php', 'verb' => 'GET'],
		['name' => 'settings#getSettings', 'url' => 'ajax/settings.php', 'verb' => 'GET'],
	]
]);
