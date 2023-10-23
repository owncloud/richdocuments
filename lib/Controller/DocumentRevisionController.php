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
namespace OCA\Richdocuments\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IVersionedStorage;
use OCP\IRequest;
use OCP\IUserSession;

class DocumentRevisionController extends Controller {
	/**
	 * @var IUserSession The user session service
	 */
	private $userSession;

	/**
	 * @var IRootFolder The root folder service
	 */
	private $rootFolder;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		IRootFolder $rootFolder
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
	}

	/**
	 * Get collabora document non-current revisions for:
	 * - the base template if fileId is null
	 * - file in user folder (also shared by user/group) if fileId not null
	 *
	 * @NoAdminRequired
	 */
	public function list($fileId) {
		if (\is_numeric($fileId)) {
			// parse fileId pointing to file
			$fileId = (int) $fileId;
		} else {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Invalid request parameters'
			], Http::STATUS_BAD_REQUEST);
		}

		$dir = $this->request->getParam('dir');

		// get current user
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Revision supported only for logged in users'
			], Http::STATUS_BAD_REQUEST);
		}

		try {
			// get current user root
			$currentUserFolder = $this->rootFolder->getUserFolder($user->getUID());
			if ($dir !== null) {
				// if dir is set, then we need to check fileId in that folder,
				// as in case of user/group shares we can have multiple file mounts with same id
				// return these fileMounts

				/** @var \OCP\Files\Folder $sourceFileParentFolder */
				$sourceFileParentFolder = $currentUserFolder->get($dir);

				/** @phpstan-ignore-next-line */
				'@phan-var \OCP\Files\Folder $sourceFileParentFolder';
				$sourceFileMounts = $sourceFileParentFolder->getById($fileId, true);
			} else {
				// if dir is not set, then we need to check fileId in user root
				$sourceFileMounts = $currentUserFolder->getById($fileId, true);
			}

			// get source document for the user
			$sourceDocument = $sourceFileMounts[0] ?? null;
			if ($sourceDocument === null) {
				return new JSONResponse([
					'status' => 'error',
					'message' => 'Document for the fileId ' . $fileId . 'not found'
				], Http::STATUS_NOT_FOUND);
			}

			// get owner of the file to be able to access versions
			$owner = $sourceDocument->getOwner();
			$ownerUserFolder = $this->rootFolder->getUserFolder($owner->getUID());

			// get original document
			$ownerFileMounts = $ownerUserFolder->getById($fileId, true);
			$document = $ownerFileMounts[0] ?? null;
			if ($document === null) {
				return new JSONResponse([
					'status' => 'error',
					'message' => 'Document for the fileId ' . $fileId . 'not found'
				], Http::STATUS_NOT_FOUND);
			}

			// get versions storage information
			$storage = $document->getStorage();
			if (!$storage->instanceOfStorage(IVersionedStorage::class)) {
				return new JSONResponse([
					'status' => 'error',
					'message' => 'Access to file ' . $fileId . ' revisions is not supported'
				], Http::STATUS_NOT_FOUND);
			}

			// retrieve versions
			$internalPath = $document->getInternalPath();
			/** @var IVersionedStorage $storage */
			/* @phan-suppress-next-line PhanUndeclaredMethod */
			$versions = $storage->getVersions($internalPath);
		} catch (InvalidPathException $e) {
			return new JSONResponse([
				'status' => 'error',
				'message' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Document revisions could not be retrieved'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		
		$ret = [];
		foreach ($versions as $version) {
			$ret[] = [
				// version number
				'version' => $version['version'],
				// version creation timestamp
				'humanReadableTimestamp' => $version['humanReadableTimestamp'],
				// version size
				'size' => $version['size'],
			];
		}
		// Return document revisions response
		return new JSONResponse([
			'revisions' => $ret
		]);
	}
}
