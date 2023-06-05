<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Victor Dubiniuk
 * @copyright 2014 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments\Controller;

use \OC\Files\View;
use OCA\Richdocuments\Db\Wopi;
use OCP\App\IAppManager;
use \OCP\AppFramework\Controller;
use \OCP\Constants;
use OCP\Files\InvalidPathException;
use OCP\IGroupManager;
use \OCP\IRequest;
use \OCP\IConfig;
use \OCP\IL10N;
use \OCP\AppFramework\Http\ContentSecurityPolicy;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\ICacheFactory;
use \OCP\ILogger;

use \OCA\Richdocuments\AppConfig;
use \OCA\Richdocuments\Db;
use \OCA\Richdocuments\Helper;
use \OCA\Richdocuments\DocumentService;
use \OCA\Richdocuments\Http\ResponseException;
use OCP\IUserManager;

class DocumentController extends Controller {
	private $uid;
	private $l10n;
	private $settings;
	private $appConfig;
	private $cache;
	private $logger;
	private $appManager;

	/**
	 * @var DocumentService
	 */
	private $documentService;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IUserManager
	 */
	private $userManager;
	
	public const ODT_TEMPLATE_PATH = '/assets/odttemplate.odt';

	public function __construct(
		$appName,
		IRequest $request,
		IConfig $settings,
		AppConfig $appConfig,
		IL10N $l10n,
		$uid,
		ICacheFactory $cache,
		ILogger $logger,
		DocumentService $documentService,
		IAppManager $appManager,
		IGroupManager $groupManager,
		IUserManager $userManager
	) {
		parent::__construct($appName, $request);
		$this->uid = $uid;
		$this->l10n = $l10n;
		$this->settings = $settings;
		$this->appConfig = $appConfig;
		$this->cache = $cache->create($appName);
		$this->logger = $logger;
		$this->documentService = $documentService;
		$this->appManager = $appManager;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}

	/**
	 * @param \SimpleXMLElement|null $discovery_parsed
	 * @param string $mimetype
	 */
	private function getWopiSrcUrl($discovery_parsed, $mimetype) {
		if ($discovery_parsed === null || $discovery_parsed == false) {
			return null;
		}

		$result = $discovery_parsed->xpath(\sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
		if (($result !== false) && (\count($result) > 0)) {
			return [
				'urlsrc' => (string)$result[0]['urlsrc'],
				'action' => (string)$result[0]['name']
			];
		}

		return null;
	}

	private function responseError($message, $hint = '') {
		$errors = ['errors' => [['error' => $message, 'hint' => $hint]]];
		$response = new TemplateResponse('', 'error', $errors, 'error');
		return $response;
	}

	/**
	 * Return the original wopi url or test wopi url
	 * @param boolean $tester
	 */
	private function getWopiUrl($tester) {
		$wopiurl = '';
		if ($tester) {
			$wopiurl = $this->appConfig->getAppValue('test_wopi_url');
		} else {
			$wopiurl = $this->appConfig->getAppValue('wopi_url');
		}

		return $wopiurl;
	}

	/**
	 * Return true if the currently logged in user is a tester.
	 * This depends on whether current user is the member of one of the groups
	 * mentioned in settings (test_server_groups)
	 */
	private function isTester() {
		$tester = false;

		$user = \OC::$server->getUserSession()->getUser();
		if ($user === null) {
			return false;
		}

		$uid = $user->getUID();
		$testgroups = \array_filter(\explode('|', $this->appConfig->getAppValue('test_server_groups')));
		$this->logger->debug('Testgroups are {testgroups}', [ 'app' => $this->appName, 'testgroups' => $testgroups ]);
		foreach ($testgroups as $testgroup) {
			$test = $this->groupManager->get($testgroup);
			if ($test !== null && \sizeof($test->searchUsers($uid)) > 0) {
				$this->logger->debug('User {user} found in {group}', ['app' => $this->appName, 'user' => $uid, 'group' => $testgroup ]);
				$tester = true;
				break;
			}
		}

		return $tester;
	}

	/** Return the content of discovery.xml - either from cache, or download it.
	 * @return string
	 */
	private function getDiscovery() {
		$tester = $this->isTester();
		$wopiRemote = $this->getWopiUrl($tester);
		$discoveryKey = 'discovery.xml';
		if ($tester) {
			$discoveryKey = 'discovery.xml_test';
		}
		// Provides access to information about the capabilities of a WOPI client
		// and the mechanisms for invoking those abilities through URIs.
		$wopiDiscovery = $wopiRemote . '/hosting/discovery';

		// Read the memcached value (if the memcache is installed)
		$discovery = $this->cache->get($discoveryKey);

		if ($discovery === null) {
			$this->logger->debug('getDiscovery(): Not found in cache; Fetching discovery.xml', ['app' => $this->appName]);

			$contact_admin = $this->l10n->t('Please contact the "%s" administrator.', [$wopiRemote]);

			try {
				// If we are sending query to built-in CODE server, we avoid using IClient::get() method
				// because of an encoding issue in guzzle: https://github.com/guzzle/guzzle/issues/1758
				if (\strpos($wopiDiscovery, 'proxy.php') === false) {
					$wopiClient = \OC::$server->getHTTPClientService()->newClient();
					$discovery = $wopiClient->get($wopiDiscovery)->getBody();
				} else {
					$discovery = \file_get_contents($wopiDiscovery);
				}
			} catch (\Exception $e) {
				$error_message = $e->getMessage();

				$this->logger->error('Collabora Online: Encountered error {error}', ['app' => $this->appName, 'error' => $error_message ]);
				if (\preg_match('/^cURL error ([0-9]*):/', $error_message, $matches)) {
					$admin_check = $this->l10n->t('Please ask your administrator to check the Collabora Online server setting. The exact error message was: ') . $error_message;

					$curl_error = $matches[1];
					switch ($curl_error) {
						case '1':
							throw new ResponseException($this->l10n->t('Collabora Online: The protocol specified in "%s" is not allowed.', [$wopiRemote]), $admin_check);
						case '3':
							throw new ResponseException($this->l10n->t('Collabora Online: Malformed URL "%s".', [$wopiRemote]), $admin_check);
						case '6':
							throw new ResponseException($this->l10n->t('Collabora Online: Cannot resolve the host "%s".', [$wopiRemote]), $admin_check);
						case '7':
							throw new ResponseException($this->l10n->t('Collabora Online: Cannot connect to the host "%s".', [$wopiRemote]), $admin_check);
						case '35':
							throw new ResponseException($this->l10n->t('Collabora Online: SSL/TLS handshake failed with the host "%s".', [$wopiRemote]), $admin_check);
						case '60':
							throw new ResponseException($this->l10n->t('Collabora Online: SSL certificate is not installed.'), $this->l10n->t('Please ask your administrator to add ca-chain.cert.pem to the ca-bundle.crt, for example "cat /etc/loolwsd/ca-chain.cert.pem >> <server-installation>/resources/config/ca-bundle.crt" . The exact error message was: ') . $error_message);
					}
				}
				throw new ResponseException($this->l10n->t('Collabora Online unknown error: ') . $error_message, $contact_admin);
			}

			if (!$discovery) {
				throw new ResponseException($this->l10n->t('Collabora Online: Unable to read discovery.xml from "%s".', [$wopiRemote]), $contact_admin);
			}

			$this->logger->debug('Storing the discovery.xml under key ' . $discoveryKey . ' to the cache.', ['app' => $this->appName]);
			$this->cache->set($discoveryKey, $discovery, 3600);
		} else {
			$this->logger->debug('getDiscovery(): Found in cache', ['app' => $this->appName]);
		}

		return $discovery;
	}

	/**
	 * Prepare document structure from raw file node metadata
	 *
	 * @param array $fileInfo
	 * @return null|array
	 */
	private function prepareDocument($fileInfo) {
		$preparedDocuments = $this->prepareDocuments([$fileInfo]);

		if ($preparedDocuments['status'] === 'success' &&
			$preparedDocuments['documents'] &&
			\count($preparedDocuments['documents']) > 0) {
			return $preparedDocuments['documents'][0];
		}

		return null;
	}

	/**
	 * Prepare documents structure from raw file nodes metadata
	 *
	 * @param array $rawDocuments
	 * @return array
	 */
	private function prepareDocuments($rawDocuments) {
		$discovery_parsed = null;
		try {
			$discovery = $this->getDiscovery();

			$loadEntities = \libxml_disable_entity_loader(true);
			$discovery_parsed = \simplexml_load_string($discovery);
			\libxml_disable_entity_loader($loadEntities);

			if ($discovery_parsed === false) {
				$this->cache->remove('discovery.xml');
				$wopiRemote = $this->getWopiUrl($this->isTester());

				return [
					'status' => 'error',
					'message' => $this->l10n->t('Collabora Online: discovery.xml from "%s" is not a well-formed XML string.', [$wopiRemote]),
					'hint' => $this->l10n->t('Please contact the "%s" administrator.', [$wopiRemote])
				];
			}
		} catch (ResponseException $e) {
			return [
				'status' => 'error',
				'message' => $e->getMessage(),
				'hint' => $e->getHint()
			];
		}

		$fileIds = [];
		$documents = [];
		$lolang = \strtolower(\str_replace('_', '-', $this->settings->getUserValue($this->uid, 'core', 'lang', 'en')));
		foreach ($rawDocuments as $key=>$document) {
			if (\is_object($document)) {
				$documents[] = $document->getData();
			} else {
				$documents[$key] = $document;
			}

			$documents[$key]['icon'] = \preg_replace('/\.png$/', '.svg', \OCP\Template::mimetype_icon($document['mimetype']));
			$documents[$key]['hasPreview'] = \OC::$server->getPreviewManager()->isMimeSupported($document['mimetype']);
			$ret = $this->getWopiSrcUrl($discovery_parsed, $document['mimetype']);
			$documents[$key]['urlsrc'] = $ret['urlsrc'];
			$documents[$key]['action'] = $ret['action'];
			$documents[$key]['lolang'] = $lolang;
			$fileIds[] = $document['fileid'];
		}

		\usort($documents, function ($a, $b) {
			return @$b['mtime']-@$a['mtime'];
		});

		return [
			'status' => 'success', 'documents' => $documents
		];
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
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index($fileId, $dir) {
		// If type of fileId is a string, then it
		// doesn't work for shared documents, lets cast to int everytime
		$fileId = (int)$fileId;

		// Normal editing and user/group share editing
		// Parameter $dir is not used during indexing, but might be used by Document Server
		return $this->handleIndex($fileId, $dir, null, 'user');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function publicIndex($fileId, $shareToken) {
		// If type of fileId is a string, then it
		// doesn't work for shared documents, lets cast to int everytime
		$fileId = (int)$fileId;

		// Public share link (folder or file)
		return $this->handleIndex($fileId, null, $shareToken, 'base');
	}

	/**
	 * Get collabora document template for:
	 * - the base template if both fileId and shareToken are null
	 * - file in user folder (also shared by user/group) if fileId not null and shareToken is null
	 * - public link (public file share or file in public folder share identified by fileId) if shareToken is not null
	 *
	 * @param int|null $fileId
	 * @param string|null $dir
	 * @param string|null $shareToken
	 * @param string $renderAs the template layout to be used
	 * @return TemplateResponse
	 */
	private function handleIndex(?int $fileId, ?string $dir, ?string $shareToken, string $renderAs) : TemplateResponse {
		// Handle general response
		$wopiRemote = $this->getWopiUrl($this->isTester());
		if (($parts = \parse_url($wopiRemote)) && isset($parts['scheme'], $parts['host'])) {
			$webSocketProtocol = "ws://";
			if ($parts['scheme'] == "https") {
				$webSocketProtocol = "wss://";
			}
			$webSocket = \sprintf(
				"%s%s%s",
				$webSocketProtocol,
				$parts['host'],
				isset($parts['port']) ? ":" . $parts['port'] : ""
			);
		} else {
			return $this->responseError($this->l10n->t('Collabora Online: Invalid URL "%s".', [$wopiRemote]), $this->l10n->t('Please ask your administrator to check the Collabora Online server setting.'));
		}

		\OC::$server->getNavigationManager()->setActiveEntry('richdocuments_index');
		$retVal = [
			'enable_previews' => $this->settings->getSystemValue('enable_previews', true),
			'wopi_url' => $webSocket,
			'doc_format' => $this->appConfig->getAppValue('doc_format'),
			'instanceId' => $this->settings->getSystemValue('instanceid'),
			'canonical_webroot' => $this->appConfig->getAppValue('canonical_webroot'),
			'show_custom_header' => $renderAs === 'base'  // public link should show a customer header without buttons
		];

		// Get doc index if possible
		try {
			$docRetVal = $this->handleDocIndex($fileId, $dir, $shareToken);
		} catch (\Exception $e) {
			return $this->responseError($this->l10n->t('Collabora Online: Cannot open document.'), $e->getMessage());
		}
		$retVal = \array_merge($retVal, $docRetVal);

		$response = new TemplateResponse('richdocuments', 'documents', $retVal, $renderAs);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain($this->domainOnly($wopiRemote));
		$policy->allowInlineScript(true);
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Get document metadata for:
	 * - file in user folder if fileId and currently authenticated user are specified, and shareToken is null
	 * - public link (public file share or file in public folder share identified by fileId) if shareToken is not null
	 *
	 * @param int|null $fileId
	 * @param string|null $dir
	 * @param string|null $shareToken
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function handleDocIndex(?int $fileId, ?string $dir, ?string $shareToken) : array {
		if ($fileId === null && $shareToken === null) {
			return [];
		}

		$useUserAuth = ($fileId !== null && $shareToken === null);
		if ($useUserAuth) {
			// Normal editing or share by user/group
			$doc = $this->getDocumentByUserAuth($this->uid, $fileId, $dir);
		} else {
			// Share by link in public folder or file
			$doc = $this->getDocumentByShareToken($shareToken, $fileId);
		}

		if ($doc == null) {
			$this->logger->warning("Null returned for document with fileid {fileid}", ["fileid" => $fileId]);
			return [];
		}

		// Update permissions
		$permissions = $doc['permissions'];
		if (!($doc['action'] === 'edit') && !($doc['action'] === 'view_comment')) {
			$permissions = $permissions & ~\OCP\Constants::PERMISSION_UPDATE;
		}

		// Get wopi token and decide max upload size
		if ($useUserAuth) {
			// Restrict filesize not possible when using public share
			$maxUploadFilesize = \OCP\Util::maxUploadFilesize("/");
		} else {
			// FIXME: In public links allow max 100MB
			$maxUploadFilesize = 100000000;
		}

		// Get wopi token and decide max upload size
		if ($useUserAuth) {
			$wopiInfo = $this->getWopiInfoForAuthUser($doc);
		} else {
			$wopiInfo = $this->getWopiInfoForPublicLink($doc);
		}

		// Create document index
		$docIndex = [
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

		return $docIndex;
	}

	/**
	 * Accessed from external-apps such as new owncloud web front-end
	 * It returns the information to load the document from the fileId.
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getDocumentIndex($fileId) {
		try {
			// If type of fileId is a string, then it
			// doesn't work for shared documents, lets cast to int everytime
			$fileId = (int)$fileId;
			
			$docRetVal = $this->handleDocIndex($fileId, null, null);
			$docRetVal["locale"] = \strtolower(\str_replace('_', '-', $this->settings->getUserValue($this->uid, 'core', 'lang', 'en')));
		} catch (\Exception $e) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Document index could not be found'
			], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse($docRetVal);
	}

	/**
	 * @NoAdminRequired
	 */
	public function create() {
		$mimetype = $this->request->getParam('mimetype');
		$filename = $this->request->getParam('filename');
		$dir = $this->request->getParam('dir');

		$view = new View('/' . $this->uid . '/files');

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

		$discovery_parsed = null;
		try {
			$discovery = $this->getDiscovery();

			$loadEntities = \libxml_disable_entity_loader(true);
			$discovery_parsed = \simplexml_load_string($discovery);
			\libxml_disable_entity_loader($loadEntities);

			if ($discovery_parsed === false) {
				$this->cache->remove('discovery.xml');
				$wopiRemote = $this->getWopiUrl($this->isTester());

				return [
					'status' => 'error',
					'message' => $this->l10n->t('Collabora Online: discovery.xml from "%s" is not a well-formed XML string.', [$wopiRemote]),
					'hint' => $this->l10n->t('Please contact the "%s" administrator.', [$wopiRemote])
				];
			}
		} catch (ResponseException $e) {
			return [
				'status' => 'error',
				'message' => $e->getMessage(),
				'hint' => $e->getHint()
			];
		}

		if ($content && $view->file_put_contents($path, $content)) {
			$info = $view->getFileInfo($path);
			$ret = $this->getWopiSrcUrl($discovery_parsed, $mimetype);
			$lolang = \strtolower(\str_replace('_', '-', $this->settings->getUserValue($this->uid, 'core', 'lang', 'en')));
			$response =  [
				'status' => 'success',
				'fileid' => $info['fileid'],
				'urlsrc' => $ret['urlsrc'],
				'action' => $ret['action'],
				'lolang' => $lolang,
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
	private function getWopiInfoForAuthUser(array $docInfo) : array {
		$currentUser = $this->uid;
		$ownerUid = $docInfo['owner'];
		$updatable = $docInfo['updateable'];
		$fileId = $docInfo['fileid'];
		$path = $docInfo['path'];
		$version = $docInfo['version'];
		$permissions = $docInfo['permissions'];

		$this->logger->info('getWopiInfoForAuthUser(): Generating WOPI Token for file {fileId}, version {version}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version ]);

		$view = \OC\Files\Filesystem::getView();

		// If token is for some versioned file, then it is not updatable
		if ($version !== '0') {
			$updatable = false;
		}
		if ($updatable) {
			// Check if is allowed editor
			$updatable = $this->isAllowedEditor($currentUser);
		}

		// default shared session id
		$sessionid = '0';

		// get file info and storage
		$info = $view->getFileInfo($path);
		$storage = $info->getStorage();

		// check if secure mode feature has been enabled for share/file
		$secureModeEnabled = $this->appConfig->secureViewOptionEnabled();
		$isSharedFile = $storage->instanceOfStorage('\OCA\Files_Sharing\SharedStorage');
		$enforceSecureView = \filter_var($this->request->getParam('enforceSecureView', false), FILTER_VALIDATE_BOOLEAN);
		if ($secureModeEnabled) {
			if ($isSharedFile) {
				// handle shares
				/** @var \OCA\Files_Sharing\SharedStorage $storage */
				/* @phan-suppress-next-line PhanUndeclaredMethod */
				$share = $storage->getShare();
				$canDownload = $share->getAttributes()->getAttribute('permissions', 'download');
				$viewWithWatermark = $share->getAttributes()->getAttribute('richdocuments', 'view-with-watermark');
				$canPrint = $share->getAttributes()->getAttribute('richdocuments', 'print');
				// if view with watermark enforce user-private secure session with dedicated sessionid
				$sessionid = $viewWithWatermark === true ? $share->getId() : '0';
			} else {
				// handle files
				$canDownload = true;
				$viewWithWatermark = false;
				$canPrint = true;
			}
			
			if ($enforceSecureView) {
				// handle enforced secure view watermark
				// but preserve other permissions like print/download/edit
				$viewWithWatermark = true;
			}

			// restriction on view has been set to false, return forbidden
			// as there is no supported way of opening this document
			if ($canDownload === false && $viewWithWatermark === false) {
				throw new \Exception($this->l10n->t('Insufficient file permissions.'));
			}

			$attributes = WOPI::ATTR_CAN_VIEW;

			// can export file in editor if download is not set or true
			if ($canDownload === null || $canDownload === true) {
				$attributes = $attributes | WOPI::ATTR_CAN_EXPORT;
			}

			// can print from editor if print is not set or true
			if ($canPrint === null || $canPrint === true) {
				$attributes = $attributes | WOPI::ATTR_CAN_PRINT;
			}

			// restriction on view with watermarking enabled
			if ($viewWithWatermark === true) {
				$attributes = $attributes | WOPI::ATTR_HAS_WATERMARK;
			}
		} else {
			$attributes = WOPI::ATTR_CAN_VIEW | WOPI::ATTR_CAN_EXPORT | WOPI::ATTR_CAN_PRINT;
		}

		if ($updatable) {
			$attributes = $attributes | WOPI::ATTR_CAN_UPDATE;
		}

		$this->logger->debug('getWopiInfoForAuthUser(): File {fileid} is updatable? {updatable}', [
			'app' => $this->appName,
			'fileid' => $fileId,
			'updatable' => $updatable ]);
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
		$tokenArray = $row->generateToken($fileId, $version, $attributes, $serverHost, $ownerUid, $currentUser);

		// Return the token.
		$result = [
			'status' => 'success',
			'access_token' => $tokenArray['access_token'],
			'access_token_ttl' => $tokenArray['access_token_ttl'],
			'sessionid' => $sessionid
		];
		$this->logger->debug('getWopiInfoForAuthUser(): Issued token: {result}', ['app' => $this->appName, 'result' => $result]);
		return $result;
	}

	/**
	 * @param string $userId
	 * @param int $fileId
	 * @param string|null $dir
	 * @return array|null
	 */
	private function getDocumentByUserAuth($userId, $fileId, $dir) {
		if ($fileInfo = $this->documentService->getDocumentByUserId($userId, $fileId, $dir)) {
			return $this->prepareDocument($fileInfo);
		}
		return null;
	}

	/**
	 * @param string $token
	 * @param int|null $fileId
	 * @return array|null
	 */
	private function getDocumentByShareToken(string $token, ?int $fileId) : ?array {
		if ($fileInfo = $this->documentService->getDocumentByShareToken($token, $fileId)) {
			return $this->prepareDocument($fileInfo);
		}
		return null;
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
	 * Generates and returns an wopi access info containing token for a given fileId.
	 *
	 * @param array $docInfo doc index as retrieved from DocumentService
	 * @return array wopi access info
	 */
	private function getWopiInfoForPublicLink(array $docInfo) : array {
		$currentUser = $this->uid;
		$ownerUid = $docInfo['owner'];
		$fileId = $docInfo['fileid'];
		$path = $docInfo['path'];
		$version = $docInfo['version'];
		$permissions = $docInfo['permissions'];

		$this->logger->info('getWopiInfoForPublicLink(): Generating WOPI Token for file {fileId}, version {version}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version ]);

		$this->updateDocumentEncryptionAccessList($ownerUid, $currentUser, $path);

		$updateable = ($permissions & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE;
		// If token is for some versioned file
		if ($version !== '0') {
			$updateable = false;
		}

		$serverHost = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();

		$attributes = WOPI::ATTR_CAN_VIEW | WOPI::ATTR_CAN_EXPORT | WOPI::ATTR_CAN_PRINT;
		if ($updateable) {
			$attributes = $attributes | WOPI::ATTR_CAN_UPDATE;
		}

		$row = new Db\Wopi();
		/*
		 * Version is a string here, and arg 2 (version) should be an int.
		 * As long as the string is just a number, all is good.
		 */
		/* @phan-suppress-next-line PhanTypeMismatchArgument */
		$tokenArray = $row->generateToken($fileId, $version, $attributes, $serverHost, $ownerUid, $currentUser);

		// Return the token.
		$result = [
			'status' => 'success',
			'access_token' => $tokenArray['access_token'],
			'access_token_ttl' => $tokenArray['access_token_ttl'],
			'sessionid' => '0' // default shared session
		];
		$this->logger->debug('getWopiInfoForPublicLink(): Issued token: {result}', ['app' => $this->appName, 'result' => $result]);
		return $result;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * Generates and returns an access token and urlsrc for a given fileId
	 * for requests that provide secret token set in app settings
	 */
	public function extAppWopiGetData($documentId) {
		list($fileId, , $version, ) = Helper::parseDocumentId($documentId);

		// If type of fileId is a string, then it
		// doesn't work for shared documents, lets cast to int everytime
		$fileId = (int)$fileId;

		$secretToken = $this->request->getParam('secret_token');
		$apps = \array_filter(\explode(',', $this->appConfig->getAppValue('external_apps')));
		foreach ($apps as $app) {
			if ($app !== '') {
				if ($secretToken === $app) {
					$appName = \explode(':', $app);
					$this->logger->info('extAppWopiGetData(): External app "{extApp}" authenticated; issuing access token for fileId {fileId}', [
						'app' => $this->appName,
						'extApp' => $appName[0],
						'fileId' => $fileId
					]);

					$retArray = [];
					if ($doc = $this->getDocumentByUserAuth($this->uid, $fileId, null)) {
						$retArray = $this->getWopiInfoForAuthUser($doc);
						$retArray['urlsrc'] = $doc['urlsrc'];
					}

					return $retArray;
				}
			}
		}

		return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
	}

	/**
	 * @NoAdminRequired
	 * lists the documents the user has access to (including shared files, once the code in core has been fixed)
	 * also adds session and member info for these files
	 */
	public function listAll() {
		return $this->prepareDocuments($this->documentService->getDocuments());
	}
}
