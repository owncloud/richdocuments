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

class Storage extends \OCA\Richdocuments\Db {
	public const appName = 'richdocuments';

	public const documentShowLimit = 30;

	protected $tableName  = '`*PREFIX*filecache`';

	protected $loadStatement = 'SELECT * FROM `*PREFIX*filecache` WHERE ``= ?';

	/*
	 * Loads the recent accessed documents that match any of the mimetypes given in array $mimes
	 * for currently logged in user
	 *
	 * WARNING: This method is legacy, use with caution.
	 */
	public function loadRecentDocumentsForMimes($mimes) {
		// FIXME: we should not assume user being logged in here
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
		$result = $this->executeQuery($query, $values);
		$files = [];
		while ($row = $result->fetch()) {
			$row['mimetype'] = $mimetypeLoader->getMimetypeById($row['mimetype']);
			/*
			 * mimepart is a string here, and arg 1 (id) should be an int.
			 * As long as the string is just a number, all is good.
			 */
			/* @phan-suppress-next-line PhanTypeMismatchArgument */
			$row['mimepart'] = $mimetypeLoader->getMimetypeById($row['mimepart']);
			$files[] = $row;
		}

		return $files;
	}
}
