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
namespace OCA\Richdocuments\SharedStorageWrapper;

use OC\Files\Storage\Wrapper\Wrapper;

class SecureViewStorage extends Wrapper {
	/** @var \OC\Files\Storage\Storage */
	protected $storage;

	/** @var string */
	protected $mountPoint;

	/**
	 * @param array $arguments
	 */
	public function __construct($arguments) {
		parent::__construct($arguments);

		$this->storage = $arguments['storage'];
		$this->mountPoint = $arguments['mountPoint'];
	}

	/**
	 * see http://php.net/manual/en/function.file_get_contents.php
	 *
	 * @param string $path
	 * @return string
	 */
	public function file_get_contents($path) {
		\OC::$server->getLogger()->warning('file_get_contents mount=' . $this->mountPoint . ' path=' . $path);
		return $this->storage->file_get_contents($path);
	}

	/**
	 * see http://php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource
	 */
	public function fopen($path, $mode) {
		\OC::$server->getLogger()->warning('fopen mount=' . $this->mountPoint . ' path=' . $path);
		return $this->storage->fopen($path, $mode);
	}

//	/**
//	 * see http://php.net/manual/en/function.rename.php
//	 *
//	 * @param string $path1
//	 * @param string $path2
//	 * @return bool
//	 */
//	public function rename($path1, $path2) {
//		return $this->storage->rename($path1, $path2);
//	}
//
//	/**
//	 * see http://php.net/manual/en/function.copy.php
//	 *
//	 * @param string $path1
//	 * @param string $path2
//	 * @return bool
//	 */
//	public function copy($path1, $path2) {
//		return $this->storage->copy($path1, $path2);
//	}
//
//	/**
//	 * A custom storage implementation can return an url for direct download of a give file.
//	 *
//	 * For now the returned array can hold the parameter url - in future more attributes might follow.
//	 *
//	 * @param string $path
//	 * @return array
//	 */
//	public function getDirectDownload($path) {
//		return $this->storage->getDirectDownload($path);
//	}
//
//	/**
//	 * @param Storage $sourceStorage
//	 * @param string $sourceInternalPath
//	 * @param string $targetInternalPath
//	 * @return bool
//	 */
//	public function copyFromStorage(Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
//		if ($sourceStorage === $this) {
//			return $this->copy($sourceInternalPath, $targetInternalPath);
//		}
//
//		return $this->storage->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
//	}
}
