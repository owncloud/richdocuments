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

use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\ISession;
use OCA\Richdocuments\AppConfig;

class DocumentService {
	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var AppConfig
	 */
	private $appConfig;

	/**
	 * @var IManager
	 */
	private $shareManager;

	/**
	 * @var ISession
	 */
	private $session;

	public function __construct(
		IRootFolder $rootFolder,
		AppConfig $appConfig,
		IManager $shareManager,
		ISession $session
	) {
		$this->rootFolder = $rootFolder;
		$this->appConfig = $appConfig;
		$this->shareManager = $shareManager;
		$this->session = $session;
	}

	/**
	 * Retrieve all document info for current user.
	 *
	 * WARNING: This method is legacy, use with caution.
	 *
	 * @return array
	 */
	public function getDocuments() {
		// FIXME: we should not assume user being logged in here
		$db = new Db\Storage();
		$view = \OC\Files\Filesystem::getView();

		$rawDocuments = $db->loadRecentDocumentsForMimes(Helper::$MIMETYPE_LIBREOFFICE_WORDPROCESSOR);

		$documents = [];
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

		$list = \array_filter(
			$documents,
			function ($item) {
				//filter Deleted
				if (\strpos($item['path'], '_trashbin') === 0) {
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
		$root = $this->rootFolder->getUserFolder($userId);

		try {
			// if dir is set, then we need to check fileId in that folder,
			// as in case of user/group shares we can have multiple file mounts with same id
			// return these fileMounts
			if ($dir !== null) {
				/** @var \OCP\Files\Folder $parentFolder */
				$parentFolder = $root->get($dir);

				/** @phpstan-ignore-next-line */
				'@phan-var \OCP\Files\Folder $parentFolder';
				$fileMounts = $parentFolder->getById($fileId);
			} else {
				$fileMounts = $root->getById($fileId);
			}
			
			$document = $fileMounts[0] ?? null;
			if ($document === null) {
				return $this->reportError('Document for the fileId ' . $fileId . 'not found');
			}

			/** @var \OCP\Files\Storage\IStorage $storage */
			$storage = $document->getStorage();
			$isSharedFile = $storage->instanceOfStorage('\OCA\Files_Sharing\SharedStorage');
			$isFederatedShare = $storage->instanceOfStorage('\OCA\Files_Sharing\External\Storage');
			$isSecureModeEnabled = $this->appConfig->secureViewOptionEnabled();

			// Base file info
			$ret = [];
			$ret['owner'] = $document->getOwner()->getUID();
			$ret['allowEdit'] = $document->isUpdateable();
			$ret['allowExport'] = true;
			$ret['allowPrint'] = true;
			$ret['secureView'] = false;
			$ret['secureViewId'] = null;
			$ret['federatedServer'] = null;
			$ret['federatedToken'] = null;
			$ret['mimetype'] = $document->getMimeType();
			$ret['path'] = $root->getRelativePath($document->getPath());
			$ret['name'] = $document->getName();
			$ret['fileid'] = $fileId;
			$ret['version'] = '0'; // latest

			if ($isSharedFile && $isSecureModeEnabled) {
				/** @var \OCA\Files_Sharing\SharedStorage $storage */
				/* @phan-suppress-next-line PhanUndeclaredMethod */
				$share = $storage->getShare();
				$sharePermissionsDownload = $share->getAttributes()->getAttribute('permissions', 'download');
				$shareViewWithWatermark = $share->getAttributes()->getAttribute('richdocuments', 'view-with-watermark');
				$shareCanPrint = $share->getAttributes()->getAttribute('richdocuments', 'print');

				// restriction on view has been set to false, return forbidden
				// as there is no supported way of opening this document
				if ($sharePermissionsDownload === false && $shareViewWithWatermark === false) {
					return $this->reportError('Insufficient file permissions for the fileId ' . $fileId);
				}

				// can export file in editor if download is not set or true
				$ret['allowExport'] = ($sharePermissionsDownload === null || $sharePermissionsDownload === true);

				// can print from editor if print is not set or true
				$ret['allowPrint'] = ($shareCanPrint === null || $shareCanPrint === true);

				// view with watermarking enabled with private mode enabled
				if ($shareViewWithWatermark === true) {
					$ret['allowEdit'] = false;
					$ret['secureView'] = true;
					$ret['secureViewId'] = $share->getId();
				}
			} elseif ($isFederatedShare) {
				/** @var \OCA\Files_Sharing\External\Storage $storage */

				// get the federated share server
				/* @phan-suppress-next-line PhanUndeclaredMethod */
				$ret['federatedServer'] = $storage->getRemote();

				// get the federated share token
				/* @phan-suppress-next-line PhanUndeclaredMethod */
				$ret['federatedShareToken'] = $storage->getToken();

				// get the path of the file in the federates share:
				//  - in case of shared folder it would be relative path to file in that shared folder
				//  - in case of shared file it would be name of the shared file
				/* @phan-suppress-next-line PhanUndeclaredMethod */
				$ret['federatedPath'] = $document->getInternalPath();
			}

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
		} elseif (! $this->session->exists('public_link_authenticated')
			|| $this->session->get('public_link_authenticated') !== (string)$share->getId()) {
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
			$share = $this->shareManager->getShareByToken($token);
			if (!$this->isShareAuthValid($share)) {
				return null;
			}

			$node = $share->getNode();
			if ($node->getType() == FileInfo::TYPE_FILE) {
				/** @var \OCP\Files\File|null $node */
				$document = $node;
			} elseif ($fileId !== null) {
				// node was not a file, so it must be a folder.
				// fileId was passed in, so look that up in the folder.
				/** @var \OCP\Files\Folder|null $node */
				$document = $node->getById($fileId)[0];
			} else {
				return $this->reportError('Cannot retrieve metadata for the node ' . $node->getPath());
			}

			if ($document === null) {
				return $this->reportError('Document for the node ' . $node->getPath() . 'not found');
			}

			// Retrieve user folder for the file to be able to get relative path
			$owner = $share->getShareOwner();
			$root = $this->rootFolder->getUserFolder($owner);

			$ret = [];
			$ret['owner'] = $owner;
			$ret['allowEdit'] = ($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE;
			$ret['allowExport'] = true;
			$ret['allowPrint'] = true;
			$ret['mimetype'] = $document->getMimeType();
			$ret['path'] = $root->getRelativePath($document->getPath());
			$ret['name'] = $document->getName();
			$ret['fileid'] = $document->getId();
			$ret['version'] = '0'; // latest

			return $ret;
		} catch (ShareNotFound $e) {
			return $this->reportError('Share for the token ' . $token . ' and document fileid ' . $fileId . 'not found');
		} catch (NotFoundException $e) {
			return $this->reportError($e->getMessage());
		} catch (InvalidPathException $e) {
			return $this->reportError($e->getMessage());
		}
	}

	/**
	 * Retrieve document info for the federated share token. If file in the public link folder is used,
	 * path has to be provided.
	 *
	 * If share or file does not exist, null is returned
	 *
	 * @param string $token
	 * @param string|null $path
	 * @return array|null
	 */
	public function getDocumentByFederatedToken(string $token, ?string $path) : ?array {
		try {
			// Get share by token
			$share = $this->shareManager->getShareByToken($token);
			if (!$this->isShareAuthValid($share)) {
				return null;
			}

			$node = $share->getNode();
			if ($node->getType() == FileInfo::TYPE_FILE) {
				/** @var \OCP\Files\File|null $node */
				$document = $node;
			} elseif ($path !== null) {
				// node was not a file, so it must be a folder.
				// fileId was passed in, so look that up in the folder.
				/** @var \OCP\Files\Folder|null $node */
				$document = $node->get($path);
			} else {
				return $this->reportError('Cannot retrieve metadata for the node ' . $node->getPath());
			}

			if ($document === null) {
				return $this->reportError('Document for the node ' . $node->getPath() . 'not found');
			}

			// Retrieve user folder for the file to be able to get relative path
			$owner = $share->getShareOwner();
			$root = $this->rootFolder->getUserFolder($owner);

			$ret = [];
			$ret['owner'] = $owner;
			$ret['allowEdit'] = ($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE;
			$ret['allowExport'] = true;
			$ret['allowPrint'] = true;
			$ret['mimetype'] = $document->getMimeType();
			$ret['path'] = $root->getRelativePath($document->getPath());
			$ret['name'] = $document->getName();
			$ret['fileid'] = $document->getId();
			$ret['version'] = '0'; // latest

			return $ret;
		} catch (ShareNotFound $e) {
			return $this->reportError('Share for the token ' . $token . ' and document path ' . $path . 'not found');
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
}
