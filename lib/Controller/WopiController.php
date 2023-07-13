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

use OCP\Security\ISecureRandom;
use OCA\Richdocuments\Db\Wopi;
use OCP\AppFramework\Controller;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IPersistentLockingStorage;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IL10N;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ILogger;

use OCA\Richdocuments\AppConfig;
use OCA\Richdocuments\Db;
use OCA\Richdocuments\Helper;
use OCA\Richdocuments\FileService;
use OCA\Richdocuments\Http\DownloadResponse;
use OCP\IUserManager;
use OCP\IURLGenerator;

class WopiController extends Controller {
	/**
	 * @var IConfig
	 */
	private $settings;

	/**
	 * @var AppConfig
	 */
	private $appConfig;

	/**
	 * @var IL10N
	 */
	private $l10n;

	/**
	 * @var ILogger
	 */
	private $logger;
	
	/**
	 * @var FileService
	 */
	private $fileService;

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var ISecureRandom
	 */
	private $secureRandom;

	// Signifies LOOL that document has been changed externally in this storage
	public const LOOL_STATUS_DOC_CHANGED = 1010;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $settings,
		AppConfig $appConfig,
		IL10N $l10n,
		ILogger $logger,
		FileService $fileService,
		IURLGenerator $urlGenerator,
		IUserManager $userManager,
		ISecureRandom $secureRandom
	) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->settings = $settings;
		$this->appConfig = $appConfig;
		$this->logger = $logger;
		$this->fileService = $fileService;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * The Files endpoint operation CheckFileInfo.
	 *
	 * The operation returns information about a file, a user's permissions on that file,
	 * and general information about the capabilities that the WOPI host has on the file.
	 */
	public function wopiCheckFileInfo(string $documentId): JSONResponse {
		$wopiToken = $this->request->getParam('access_token');

		$this->logger->debug('CheckFileInfo: documentId {documentId}.', [
			'app' => $this->appName,
			'documentId' => $documentId
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('CheckFileInfo: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		// get origin
		$postMessageOrigin = $res['server_host'];

		// get owner info
		$ownerId = $res['owner'];

		// get user info
		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			// file editing as local logged in user
			$editor = $this->userManager->get($res['editor']);

			$userId = $editor->getUID();
			$userFriendlyName = $editor->getDisplayName();
			$userEmail = $editor->getEMailAddress();
			$isAnonymousUser = false;
		} elseif ($res['editor'] && $res['editor'] !== '' && ($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			// federated share needs to access file as incognito (remote user) as
			// currently it is not supported to set federated user as file editor
			// FIXME: knowing federated user we could get its friendly name from DAV contacts
			$userId = $res['editor'];
			$userFriendlyName = $res['editor'];
			$userEmail = null;
			$isAnonymousUser = true;
		} else {
			// public link needs to access file as incognito (remote user)
			$userId = 'public-link-user-' . $this->secureRandom->generate(8);
			$userFriendlyName = $this->l10n->t('Public Link User');
			$userEmail = null;
			$isAnonymousUser = true;
		}

		// get file handle
		$fileId = $res['fileid'];
		$version = $res['version'];
		if ($isAnonymousUser) {
			$file = $this->fileService->getFileHandle($fileId, $ownerId, null);
		} else {
			$file = $this->fileService->getFileHandle($fileId, $ownerId, $userId);
		}

		// make sure file can be read when checking file info
		if (!$file) {
			$this->logger->error('File not found or user unauthorized.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// trigger read operation while checking file info for user
		// after acquiring the token
		try {
			$file->fopen('rb');
		} catch (NotPermittedException $e) {
			$this->logger->error('Could not open file - {error}', ['app' => $this->appName, 'error' => $e->getMessage()]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('CheckFileInfo: unexpected exception - {error}', ['app' => $this->appName, 'error' => $e->getMessage()]);
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// cannot write relative when public link or federated access, or when parent folder is not writable
		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$userCanNotWriteRelative = !$file->getParent()->isCreatable();
		} else {
			$userCanNotWriteRelative = true;
		}

		// check permissions
		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		$canPrint = $res['attributes'] & WOPI::ATTR_CAN_PRINT;
		$canExport = $res['attributes'] & WOPI::ATTR_CAN_EXPORT;

		// check watermark text
		if ($res['attributes'] & WOPI::ATTR_HAS_WATERMARK) {
			$watermark = \str_replace(
				'{viewer-email}',
				$userEmail === null ? $userFriendlyName : $userEmail,
				$this->appConfig->getAppValue('watermark_text')
			);
		} else {
			$watermark = null;
		}

		$storage = $file->getStorage();
		$supportsLocks = $canWrite && $storage->instanceOfStorage(IPersistentLockingStorage::class);

		$result = [
			'BaseFileName' => $file->getName(),
			'Size' => $file->getSize(),
			'Version' => $version,
			'OwnerId' => $ownerId,
			'UserId' => $userId,
			'IsAnonymousUser' => $isAnonymousUser,
			'UserFriendlyName' => $userFriendlyName,
			'UserCanWrite' => $canWrite,
			'SupportsGetLock' => false,
			'SupportsLocks' => $supportsLocks,
			'UserCanNotWriteRelative' => $userCanNotWriteRelative,
			'PostMessageOrigin' => $postMessageOrigin,
			'LastModifiedTime' => Helper::toISO8601($file->getMTime()),
			'DisablePrint' => !$canPrint,
			'HidePrintOption' => !$canPrint,
			'DisableExport' => !$canExport,
			'HideExportOption' => !$canExport,
			'HideSaveOption' => !$canExport, // dont show the §save to OC§ option as user cannot download file
			'DisableCopy' => !$canExport, // disallow copying in document
			'WatermarkText' => $watermark,
		];
		
		$this->logger->debug("CheckFileInfo: Result: {result}", ['app' => $this->appName, 'result' => $result]);
		return new JSONResponse($result, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * The Files endpoint file-level operations.
	 */
	public function wopiFileOperation(string $documentId): JSONResponse {
		$operation = $this->request->getHeader('X-WOPI-Override');
		switch ($operation) {
			case 'PUT_RELATIVE':
				return $this->wopiPutFileRelative($documentId);
			case 'LOCK':
				if ($this->request->getHeader('X-WOPI-OldLock')) {
					return $this->wopiUnlockAndRelock($documentId);
				}
				return $this->wopiLock($documentId);
			case 'UNLOCK':
				return $this->wopiUnlock($documentId);
			case 'REFRESH_LOCK':
				return $this->wopiRefreshLock($documentId);
			case 'GET_LOCK':
			case 'DELETE':
			case 'RENAME_FILE':
			case 'PUT_USER_INFO':
			case 'GET_SHARE_URL':
				$this->logger->warning("FileOperation: $operation unsupported", ['app' => $this->appName]);
				break;
			default:
				$this->logger->warning("FileOperation: $operation unknown", ['app' => $this->appName]);
		}

		return new JSONResponse([], Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * The File contents endpoint provides access to retrieve the contents of a file.
	 *
	 * The GetFile operation retrieves a file from a host.
	 */
	public function wopiGetFile(string $documentId): Response {
		$wopiToken = $this->request->getParam('access_token');

		$this->logger->debug('GetFile: documentId {documentId}.', [
			'app' => $this->appName,
			'documentId' => $documentId
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('GetFile: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], $res['editor']);
		} else {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], null);
		}

		if (!$file) {
			$this->logger->warning('GetFile: Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DownloadResponse($this->request, $file);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * The File contents endpoint provides access to update the contents of a file.
	 *
	 * The PutFile operation updates a file’s binary contents.
	 */
	public function wopiPutFile(string $documentId): JSONResponse {
		$wopiToken = $this->request->getParam('access_token');

		$this->logger->debug('PutFile: documentId {documentId}.', [
			'app' => $this->appName,
			'documentId' => $documentId
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('PutFile: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		if (!$canWrite) {
			$this->logger->debug('PutFile: not allowed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		// Retrieve wopi timestamp header
		$wopiHeaderTime = $this->request->getHeader('X-LOOL-WOPI-Timestamp');
		$this->logger->debug('PutFile: WOPI header timestamp: {wopiHeaderTime}', [
			'app' => $this->appName,
			'wopiHeaderTime' => $wopiHeaderTime
		]);

		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], $res['editor']);
		} else {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], null);
		}

		if (!$file) {
			$this->logger->warning('PutFile: Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// Handle wopiHeaderTime
		if (!$wopiHeaderTime) {
			$this->logger->debug('PutFile: X-LOOL-WOPI-Timestamp absent. Saving file.', ['app' => $this->appName]);
		} elseif ($wopiHeaderTime != Helper::toISO8601($file->getMTime())) {
			$this->logger->debug('PutFile: Document timestamp mismatch ! WOPI client says mtime {headerTime} but storage says {storageTime}', [
				'app' => $this->appName,
				'headerTime' => $wopiHeaderTime,
				'storageTime' => Helper::toISO8601($file->getMtime())
			]);
			// Tell WOPI client about this conflict.
			return new JSONResponse(['LOOLStatusCode' => self::LOOL_STATUS_DOC_CHANGED], Http::STATUS_CONFLICT);
		}

		// Read the contents of the file from the POST body and store.
		$this->logger->debug(
			'PutFile: storing file {fileId}, editor: {editor}, owner: {owner}.',
			[
				'app' => $this->appName,
				'fileId' => $res['fileid'],
				'editor' => $res['editor'],
				'owner' => $res['owner']
			]
		);
		$content = \fopen('php://input', 'r');
		$file->putContent($content);

		$this->logger->debug('PutFile: mtime', ['app' => $this->appName]);

		$mtime = $file->getMtime();

		return new JSONResponse([
			'status' => 'success',
			'LastModifiedTime' => Helper::toISO8601($mtime)
		], Http::STATUS_OK);
	}

	/**
	 * The Files endpoint operation PutFileRelative.
	 */
	public function wopiPutFileRelative(string $documentId): JSONResponse {
		$wopiToken = $this->request->getParam('access_token');

		$this->logger->debug('PutFileRelative: documentId {documentId}.', [
			'app' => $this->appName,
			'documentId' => $documentId
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('PutFileRelative: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		if (!$canWrite) {
			$this->logger->debug('PutFileRelative: not allowed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], $res['editor']);
		} else {
			$this->logger->warning('PutFileRelative: Unexpected call for function for anonymous access', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if (!$file) {
			$this->logger->warning('PutFileRelative: Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// Retrieve suggested target
		$suggested = $this->request->getHeader('X-WOPI-SuggestedTarget');
		$suggested = \iconv('utf-7', 'utf-8', $suggested);

		if ($suggested[0] === '.') {
			$path = \dirname($file->getPath()) . '/New File' . $suggested;
		} elseif ($suggested[0] !== '/') {
			$path = \dirname($file->getPath()) . '/' . $suggested;
		} else {
			$this->logger->debug('PutFileRelative: Suggested path {suggested} not supported', ['app' => $this->appName, 'suggested' => $suggested]);
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		// create a unique new file
		$newFile = $this->fileService->newFile($path, $res['owner'], $res['editor']);
		if (!$newFile) {
			$this->logger->warning('PutFileRelative: could not create new file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// Read the contents of the file from the POST body and store.
		$content = \fopen('php://input', 'r');

		$newFile->putContent($content);
		$mtime = $newFile->getMtime();

		$this->logger->debug(
			'PutFileRelative: storing file {fileId}, editor: {editor}, owner: {owner}, mtime: {mtime}.',
			[
			'app' => $this->appName,
			'fileId' => $newFile->getId(),
			'editor' => $res['editor'],
			'owner' => $res['owner'],
			'mtime' => $mtime
			]
		);

		// we should preserve the original PostMessageOrigin
		// otherwise this will change it to serverHost after save-as
		// then we can no longer know the outer frame's origin.
		$serverHost = $res['server_host'] ? $res['server_host'] : $this->request->getServerProtocol() . '://' . $this->request->getServerHost();

		// Continue editing
		$attributes = WOPI::ATTR_CAN_VIEW | WOPI::ATTR_CAN_UPDATE | WOPI::ATTR_CAN_PRINT;

		// generate a token for the new file
		$row = new Db\Wopi();
		$tokenArray = $row->generateToken($newFile->getId(), 0, $attributes, $serverHost, $res['owner'], $res['editor']);

		$wopi = 'index.php/apps/richdocuments/wopi/files/' . $newFile->getId() . '_' . $this->settings->getSystemValue('instanceid') . '?access_token=' . $tokenArray['access_token'];
		$url = $this->urlGenerator->getAbsoluteURL($wopi);

		return new JSONResponse([ 'Name' => $newFile->getName(), 'Url' => $url ], Http::STATUS_OK);
	}

	/**
	 * The Files endpoint operation Lock.
	 */
	public function wopiLock(string $documentId): JSONResponse {
		$wopiToken = $this->request->getParam('access_token');
		$wopiLock = $this->request->getHeader('X-WOPI-Lock');

		$this->logger->debug('Lock: documentId {documentId}, wopiLock {wopiLock}.', [
			'app' => $this->appName,
			'documentId' => $documentId,
			'wopiLock' => $wopiLock,
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('Lock: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		if (!$canWrite) {
			$this->logger->debug('Lock: not allowed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], $res['editor']);
		} else {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], null);
		}

		if (!$file) {
			$this->logger->warning('Lock: Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$storage = $file->getStorage();

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$locks = $storage->getLocks($file->getInternalPath(), false);

		// handle non-existing lock
		if (empty($locks)) {
			// get locking user
			if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
				$editor = $this->userManager->get($res['editor']);
				$lockUser = $this->l10n->t('%s via Office Collabora', [$editor->getDisplayName()]);
			} elseif ($res['editor'] && $res['editor'] !== '' && ($res['attributes'] & WOPI::ATTR_FEDERATED)) {
				$lockUser = $this->l10n->t('%s via Office Collabora', [$res['editor']]);
			} else {
				$lockUser = $this->l10n->t('Public Link User via Collabora Online');
			}

			// set new lock
			/**
			 * @var IPersistentLockingStorage $storage
			 * @phpstan-ignore-next-line
			 */
			'@phan-var IPersistentLockingStorage $storage';
			$storage->lockNodePersistent($file->getInternalPath(), [
				'token' => $wopiLock,
				'owner' => $lockUser
			]);
			return new JSONResponse([], Http::STATUS_OK);
		}

		// handle existing lock

		$currentLock = $locks[0];
		if ($currentLock->getToken() !== $wopiLock) {
			// foreign lock conflict
			$this->logger->debug('Lock: resource has lock conflict.', ['app' => $this->appName]);

			$response = new JSONResponse([], Http::STATUS_CONFLICT);
			$response->addHeader('X-WOPI-LockFailureReason', "Locked by {$currentLock->getOwner()}");
			$response->addHeader('X-WOPI-Lock', $currentLock->getToken());
			return $response;
		}

		$this->logger->debug('Lock: resource already locked, refresh.', ['app' => $this->appName]);

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$storage->lockNodePersistent($file->getInternalPath(), [
			'token' => $wopiLock,
		]);

		return new JSONResponse([], Http::STATUS_OK);
	}

	/**
	 * The Files endpoint operation Unlock.
	 */
	public function wopiUnlock(string $documentId): JSONResponse {
		$wopiToken = $this->request->getParam('access_token');
		$wopiLock = $this->request->getHeader('X-WOPI-Lock');

		$this->logger->debug('Unlock: documentId {documentId}, wopiLock {wopiLock}.', [
			'app' => $this->appName,
			'documentId' => $documentId,
			'wopiLock' => $wopiLock,
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('Unlock: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		if (!$canWrite) {
			$this->logger->debug('Unlock: not allowed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], $res['editor']);
		} else {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], null);
		}

		if (!$file) {
			$this->logger->warning('Unlock: Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$storage = $file->getStorage();

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$locks = $storage->getLocks($file->getInternalPath(), false);

		// handle non-existing lock

		if (empty($locks)) {
			$this->logger->debug('Unlock: file is not locked.', ['app' => $this->appName]);

			$response = new JSONResponse([], Http::STATUS_CONFLICT);
			$response->addHeader('X-WOPI-LockFailureReason', "Attempt to unlock the file that is not locked");
			$response->addHeader('X-WOPI-Lock', '');
			return $response;
		}

		// handle existing lock

		$currentLock = $locks[0];
		if ($currentLock->getToken() !== $wopiLock) {
			// foreign lock conflict
			$this->logger->debug('Unlock: resource has lock conflict.', ['app' => $this->appName]);

			$response = new JSONResponse([], Http::STATUS_CONFLICT);
			$response->addHeader('X-WOPI-LockFailureReason', "Locked by {$currentLock->getOwner()}");
			$response->addHeader('X-WOPI-Lock', $currentLock->getToken());
			return $response;
		}

		$this->logger->debug('Unlock: unlocking resource.', ['app' => $this->appName]);

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$storage->unlockNodePersistent($file->getInternalPath(), [
			'token' => $wopiLock,
		]);

		return new JSONResponse([], Http::STATUS_OK);
	}

	/**
	 * The Files endpoint operation RefreshLock.
	 */
	public function wopiRefreshLock(string $documentId): JSONResponse {
		$wopiToken = $this->request->getParam('access_token');
		$wopiLock = $this->request->getHeader('X-WOPI-Lock');

		$this->logger->debug('RefreshLock: documentId {documentId}, wopiLock {wopiLock}.', [
			'app' => $this->appName,
			'documentId' => $documentId,
			'wopiLock' => $wopiLock,
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('RefreshLock: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		if (!$canWrite) {
			$this->logger->debug('RefreshLock: not allowed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], $res['editor']);
		} else {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], null);
		}

		if (!$file) {
			$this->logger->warning('Unlock: Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$storage = $file->getStorage();

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$locks = $storage->getLocks($file->getInternalPath(), false);

		// handle non-existing lock

		if (empty($locks)) {
			$this->logger->debug('RefreshLock: file is not locked.', ['app' => $this->appName]);

			$response = new JSONResponse([], Http::STATUS_CONFLICT);
			$response->addHeader('X-WOPI-LockFailureReason', "Attempt to refresh lock on the file that is not locked");
			$response->addHeader('X-WOPI-Lock', '');
			return $response;
		}

		// handle existing lock

		$currentLock = $locks[0];
		if ($currentLock->getToken() !== $wopiLock) {
			// foreign lock conflict
			$this->logger->debug('RefreshLock: resource has lock conflict.', ['app' => $this->appName]);

			$response = new JSONResponse([], Http::STATUS_CONFLICT);
			$response->addHeader('X-WOPI-LockFailureReason', "Locked by {$currentLock->getOwner()}");
			$response->addHeader('X-WOPI-Lock', $currentLock->getToken());
			return $response;
		}

		$this->logger->debug('RefreshLock: resource already locked, refresh.', ['app' => $this->appName]);

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$storage->lockNodePersistent($file->getInternalPath(), [
			'token' => $wopiLock,
		]);

		return new JSONResponse([], Http::STATUS_OK);
	}

	/**
	 * The Files endpoint operation UnlockAndRelock.
	 */
	public function wopiUnlockAndRelock(string $documentId): JSONResponse {
		$wopiToken = $this->request->getParam('access_token');
		$wopiLock = $this->request->getHeader('X-WOPI-Lock');
		$wopiLockOld = $this->request->getHeader('X-WOPI-OldLock');

		$this->logger->debug('Unlock: documentId {documentId}, wopiLock {wopiLock}, wopiLockOld {wopiLockOld}.', [
			'app' => $this->appName,
			'documentId' => $documentId,
			'wopiLock' => $wopiLock,
			'wopiLockOld' => $wopiLockOld,
		]);

		$res = $this->getWopiInfoForToken($documentId, $wopiToken);
		if (!$res) {
			$this->logger->debug('Unlock: get token failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		if (!$canWrite) {
			$this->logger->debug('Unlock: not allowed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], $res['editor']);
		} else {
			$file = $this->fileService->getFileHandle($res['fileid'], $res['owner'], null);
		}

		if (!$file) {
			$this->logger->warning('Unlock: Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$storage = $file->getStorage();

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$locks = $storage->getLocks($file->getInternalPath(), false);

		// handle non-existing lock

		if (empty($locks)) {
			$this->logger->debug('UnlockAndRelock: file is not locked.', ['app' => $this->appName]);

			$response = new JSONResponse([], Http::STATUS_CONFLICT);
			$response->addHeader('X-WOPI-LockFailureReason', "Attempt to unlock and refresh on the file that is not locked");
			$response->addHeader('X-WOPI-Lock', '');
			return $response;
		}

		// handle existing lock

		$currentLock = $locks[0];
		if ($currentLock->getToken() !== $wopiLockOld) {
			// foreign lock conflict
			$this->logger->debug('UnlockAndRelock: resource has lock conflict.', ['app' => $this->appName]);

			$response = new JSONResponse([], Http::STATUS_CONFLICT);
			$response->addHeader('X-WOPI-LockFailureReason', "Locked by {$currentLock->getOwner()}");
			$response->addHeader('X-WOPI-Lock', $currentLock->getToken());
			return $response;
		}

		$this->logger->debug('UnlockAndRelock: unlocking the old lock and locking with new lock.', ['app' => $this->appName]);

		// get re-locking user
		if ($res['editor'] && $res['editor'] !== '' && !($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$editor = $this->userManager->get($res['editor']);
			$lockUser = $this->l10n->t('%s via Office Collabora', [$editor->getDisplayName()]);
		} elseif ($res['editor'] && $res['editor'] !== '' && ($res['attributes'] & WOPI::ATTR_FEDERATED)) {
			$lockUser = $this->l10n->t('%s via Office Collabora', [$res['editor']]);
		} else {
			$lockUser = $this->l10n->t('Public Link User via Collabora Online');
		}

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		*/
		'@phan-var IPersistentLockingStorage $storage';
		$storage->unlockNodePersistent($file->getInternalPath(), [
			'token' => $wopiLockOld,
		]);

		/**
		 * @var IPersistentLockingStorage $storage
		 * @phpstan-ignore-next-line
		 */
		'@phan-var IPersistentLockingStorage $storage';
		$storage->lockNodePersistent($file->getInternalPath(), [
			'token' => $wopiLock,
			'owner' => $lockUser
		]);
		return new JSONResponse([], Http::STATUS_OK);
	}

	private function getWopiInfoForToken(string $documentId, $wopiToken): ?array {
		$token = $this->request->getParam('access_token');

		list($fileId, , $version, $sessionId) = Helper::parseDocumentId($documentId);
		$this->logger->debug('Getting wopi token {token} info for file {fileId}, version {version},', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version,
			'token' => $token ]);

		$row = new Db\Wopi();
		$res = $row->getWopiForToken($wopiToken);
		if (!$res) {
			$this->logger->debug('Cannot find token.', ['app' => $this->appName]);
			return null;
		}

		// check if the token is for the given file
		if ($res['fileid'] != $fileId) {
			$this->logger->debug('Provided wopi token for a wrong file.', ['app' => $this->appName]);
			return null;
		}

		return $res;
	}
}
