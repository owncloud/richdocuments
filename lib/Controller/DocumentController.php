<?php
/**
 * @author Victor Dubiniuk <victor.dubiniuk@gmail.com>
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
use OCA\Richdocuments\Db;
use OCA\Richdocuments\Db\Wopi;
use OCA\Richdocuments\DiscoveryService;
use OCA\Richdocuments\DocumentService;
use OCA\Richdocuments\Helper;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\InvalidPathException;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\Template;
use OCP\IUserManager;
use OCP\IPreview;
use OC\Files\View;

class DocumentController extends Controller {
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
	
	/**
	 * The path to the ODT template
	 */
	public const ODT_TEMPLATE_PATH = '/assets/odttemplate.odt';

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
		INavigationManager $navigationManager
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
	}

	private function responseError($message, $hint = '') {
		$errors = ['errors' => [['error' => $message, 'hint' => $hint]]];
		$response = new TemplateResponse('', 'error', $errors, 'error');
		return $response;
	}

	/**
	 * Strips the path and query parameters from the URL.
	 *
	 * @param string $url
	 * @return string
	 */
	private function domainOnly($url) {
		$parsed_url = \parse_url($url);
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host   = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port   = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		return "$scheme$host$port";
	}

	/**
	 * Get collabora document for:
	 * - the base template if fileId is null
	 * - file in user folder (also shared by user/group) if fileId not null
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index($fileId, $dir) {
		if (\is_numeric($fileId)) {
			// parse fileId pointing to file
			$fileId = (int) $fileId;
		} elseif ($fileId === '' || $fileId === null) {
			// base template
			$fileId = null;
		} else {
			return $this->responseError($this->l10n->t('Invalid request parameters'));
		}

		// Get doc index if possible
		if ($fileId !== null) {
			try {
				// Normal editing or share by user/group
				$docinfo = $this->documentService->getDocumentByUserId($this->getCurrentUserUID(), $fileId, $dir);
				if (!$docinfo) {
					$this->logger->warning("Cannot retrieve document with fileid {fileid} in dir {dir}", ["fileid" => $fileId, "dir" => $dir]);
					return $this->responseError(
						$this->l10n->t('Collabora Online: Error encountered while opening the document.', []),
						$this->l10n->t('Please contact the administrator.', [])
					);
				}

				if (isset($docinfo['federatedServer'], $docinfo['federatedToken'])) {
					return $this->responseError(
						$this->l10n->t('Collabora Online: Federated shares not supported yet.', []),
						$this->l10n->t('Please contact the administrator.', [])
					);
				}

				// Get document discovery
				$wopiSrc = $this->discoveryService->getWopiSrc($docinfo['mimetype']);
				if (!$wopiSrc) {
					$this->logger->error("Cannot retrieve discovery for document", []);
					return $this->responseError(
						$this->l10n->t('Collabora Online: Error encountered while opening the document.', []),
						$this->l10n->t('Please contact the administrator.', [])
					);
				}
	
				// Decide max upload size
				$maxUploadFilesize = \OCP\Util::maxUploadFilesize("/");

				// Get wopi access info
				$wopiAccessInfo = $this->createWopiSessionForAuthUser($docinfo);

				// Create document index
				$docRetVal = [
					'uploadMaxFilesize' => $maxUploadFilesize,
					'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize($maxUploadFilesize),
					'title' => $docinfo['name'],
					'fileId' => $docinfo['fileid'],
					'instanceId' => $this->settings->getSystemValue('instanceid'),
					'locale' => $this->getLocale(),
					'version' => $docinfo['version'],
					'sessionId' => $wopiAccessInfo['sessionid'],
					'access_token' => $wopiAccessInfo['access_token'],
					'access_token_ttl' => $wopiAccessInfo['access_token_ttl'],
					'default_action' => $wopiSrc['action'],
					'urlsrc' => $wopiSrc['urlsrc'],
					'path' => $docinfo['path']
				];
			} catch (\Exception $e) {
				$this->logger->error('Collabora Online: Encountered error {error}', ['app' => $this->appName, 'error' => $e->getMessage()]);
				return $this->responseError(
					$this->l10n->t('Collabora Online: Cannot open document due to unexpected error.'),
					$this->l10n->t('Please contact the administrator.', [])
				);
			}
		} else {
			// base template
			$docRetVal = [];
		}

		// Normal editing and user/group share editing
		// Parameter $dir is not used during indexing, but might be used by Document Server
		$renderAs = 'user';

		// Handle general response
		$wopiRemote = $this->discoveryService->getWopiUrl();
		$webSocket = $this->parseWopiSocket($wopiRemote);
		if (!$webSocket) {
			return $this->responseError($this->l10n->t('Collabora Online: Invalid URL "%s".', [$wopiRemote]), $this->l10n->t('Please ask your administrator to check the Collabora Online server setting.'));
		}

		$retVal = \array_merge(
			[
				'enable_previews' => $this->settings->getSystemValue('enable_previews', true),
				'wopi_url' => $webSocket,
				'doc_format' => $this->appConfig->getAppValue('doc_format'),
				'instanceId' => $this->settings->getSystemValue('instanceid'),
				'canonical_webroot' => $this->appConfig->getAppValue('canonical_webroot'),
				'show_custom_header' => false
			],
			$docRetVal
		);
		
		// set active navigation entry
		$this->navigationManager->setActiveEntry('richdocuments_index');

		// prepare template response
		$response = new TemplateResponse('richdocuments', 'documents', $retVal, $renderAs);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain($this->domainOnly($wopiRemote));
		$policy->allowInlineScript(true);
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Get collabora document for public link by share token shareToken:
	 * - file shared by public link (shareToken points directly to file)
	 * - file in public folder shared by link (shareToken points to shared folder, and file to get is identified by fileId)
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function public($shareToken, $fileId) {
		if (\is_string($shareToken) && \strlen($shareToken) > 0 && \is_numeric($fileId)) {
			// fileId is a numeric string indicating the file in the folder link share (via shareToken)
			$fileId = (int) $fileId;
		} elseif (\is_string($shareToken) && \strlen($shareToken) > 0 && ($fileId === '' || $fileId === null)) {
			// shareToken points directly to the file
			$fileId = null;
		} else {
			return $this->responseError($this->l10n->t('Invalid request parameters'));
		}

		// Public share link (folder or file)
		$renderAs = 'base';

		// Handle general response
		$wopiRemote = $this->discoveryService->getWopiUrl();
		$webSocket = $this->parseWopiSocket($wopiRemote);
		if (!$webSocket) {
			return $this->responseError($this->l10n->t('Collabora Online: Invalid URL "%s".', [$wopiRemote]), $this->l10n->t('Please ask your administrator to check the Collabora Online server setting.'));
		}

		try {
			// Share by link in public folder or file
			$docinfo = $this->documentService->getDocumentByShareToken($shareToken, $fileId);
			if (!$docinfo) {
				$this->logger->warning("Cannot retrieve document from share {token} that has fileid {fileId}", ["token" => $shareToken, "fileId" => $fileId]);
				return $this->responseError(
					$this->l10n->t('Collabora Online: Error encountered while opening the document.', []),
					$this->l10n->t('Please contact the administrator.', [])
				);
			}

			// Get wopi token
			$wopiAccessInfo = $this->createWopiSessionForPublicLink($docinfo);
		} catch (\Exception $e) {
			$this->logger->error('Collabora Online: Encountered error {error}', ['app' => $this->appName, 'error' => $e->getMessage()]);
			return $this->responseError($this->l10n->t('Collabora Online: Cannot open document.'), $e->getMessage());
		}

		// Get document discovery
		$wopiSrc = $this->discoveryService->getWopiSrc($docinfo['mimetype']);
		if (!$wopiSrc) {
			$this->logger->error("Cannot retrieve discovery for document", []);
			return $this->responseError(
				$this->l10n->t('Collabora Online: Error encountered while opening the document.', []),
				$this->l10n->t('Please contact the administrator.', [])
			);
		}

		// FIXME: In public links allow max 100MB
		$maxUploadFilesize = 100000000;

		$this->navigationManager->setActiveEntry('richdocuments_index');
		$retVal = [
			'uploadMaxFilesize' => $maxUploadFilesize,
			'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize($maxUploadFilesize),
			'title' => $docinfo['name'],
			'fileId' => $docinfo['fileid'],
			'locale' => $this->getLocale(),
			'version' => $docinfo['version'],
			'sessionId' => $wopiAccessInfo['sessionid'],
			'access_token' => $wopiAccessInfo['access_token'],
			'access_token_ttl' => $wopiAccessInfo['access_token_ttl'],
			'urlsrc' => $wopiSrc['urlsrc'],
			'default_action' => $wopiSrc['action'],
			'path' => $docinfo['path'],
			'enable_previews' => $this->settings->getSystemValue('enable_previews', true),
			'wopi_url' => $webSocket,
			'doc_format' => $this->appConfig->getAppValue('doc_format'),
			'instanceId' => $this->settings->getSystemValue('instanceid'),
			'canonical_webroot' => $this->appConfig->getAppValue('canonical_webroot'),
			'show_custom_header' => true // public link should show a customer header without buttons
		];

		$response = new TemplateResponse('richdocuments', 'documents', $retVal, $renderAs);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain($this->domainOnly($wopiRemote));
		$policy->allowInlineScript(true);
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * API endpoint for external-apps such as new owncloud web front-end
	 * to return the information needed to load the document using the fileId.
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function get($fileId) {
		try {
			if (\is_numeric($fileId)) {
				// parse fileId pointing to file
				$fileId = (int) $fileId;
			} else {
				return $this->responseError($this->l10n->t('Invalid request parameters'));
			}

			// Normal editing or share by user/group
			$docinfo = $this->documentService->getDocumentByUserId($this->getCurrentUserUID(), $fileId, null);
			if (!$docinfo) {
				$this->logger->warning("Cannot retrieve document with fileid {fileid}", ["fileid" => $fileId]);
				return null;
			}

			// Get document discovery
			$wopiSrc = $this->discoveryService->getWopiSrc($docinfo['mimetype']);
			if (!$wopiSrc) {
				$this->logger->error("Cannot retrieve discovery for document", []);
				return null;
			}

			// Restrict filesize
			$maxUploadFilesize = \OCP\Util::maxUploadFilesize("/");

			// Get wopi token
			$wopiAccessInfo = $this->createWopiSessionForAuthUser($docinfo);

			// Create document index
			$docRetVal = [
				'uploadMaxFilesize' => $maxUploadFilesize,
				'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize($maxUploadFilesize),
				'title' => $docinfo['name'],
				'fileId' => $docinfo['fileid'],
				'instanceId' => $this->settings->getSystemValue('instanceid'),
				'locale' => $this->getLocale(),
				'version' => $docinfo['version'],
				'sessionId' => $wopiAccessInfo['sessionid'],
				'access_token' => $wopiAccessInfo['access_token'],
				'access_token_ttl' => $wopiAccessInfo['access_token_ttl'],
				'urlsrc' => $wopiSrc['urlsrc'],
				'default_action' => $wopiSrc['action'],
				'path' => $docinfo['path']
			];
			return new JSONResponse($docRetVal);
		} catch (\Exception $e) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Document index could not be found'
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Get current user locale
	 */
	private function getLocale() : string {
		return \strtolower(\str_replace('_', '-', $this->settings->getUserValue($this->getCurrentUserUID(), 'core', 'lang', 'en')));
	}

	/**
	 * Parse wopi socket from the wopi url
	 */
	private function parseWopiSocket(string $wopiRemote) : ?string {
		$wopiRemoteParts = \parse_url($wopiRemote);
		if (isset($wopiRemoteParts['scheme'], $wopiRemoteParts['host'])) {
			$webSocketProtocol = "ws://";
			if ($wopiRemoteParts['scheme'] == "https") {
				$webSocketProtocol = "wss://";
			}
			$webSocket = \sprintf(
				"%s%s%s",
				$webSocketProtocol,
				$wopiRemoteParts['host'],
				isset($wopiRemoteParts['port']) ? ":" . $wopiRemoteParts['port'] : ""
			);
			return $webSocket;
		}
		return null;
	}

	/**
	 * API endpoint for to create new document
	 *
	 * @NoAdminRequired
	 */
	public function create() {
		$mimetype = $this->request->getParam('mimetype');
		$filename = $this->request->getParam('filename');
		$dir = $this->request->getParam('dir');

		$view = new View('/' . $this->getCurrentUserUID() . '/files');

		if (!$dir) {
			$dir = '/';
		}

		$basename = $this->l10n->t('New Document.odt');
		switch ($mimetype) {
			case 'application/vnd.oasis.opendocument.spreadsheet':
				$basename = $this->l10n->t('New Spreadsheet.ods');
				break;
			case 'application/vnd.oasis.opendocument.presentation':
				$basename = $this->l10n->t('New Presentation.odp');
				break;
			case 'application/vnd.oasis.opendocument.graphics':
				$basename = $this->l10n->t('New Drawing.odg');
				break;
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				$basename = $this->l10n->t('New Document.docx');
				break;
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				$basename = $this->l10n->t('New Spreadsheet.xlsx');
				break;
			case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
				$basename = $this->l10n->t('New Presentation.pptx');
				break;
			default:
				// to be safe
				$mimetype = 'application/vnd.oasis.opendocument.text';
				break;
		}

		if (!$filename) {
			$path = Helper::getNewFileName($view, $dir . '/' . $basename);
		} else {
			$path = $dir . '/' . $filename;
		}

		if ($filename !== null) {
			try {
				$view->verifyPath($path, $filename);
			} catch (InvalidPathException $e) {
				$this->logger->error('Collabora Online: Encountered error {error}', ['app' => $this->appName, 'error' => $e->getMessage()]);
				return [
					'status' => 'error',
					'message' => $this->l10n->t('Invalid filename'),
				];
			}
		}

		$content = '';
		if (\class_exists('\OC\Files\Type\TemplateManager')) {
			$manager = \OC_Helper::getFileTemplateManager();
			$content = $manager->getTemplate($mimetype);
		}

		if (!$content) {
			$content = \file_get_contents($this->appManager->getAppPath($this->appName) . self::ODT_TEMPLATE_PATH);
		}

		// Get document discovery
		$wopiSrc = $this->discoveryService->getWopiSrc($mimetype);
		if (!$wopiSrc) {
			return [
				'status' => 'error',
				'message' => $this->l10n->t('Collabora Online: Unable to read WOPI discovery for given document', []),
				'hint' => $this->l10n->t('Please contact the administrator.', [])
			];
		}

		if ($content && $view->file_put_contents($path, $content)) {
			$info = $view->getFileInfo($path);
			$response =  [
				'status' => 'success',
				'fileid' => $info['fileid'],
				'urlsrc' => $wopiSrc['urlsrc'],
				'action' => $wopiSrc['action'],
				'locale' => $this->getLocale(),
				'data' => \OCA\Files\Helper::formatFileInfo($info)
			];
		} else {
			$response =  [
				'status' => 'error',
				'message' => (string) $this->l10n->t('Can\'t create document')
			];
		}
		return $response;
	}

	private function isAllowedEditor($editorUid) {
		// Check if the editor (user who is accessing) is in editable group
		// 1. No edit groups are set or
		// 2. if they are set, it is in one of the edit groups
		$editGroups = \array_filter(\explode('|', $this->appConfig->getAppValue('edit_groups')));
		$isAllowed = true;
		if (\count($editGroups) > 0) {
			$editor = $this->userManager->get($editorUid);
			if (!$editor) {
				return false;
			}

			$isAllowed = false;
			foreach ($editGroups as $editGroup) {
				$editorGroup = $this->groupManager->get($editGroup);
				if ($editorGroup !== null && $editorGroup->inGroup($editor)) {
					$this->logger->debug("Editor {editor} is in edit group {group}", [
						'app' => $this->appName,
						'editor' => $editorUid,
						'group' => $editGroup
					]);
					$isAllowed = true;
					break;
				}
			}
		}

		return $isAllowed;
	}

	/**
	 * Generates and returns an wopi access info containing token for a given fileId.
	 *
	 * @param array $docInfo doc index as retrieved from DocumentService
	 * @return array wopi access info
	 *
	 * @throws \Exception
	 */
	private function createWopiSessionForAuthUser(array $docInfo) : array {
		$currentUser = $this->getCurrentUserUID();
		$ownerUid = $docInfo['owner'];
		$allowEdit = $docInfo['allowEdit'];
		$allowExport = $docInfo['allowExport'];
		$allowPrint = $docInfo['allowPrint'];
		$secureView = $docInfo['secureView'];
		$secureViewId = $docInfo['secureViewId'];
		$mimetype = $docInfo['mimetype'];
		$fileId = $docInfo['fileid'];
		$path = $docInfo['path'];
		$version = $docInfo['version'];

		$this->logger->info('Generating WOPI Token for file {fileId}, version {version}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version ]);

		// default shared session id
		$sessionid = '0';

		$wopiSessionAttr = WOPI::ATTR_CAN_VIEW;

		// Check if edit allowed for document
		// Check if mimetime supports updates
		// Check if is allowed editor
		// If token is for some versioned file, it is not possible to edit it
		$wopiSrc = $this->discoveryService->getWopiSrc($mimetype);
		if (($allowEdit === true) && isset($wopiSrc['action'])
				&& ($wopiSrc['action'] === 'edit' || $wopiSrc['action'] === 'view_comment')
				&& ($this->isAllowedEditor($currentUser) === true) && ($version === '0')) {
			$wopiSessionAttr = $wopiSessionAttr | WOPI::ATTR_CAN_UPDATE;
		}

		// can export file in editor if download is not set or true
		if ($allowExport === true) {
			$wopiSessionAttr = $wopiSessionAttr | WOPI::ATTR_CAN_EXPORT;
		}

		// can print from editor if print is not set or true
		if ($allowPrint === true) {
			$wopiSessionAttr = $wopiSessionAttr | WOPI::ATTR_CAN_PRINT;
		}

		// restriction on view with watermarking enabled
		if ($secureView === true) {
			$wopiSessionAttr = $wopiSessionAttr | WOPI::ATTR_HAS_WATERMARK;
		}

		// if secureViewId is set, then it is a dedicated shared session
		// it should not be possible that users associated with that secureViewId see activities of other users
		// (e.g. their edits)
		if ($secureView === true && isset($secureViewId)) {
			$sessionid = \strval($secureViewId);
		}

		$this->logger->debug('File {fileid} is updateable? {allowEdit}', [
			'app' => $this->appName,
			'fileid' => $fileId,
			'allowEdit' => $allowEdit ]);
		$origin = $this->request->getHeader('ORIGIN');
		$serverHost = null;
		if ($origin === null) {
			$serverHost = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();
		} else {
			// COOL needs to know postMessageOrigin -- in case it's an external app like ownCloud Web
			// origin will be different therefore postMessages needs to target $origin instead of serverHost
			$serverHost = $origin;
		}

		$this->updateDocumentEncryptionAccessList($ownerUid, $currentUser, $path);

		$row = new Db\Wopi();
		/*
		 * Version is a string here, and arg 2 (version) should be an int.
		 * As long as the string is just a number, all is good.
		 */
		/* @phan-suppress-next-line PhanTypeMismatchArgument */
		$tokenArray = $row->generateToken($fileId, $version, $wopiSessionAttr, $serverHost, $ownerUid, $currentUser);

		// Return the token.
		$result = [
			'status' => 'success',
			'access_token' => $tokenArray['access_token'],
			'access_token_ttl' => $tokenArray['access_token_ttl'],
			'sessionid' => $sessionid
		];
		$this->logger->debug('Issued token: {result}', ['app' => $this->appName, 'result' => $result]);
		return $result;
	}

	private function updateDocumentEncryptionAccessList($owner, $currentUser, $path) {
		$encryptionManager = \OC::$server->getEncryptionManager();
		if ($encryptionManager->isEnabled()) {
			// Update the current file to be accessible with system public
			// shared key
			$this->logger->debug('Encryption enabled.', ['app' => $this->appName]);
			$absPath = '/' . $owner . '/files' .  $path;
			$accessList = \OC::$server->getEncryptionFilesHelper()->getAccessList($absPath);
			$accessList['public'] = true;
			$encryptionManager->getEncryptionModule()->update($absPath, $currentUser, $accessList);
		}
	}
	
	/**
	 * Return uid of currently logged in user.
	 *
	 * WARNING: This method is legacy, use with caution.
	 *
	 * @return string
	 */
	private function getCurrentUserUID() : string {
		$user =  \OC::$server->getUserSession()->getUser();
		$uid = $user === null ? '' : $user->getUID();
		return $uid;
	}

	/**
	 * Generates and returns an wopi access info containing token for a given fileId.
	 *
	 * @param array $docInfo doc index as retrieved from DocumentService
	 * @return array wopi access info
	 */
	private function createWopiSessionForPublicLink(array $docInfo) : array {
		$currentUser = $this->getCurrentUserUID();
		$ownerUid = $docInfo['owner'];
		$fileId = $docInfo['fileid'];
		$mimetype = $docInfo['mimetype'];
		$path = $docInfo['path'];
		$version = $docInfo['version'];
		$allowEdit = $docInfo['allowEdit'];

		$this->logger->info('Generating WOPI Token for file {fileId}, version {version}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version ]);

		$this->updateDocumentEncryptionAccessList($ownerUid, $currentUser, $path);

		$serverHost = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();

		$wopiSessionAttr = WOPI::ATTR_CAN_VIEW | WOPI::ATTR_CAN_EXPORT | WOPI::ATTR_CAN_PRINT;

		// If token is for some versioned file
		// Check if mimetime supports updates
		$wopiSrc = $this->discoveryService->getWopiSrc($mimetype);
		if (($allowEdit === true) && isset($wopiSrc['action'])
				&& ($wopiSrc['action'] === 'edit' || $wopiSrc['action'] === 'view_comment')
				&& ($version === '0')) {
			$wopiSessionAttr = $wopiSessionAttr | WOPI::ATTR_CAN_UPDATE;
		}

		$row = new Db\Wopi();
		/*
		 * Version is a string here, and arg 2 (version) should be an int.
		 * As long as the string is just a number, all is good.
		 */
		/* @phan-suppress-next-line PhanTypeMismatchArgument */
		$tokenArray = $row->generateToken($fileId, $version, $wopiSessionAttr, $serverHost, $ownerUid, $currentUser);

		// Return the token.
		$result = [
			'access_token' => $tokenArray['access_token'],
			'access_token_ttl' => $tokenArray['access_token_ttl'],
			'sessionid' => '0' // default shared session
		];
		$this->logger->debug('Issued token: {result}', ['app' => $this->appName, 'result' => $result]);
		return $result;
	}

	/**
	 * API endpoint to list all user documents
	 *
	 * lists the documents the user has access to (including shared files, once the code in core has been fixed)
	 * also adds session and member info for these files
	 *
	 * @NoAdminRequired
	 */
	public function listAll() {
		$rawDocuments = $this->documentService->getDocuments();

		$documents = [];
		$locale = $this->getLocale();
		foreach ($rawDocuments as $key=>$document) {
			if (\is_object($document)) {
				$documents[] = $document->getData();
			} else {
				$documents[$key] = $document;
			}

			// Get document discovery
			$wopiSrc = $this->discoveryService->getWopiSrc($document['mimetype']);
			if (!$wopiSrc) {
				return [
					'status' => 'error',
					'message' => $this->l10n->t('Collabora Online: Unable to read WOPI discovery for given document', []),
					'hint' => $this->l10n->t('Please contact the administrator.', [])
				];
			}

			$documents[$key]['icon'] = \preg_replace('/\.png$/', '.svg', Template::mimetype_icon($document['mimetype']));
			$documents[$key]['hasPreview'] = $this->previewManager->isMimeSupported($document['mimetype']);
			$documents[$key]['urlsrc'] = $wopiSrc['urlsrc'];
			$documents[$key]['action'] = $wopiSrc['action'];
			$documents[$key]['locale'] = $locale;
		}

		\usort($documents, function ($a, $b) {
			return @$b['mtime']-@$a['mtime'];
		});

		return [
			'status' => 'success', 'documents' => $documents
		];
	}

	/**
	 * Check if server is trusted
	 *
	 * @param string $remote a remote url
	 * @return bool indicating if given remote is trusted server
	 */
	private function isTrustedServer($remote) {
		$trustedServers = null;

		try {
			$trustedServers = \OC::$server->query(\OCA\Federation\TrustedServers::class);
		} catch (QueryException $e) {
			$this->logger->warning("Cannot load trusted servers.");
		}

		if ($trustedServers !== null && $trustedServers->isTrustedServer($remote)) {
			return true;
		}

		return false;
	}

	/**
	 * Get the wopiSrc Url from a remote server.
	 *
	 * @param string $remote a remote
	 * @return string with the wopi src Url
	 */
	private function getRemoteWopiSrc($remote) {
		if (\strpos($remote, 'http://') === false && \strpos($remote, 'https://') === false) {
			$remote = 'https://' . $remote;
		}

		if (!$this->isTrustedServer($remote)) {
			$this->logger->info("Server {server} is not trusted.", ["server" => $remote]);
			return '';
		}

		try {
			$getWopiSrcUrl = $remote . '/ocs/v2.php/apps/richdocuments/api/v1/federation?format=json';
			$client = \OC::$server->getHTTPClientService()->newClient();
			$response = $client->get($getWopiSrcUrl, ['timeout' => 5]);
			$data = \json_decode($response->getBody(), true);
			$wopiSrc = $data['ocs']['data']['wopi_url'];
			return $wopiSrc;
		} catch (\Throwable $e) {
			$this->logger->info('Cannot get the wopiSrc of remote server: ' . $remote, ['exception' => $e]);
		}

		return '';
	}

	/**
	 * Get the Url of the collabora document on a federated server.
	 *
	 * @param Node $file a remote file
	 * @return string with the Url to the given resource
	 */
	private function getRemoteFileUrl($file) {
		/** @var ExternalStorage $storage */
		$storage = $file->getStorage();

		$remote = $storage->getRemote();
		$remoteWopiSrc = $this->getRemoteWopiSrc($remote);

		if (!empty($remoteWopiSrc)) {
			$version = '0'; // FIXME
			$serverHost = \OC::$server->getURLGenerator()->getAbsoluteURL('/');
			$wopi = $this->getWopiInfoForAuthUser($file->getId(), $version, $this->uid);

			$url = \rtrim($remote, '/') . '/index.php/apps/richdocuments/remote' .
				'?shareToken=' . $storage->getToken() .
				'&remoteServer=' . $serverHost .
				'&remoteServerToken=' . $wopi['access_token'];

			if (!empty($file->getInternalPath())) {
				$url .= '&filePath=' . $file->getInternalPath();
			}

			return $url;
		}

		return '';
	}
	
	/**
	* @PublicPage
	* @NoCSRFRequired
	*
	* @param string $shareToken share token for a requested resource
	* @param string $remoteServer addres of a remote server
	* @param string $remoteServerToken wopi access token from a remote server
	* @param string $filePath path to the file if shareToken points to the shared folder
	* @return TemplateResponse
	*/
	public function remote($shareToken, $remoteServer, $remoteServerToken, $filePath = null) {
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
			$isFolderShare = $share->getNodeType() == 'folder' && $filePath;
			if ($share->getNodeType() == 'file' || $isFolderShare === true) {
				if ($isFolderShare) {
					$folder = $share->getNode();
					$file = $folder->get($filePath);
					$fileId = $file->getId();
				} else {
					$fileId = $share->getNodeId();
				}

				$remoteWopiInfo = $this->getRemoteWopiInfo($remoteServer, $remoteServerToken);
				if ($remoteWopiInfo === null) {
					$params = ['errors' => [['error' => 'Failed to get information from remote server ' . $remoteServer]]];
					return new TemplateResponse('core', 'error', $params, 'guest');
				}

				$doc = $this->getDocumentByShareToken($shareToken, $fileId);
				if ($doc == null) {
					$this->logger->warning("Null returned for document with fileid {fileid}", ["fileid" => $fileId]);
					return new TemplateResponse('core', '404', [], 'guest');
				}

				$permissions = $share->getPermissions();
				if (!$remoteWopiInfo['canwrite']) {
					$permissions = $permissions & ~ Constants::PERMISSION_UPDATE;
				}

				if (\strpos($remoteServer, 'http://') === false && \strpos($remoteServer, 'https://') === false) {
					$remoteServer = 'https://' . $remoteServer;
				}
				$currentUser = $remoteWopiInfo['editorUid'] . '@' . $remoteServer;

				$wopiInfo = $this->getWopiInfoForRemoteShare($share, $doc['fileid'], $doc['version'], $doc['path'], $permissions, $currentUser, $doc['owner']);

				// FIXME: In public links allow max 100MB
				$maxUploadFilesize = 100000000;

				$wopiRemote = $this->getWopiUrl($this->isTester());
				$webSocket = $this->getWebSocket($wopiRemote);
				if ($webSocket == null) {
					return $this->responseError($this->l10n->t('Collabora Online: Invalid URL "%s".', [$wopiRemote]), $this->l10n->t('Please ask your administrator to check the Collabora Online server setting.'));
				}

				$docIndex = [
					'enable_previews' => $this->settings->getSystemValue('enable_previews', true),
					'wopi_url' => $webSocket,
					'doc_format' => $this->appConfig->getAppValue('doc_format'),
					'canonical_webroot' => $this->appConfig->getAppValue('canonical_webroot'),
					'show_custom_header' => true,  // public link should show a customer header without buttons

					'permissions' => $permissions,
					'uploadMaxFilesize' => $maxUploadFilesize,
					'uploadMaxHumanFilesize' => \OCP\Util::humanFileSize($maxUploadFilesize),
					'title' => $doc['name'],
					'fileId' => $doc['fileid'],
					'instanceId' => $doc['instanceid'],
					'version' => $doc['version'],
					'sessionId' => $wopiInfo['sessionid'],
					'access_token' => $wopiInfo['access_token'],
					'access_token_ttl' => $wopiInfo['access_token_ttl'],
					'urlsrc' => $doc['urlsrc'],
					'path' => $doc['path']
				];

				$response = new TemplateResponse('richdocuments', 'documents', $docIndex, 'empty');
				$response->addHeader('X-Frame-Options', 'ALLOW');

				$policy = new ContentSecurityPolicy();
				$policy->addAllowedFrameDomain($this->domainOnly($wopiRemote));
				$policy->allowInlineScript(true);
				$response->setContentSecurityPolicy($policy);

				return $response;
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to open shared resource', ['app' => $this->appName]);
			$params = ['errors' => [['error' => $e->getMessage()]]];
			return new TemplateResponse('core', 'error', $params, 'guest');
		}

		return new TemplateResponse('core', '403', [], 'guest');
	}
}
