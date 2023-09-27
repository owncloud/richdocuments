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

use OCA\Richdocuments\AppConfig;
use OCA\Richdocuments\DiscoveryService;
use OCA\Richdocuments\DocumentService;
use OCA\Richdocuments\FederationService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\InvalidPathException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IVersionedStorage;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\INavigationManager;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUserManager;

class DocumentRevisionController extends Controller {
	/**
	 * @var IL10N The localization service
	 */
	private $l10n;

	/**
	 * @var IConfig The ownCloud configuration service
	 */
	private $settings;

	/**
	 * @var AppConfig The Richdocuments app configuration service
	 */
	private $appConfig;

	/**
	 * @var ILogger The logger service
	 */
	private $logger;

	/**
	 * @var IAppManager The app manager service
	 */
	private $appManager;

	/**
	 * @var DocumentService The document service
	 */
	private $documentService;

	/**
	 * @var DiscoveryService The document service
	 */
	private $discoveryService;

	/**
	 * @var FederationService The federation service
	 */
	private $federationService;

	/**
	 * @var IGroupManager The group manager service
	 */
	private $groupManager;

	/**
	 * @var IUserManager The user manager service
	 */
	private $userManager;

	/**
	 * @var IPreview The user manager service
	 */
	private $previewManager;

	/**
	 * @var INavigationManager The user manager service
	 */
	private $navigationManager;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $settings,
		AppConfig $appConfig,
		IL10N $l10n,
		ILogger $logger,
		DocumentService $documentService,
		DiscoveryService $discoveryService,
		IAppManager $appManager,
		IGroupManager $groupManager,
		IUserManager $userManager,
		IPreview $previewManager,
		INavigationManager $navigationManager,
		FederationService $federationService
	) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->settings = $settings;
		$this->appConfig = $appConfig;
		$this->logger = $logger;
		$this->documentService = $documentService;
		$this->discoveryService = $discoveryService;
		$this->appManager = $appManager;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->previewManager = $previewManager;
		$this->navigationManager = $navigationManager;
		$this->federationService = $federationService;
	}

	/**
	 * Get collabora document revisions for:
	 * - the base template if fileId is null
	 * - file in user folder (also shared by user/group) if fileId not null
	 *
	 * @NoAdminRequired
	 */
	public function list($fileId)
	{
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
		$user = \OC::$server->getUserSession()->getUser();
		if ($user === null) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Revision supported only for logged in users'
			], Http::STATUS_BAD_REQUEST);
		}

		try {
			// get current user root
			$currentUserFolder = \OC::$server->getRootFolder()->getUserFolder($user->getUID());
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
			$ownerUserFolder = \OC::$server->getRootFolder()->getUserFolder($owner->getUID());

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
			/** @var IStorage $storage */
			$storage = $document->getStorage();
			if (!$storage->instanceOfStorage(IVersionedStorage::class)) {
				return new JSONResponse([
					'status' => 'error',
					'message' => 'Access to file ' . $fileId . ' revisions is not supported'
				], Http::STATUS_NOT_FOUND);
			}

			// retrieve versions
			/** @var IVersionedStorage | IStorage $storage */
			'@phan-var IVersionedStorage | IStorage $storage';
			$internalPath = $document->getInternalPath();
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