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
		//documents
		['name' => 'document#index', 'url' => 'index', 'verb' => 'GET'],
		['name' => 'document#create', 'url' => 'ajax/documents/create', 'verb' => 'POST'],
		['name' => 'document#listAll', 'url' => 'ajax/documents/list', 'verb' => 'GET'],
		["name" => 'document#publicIndex', "url" => "public", "verb" => "GET"],
		//documents - for WOPI access
		['name' => 'document#extAppWopiGetData', 'url' => 'wopi/extapp/data/{sessionId}', 'verb' => 'POST'],
		['name' => 'document#wopiCheckFileInfo', 'url' => 'wopi/files/{sessionId}', 'verb' => 'GET'],
		['name' => 'document#wopiGetFile', 'url' => 'wopi/files/{sessionId}/contents', 'verb' => 'GET'],
		['name' => 'document#wopiPutFile', 'url' => 'wopi/files/{sessionId}/contents', 'verb' => 'POST'],
		['name' => 'document#wopiPutRelativeFile', 'url' => 'wopi/files/{sessionId}', 'verb' => 'POST'],
		//settings
		['name' => 'settings#setSettings', 'url' => 'ajax/admin.php', 'verb' => 'POST'],
		['name' => 'settings#getSettings', 'url' => 'ajax/settings.php', 'verb' => 'GET'],
	]
]);
