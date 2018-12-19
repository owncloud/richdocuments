<?php

/**
 * ownCloud - Richdocuments App
 *
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 * @copyright 2018 Piotr Mrowczynski <piotr@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */
namespace OCA\Richdocuments;

use OC\Files\Filesystem;
use OCA\Richdocuments\SharedStorageWrapper\SecureViewStorage;
use OCP\Util;
use OCP\Files\Storage;

/**
 * Class HookHandler
 *
 * handles hooks
 *
 * @package OCA\Richdocuments
 */
class HookHandler {

	public static function addViewerScripts() {
		Util::addScript('richdocuments', 'viewer/viewer');
		Util::addStyle('richdocuments', 'viewer/odfviewer');
	}

	public static function wrapSecureViewSharedStorage() {
		Filesystem::addStorageWrapper('richdocuments', function ($mountPoint, \OCP\Files\Storage $storage, \OCP\Files\Mount\IMountPoint $mount) {
			if ($storage->instanceOfStorage('OC\Files\Storage\Shared')) {
				return new SecureViewStorage([
					'storage' => $storage,
					'mountPoint' => $mountPoint,
				]);
			}
			return $storage;
		}, -1);
	}
}
