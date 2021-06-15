<?php

/**
 * ownCloud - Richdocuments App
 *
 * @author Pranav Kant
 * @copyright 2018 Pranav Kant pranavk@collabora.co.uk
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments\Db;

/**
 * @method string loadRecentDocumentsForMimes()
 */

class Storage extends \OCA\Richdocuments\Db {
	public const appName = 'richdocuments';

	public const documentShowLimit = 30;

	protected $tableName  = '`*PREFIX*filecache`';

	protected $loadStatement = 'SELECT * FROM `*PREFIX*filecache` WHERE ``= ?';

	/*
	 * Loads the recent accessed documents that match any of the mimetypes given in array $mimes
	 */
	public function loadRecentDocumentsForMimes($mimes) {
		$view = \OC\Files\Filesystem::getView();
		$mount = $view->getMount('');
		$mountPoint = $mount->getMountPoint();
		$storage = $mount->getStorage();
		$cache = $storage->getCache('');
		$storageId = $cache->getNumericStorageId();

		$mimetypeLoader = \OC::$server->getMimeTypeLoader();
		$mimeIds = [];
		foreach ($mimes as $mime) {
			$mimeIds[] = $mimetypeLoader->getId($mime);
		}

		$inStmt = $this->buildInQuery('mimetype', $mimeIds);
		$query = 'SELECT * FROM `*PREFIX*filecache` WHERE `storage` =? AND ' . $inStmt . ' ORDER BY `mtime` DESC LIMIT ' . self::documentShowLimit;
		$values = \array_merge([$storageId], $mimeIds);
		$result = $this->execute($query, $values);
		$files = [];
		while ($row = $result->fetch()) {
			$row['mimetype'] = $mimetypeLoader->getMimetypeById($row['mimetype']);
			$row['mimepart'] = $mimetypeLoader->getMimetypeById($row['mimepart']);
			$files[] = $row;
		}

		return $files;
	}
}
