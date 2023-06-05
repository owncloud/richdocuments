<?php

/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2023, ownCloud GmbH
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
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Richdocuments;

use OCP\IConfig;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;

class DocumentService {
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(
		IRootFolder $rootFolder,
		IConfig $config
	) {
		$this->rootFolder = $rootFolder;
		$this->config = $config;
	}

	public static $MIMETYPE_LIBREOFFICE_WORDPROCESSOR = [
		'application/pdf',
		'application/vnd.oasis.opendocument.text',
		'application/vnd.oasis.opendocument.presentation',
		'application/vnd.oasis.opendocument.spreadsheet',
		'application/vnd.oasis.opendocument.graphics',
		'application/vnd.oasis.opendocument.text-flat-xml',
		'application/vnd.oasis.opendocument.presentation-flat-xml',
		'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
		'application/vnd.oasis.opendocument.graphics-flat-xml',
		'application/vnd.lotus-wordpro',
		'image/svg+xml',
		'application/vnd.visio',
		'application/vnd.wordperfect',
		'application/msonenote',
		'application/msword',
		'application/rtf',
		'text/rtf',
		'text/plain',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		'application/vnd.ms-word.document.macroEnabled.12',
		'application/vnd.ms-word.template.macroEnabled.12',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
		'application/vnd.ms-excel.sheet.macroEnabled.12',
		'application/vnd.ms-excel.template.macroEnabled.12',
		'application/vnd.ms-excel.addin.macroEnabled.12',
		'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/vnd.openxmlformats-officedocument.presentationml.template',
		'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
		'application/vnd.ms-powerpoint.addin.macroEnabled.12',
		'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		'application/vnd.ms-powerpoint.template.macroEnabled.12',
		'application/vnd.ms-powerpoint.slideshow.macroEnabled.12'
	];

	public function getDocuments() {
		$db = new Db\Storage();
		$rawDocuments = $db->loadRecentDocumentsForMimes(self::$MIMETYPE_LIBREOFFICE_WORDPROCESSOR);
		$documents = $this->processDocuments($rawDocuments);

		$list = \array_filter(
			$documents,
			function ($item) {
				//filter Deleted
				if (\strpos($item['path'], '_trashbin')===0) {
					return false;
				}
				return true;
			}
		);

		return $list;
	}

	/**
	 * Retrieve document info for file in the user directory (also shared file or within shared folder).
	 *
	 * If share is invalid or file does not exist, null is returned
	 *
	 * @param string $userId
	 * @param int $fileId
	 * @param string|null $dir
	 * @return array|null
	 */
	public function getDocumentByUserId(string $userId, int $fileId, ?string $dir) : ?array {
		$ret = [];
		$root = $this->rootFolder->getUserFolder($userId);

		try {
			// if dir is set, then we need to check fileId in that folder,
			// as in case of user/group shares we can have multiple file mounts with same id
			// return these fileMounts
			if ($dir !== null) {
				/** @var Folder $parentFolder */
				$parentFolder = $root->get($dir);

				/** @phpstan-ignore-next-line */
				'@phan-var Folder $parentFolder';
				$fileMounts = $parentFolder->getById($fileId);
			} else {
				$fileMounts = $root->getById($fileId);
			}
			
			$document = $fileMounts[0] ?? null;
			if ($document === null) {
				return $this->reportError('Document for the fileId ' . $fileId . 'not found');
			}

			// Set basic parameters
			$ret['owner'] = $document->getOwner()->getUID();
			$ret['permissions'] = $document->getPermissions();
			$ret['updateable'] = $document->isUpdateable();
			$ret['mimetype'] = $document->getMimeType();
			$ret['path'] = $root->getRelativePath($document->getPath());
			$ret['name'] = $document->getName();
			$ret['fileid'] = $fileId;
			$ret['instanceid'] = $this->config->getSystemValue('instanceid');
			$ret['version'] = '0'; // latest

			return $ret;
		} catch (InvalidPathException $e) {
			return $this->reportError($e->getMessage());
		} catch (NotFoundException $e) {
			return $this->reportError($e->getMessage());
		}
	}

	private function isShareAuthValid(IShare $share) {
		// check if password authentication has been passed
		// (calling directly from API, password form cannot be enforced, so check is needed)
		if ($share->getPassword() === null) {
			return true;
		} elseif (! \OC::$server->getSession()->exists('public_link_authenticated')
			|| \OC::$server->getSession()->get('public_link_authenticated') !== (string)$share->getId()) {
			return false;
		}
		return true;
	}

	/**
	 * Retrieve document info for the public share link token. If file in the public link folder is used,
	 * fileId has to be provided.
	 *
	 * If share or file does not exist, null is returned
	 *
	 * @param string $token
	 * @param int|null $fileId
	 * @return array|null
	 */
	public function getDocumentByShareToken(string $token, ?int $fileId) : ?array {
		try {
			// Get share by token
			$share = \OC::$server->getShareManager()->getShareByToken($token);
			if (!$this->isShareAuthValid($share)) {
				return null;
			}

			$node = $share->getNode();
			if ($node->getType() == FileInfo::TYPE_FILE) {
				/** @var \OCP\Files\Node|null $document */
				$document = $node;
			} elseif ($fileId !== null) {
				// node was not a file, so it must be a folder.
				// fileId was passed in, so look that up in the folder.
				/** @var \OCP\Files\Node|null $document */
				$document = $node->getById($fileId)[0];
			} else {
				return $this->reportError('Cannot retrieve metadata for the node ' . $node->getPath());
			}

			if ($document === null) {
				return $this->reportError('Document for the node ' . $node->getPath() . 'not found');
			}

			// Retrieve user folder for the file to be able to get relative path
			$owner = $share->getShareOwner();
			$root = \OC::$server->getRootFolder()->getUserFolder($owner);

			$ret = [];
			$ret['owner'] = $owner;
			$ret['permissions'] = $share->getPermissions();
			$ret['updateable'] = $document->isUpdateable();
			$ret['mimetype'] = $document->getMimeType();
			$ret['path'] = $root->getRelativePath($document->getPath());
			$ret['name'] = $document->getName();
			$ret['fileid'] = $document->getId();
			$ret['instanceid'] = \OC::$server->getConfig()->getSystemValue('instanceid');
			$ret['version'] = '0'; // latest
			$ret['sessionid'] = '0'; // default shared session

			return $ret;
		} catch (ShareNotFound $e) {
			return $this->reportError('Share for the token ' . $token . ' and document fileid ' . $fileId . 'not found');
		} catch (NotFoundException $e) {
			return $this->reportError($e->getMessage());
		} catch (InvalidPathException $e) {
			return $this->reportError($e->getMessage());
		}
	}

	private function reportError($error) {
		\error_log($error);
		return null;
	}

	private function processDocuments($rawDocuments) {
		$documents = [];
		$view = \OC\Files\Filesystem::getView();

		foreach ($rawDocuments as $rawDocument) {
			$fileId = $rawDocument['fileid'];
			$fileName = $rawDocument['name'];
			$mimeType = $rawDocument['mimetype'];
			$mtime = $rawDocument['mtime'];
			try {
				/*
				 * File id is a string here, and arg 1 should be an int.
				 * As long as the string is just a number, all is good.
				 */
				/* @phan-suppress-next-line PhanTypeMismatchArgument */
				$path = $view->getPath($fileId);
			} catch (\Exception $e) {
				\OC::$server->getLogger()->debug('Path not found for fileId: {fileId}. Skipping', [
					'app' => 'richdocuments',
					'fileId' => $fileId
				]);
				continue;
			}

			$document = [
				'fileid' => $fileId,
				'path' => $path,
				'name' => $fileName,
				'mimetype' => $mimeType,
				'mtime' => $mtime
				];

			\array_push($documents, $document);
		}

		return $documents;
	}
}
