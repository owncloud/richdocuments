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
use OCP\Files\File;
use OCP\IGroupManager;
use OCP\Files\NotPermittedException;
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
use \OCA\Richdocuments\Storage;
use \OCA\Richdocuments\Http\DownloadResponse;
use \OCA\Richdocuments\Http\ResponseException;
use OCP\IUserManager;

use Symfony\Component\EventDispatcher\GenericEvent;

class DocumentController extends Controller {
	private $uid;
	private $l10n;
	private $settings;
	private $appConfig;
	private $cache;
	private $logger;
	private $storage;
	private $appManager;
	/**
	 * @var IGroupManager
	 */
	private $groupManager;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	const ODT_TEMPLATE_PATH = '/assets/odttemplate.odt';

	// Signifies LOOL that document has been changed externally in this storage
	const LOOL_STATUS_DOC_CHANGED = 1010;

	public function __construct($appName,
								IRequest $request,
								IConfig $settings,
								AppConfig $appConfig,
								IL10N $l10n,
								$uid,
								ICacheFactory $cache,
								ILogger $logger,
								Storage $storage,
								IAppManager $appManager,
								IGroupManager $groupManager,
								IUserManager $userManager) {
		parent::__construct($appName, $request);
		$this->uid = $uid;
		$this->l10n = $l10n;
		$this->settings = $settings;
		$this->appConfig = $appConfig;
		$this->cache = $cache->create($appName);
		$this->logger = $logger;
		$this->storage = $storage;
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
		// Normal editing and user/group share editing
		// Parameter $dir is not used during indexing, but might be used by Document Server
		return $this->handleIndex($fileId, null, 'user');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function publicIndex($fileId, $shareToken) {
		// Public share link (folder or file)
		return $this->handleIndex($fileId, $shareToken, 'base');
	}

	/**
	 * Get collabora document template for:
	 * - the base template if both fileId and shareToken are null
	 * - file in user folder (also shared by user/group) if fileId not null and shareToken is null
	 * - public link (public file share or file in public folder share identified by fileId) if shareToken is not null
	 *
	 * @param string|int|null $fileId
	 * @param string|null $shareToken
	 * @param string $renderAs the template layout to be used
	 * @return TemplateResponse
	 */
	private function handleIndex($fileId, $shareToken, $renderAs) {
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
				isset($parts['port']) ? ":" . $parts['port'] : "");
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
			$docRetVal = $this->handleDocIndex($fileId, $shareToken, $this->uid);
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
	 * @param string|int|null $fileId
	 * @param string|null $shareToken
	 * @param string|null $currentUser
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function handleDocIndex($fileId, $shareToken, $currentUser) {
		if ($fileId === null && $shareToken === null) {
			return [];
		}

		$useUserAuth = ($fileId !== null && $shareToken === null);
		if ($useUserAuth) {
			// Normal editing or share by user/group
			$doc = $this->getDocumentByUserAuth($currentUser, $fileId);
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
			$wopiInfo = $this->getWopiInfoForAuthUser($doc['fileid'], $doc['version'], $this->uid);
		} else {
			$wopiInfo = $this->getWopiInfoForPublicLink($doc['fileid'], $doc['version'], $doc['path'], $permissions, $currentUser, $doc['owner']);
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

	private function getOwner($fileId) {
		$view = \OC\Files\Filesystem::getView();
		$path = $view->getPath($fileId);
		return $view->getOwner($path);
	}

	/**
	 * Generates and returns an access token for a given fileId.
	 *
	 * @throws \Exception
	 */
	private function getWopiInfoForAuthUser($fileId, $version, $currentUser) {
		$this->logger->info('getWopiInfoForAuthUser(): Generating WOPI Token for file {fileId}, version {version}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version ]);

		$view = \OC\Files\Filesystem::getView();
		$path = $view->getPath($fileId);

		// If token is for some versioned file
		$updatable = (bool)$view->isUpdatable($path);
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
		$secureModeEnabled = \OC::$server->getConfig()->getAppValue('richdocuments', 'secure_view_option') === 'true';
		$isSharedFile = $info->getStorage()->instanceOfStorage('\OCA\Files_Sharing\SharedStorage');
		$enforceSecureView = \filter_var($this->request->getParam('enforceSecureView', false), FILTER_VALIDATE_BOOLEAN);
		if ($secureModeEnabled) {
			if ($isSharedFile) {
				// handle shares
				/** @var \OCA\Files_Sharing\SharedStorage $storage */
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
		$serverHost = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();

		$owner = $this->getOwner($fileId);
		$this->updateDocumentEncryptionAccessList($owner, $currentUser, $path);

		$row = new Db\Wopi();
		$tokenArray = $row->generateToken($fileId, $version, $attributes, $serverHost, $owner, $currentUser);

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
	 * @param string|int $fileId
	 * @return null|array
	 */
	private function getDocumentByUserAuth($userId, $fileId) {
		if ($fileInfo = $this->storage->getDocumentByUserId($userId, $fileId)) {
			return $this->prepareDocument($fileInfo);
		}
		return null;
	}

	/**
	 * @param string $token
	 * @param string|int $fileId
	 * @return null|array
	 */
	private function getDocumentByShareToken($token, $fileId = null) {
		if ($fileInfo = $this->storage->getDocumentByShareToken($token, $fileId)) {
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
	 * Generates and returns an access token for a given fileId.
	 */
	private function getWopiInfoForPublicLink($fileId, $version, $path, $permissions, $currentUser, $ownerUid) {
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
					if ($doc = $this->getDocumentByUserAuth($this->uid, $fileId)) {
						$retArray = $this->getWopiInfoForAuthUser($fileId, $version, $this->uid);
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
	 * @NoCSRFRequired
	 * @PublicPage
	 * Returns general info about a file.
	 */
	public function wopiCheckFileInfo($documentId) {
		$token = $this->request->getParam('access_token');

		list($fileId, , $version, $sessionId) = Helper::parseDocumentId($documentId);
		$this->logger->info('wopiCheckFileInfo(): Getting info about file {fileId}, version {version} by token {token}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version,
			'token' => $token ]);

		$row = new Db\Wopi();
		$row->loadBy('token', $token);

		$res = $row->getWopiForToken($token);
		if ($res == false) {
			$this->logger->debug('wopiCheckFileInfo(): getWopiForToken() failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// make sure file can be read when checking file info
		$file = $this->getFileHandle($fileId, $res['owner'], $res['editor']);
		if (!$file) {
			$this->logger->error('wopiCheckFileInfo(): Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// trigger read operation while checking file info for user
		// after acquiring the token
		try {
			$file->fopen('rb');
		} catch (NotPermittedException $e) {
			$this->logger->error('wopiCheckFileInfo(): Could not open file - {error}', ['app' => $this->appName, 'error' => $e->getMessage()]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			$this->logger->error('wopiCheckFileInfo(): Unexpected Exception - {error}', ['app' => $this->appName, 'error' => $e->getMessage()]);
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($res['editor'] && $res['editor'] != '') {
			$editor = $this->userManager->get($res['editor']);
			$editorId = $editor->getUID();
			$editorDisplayName = $editor->getDisplayName();
			$editorEmail = $editor->getEMailAddress();
		} else {
			$editorId = $this->l10n->t('remote user');
			$editorDisplayName = $this->l10n->t('remote user');
			$editorEmail = null;
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		$result = [
			'BaseFileName' => $file->getName(),
			'Size' => $file->getSize(),
			'Version' => $version,
			'OwnerId' => $res['owner'],
			'UserId' => $editorId,
			'UserFriendlyName' => $editorDisplayName,
			'UserCanWrite' => $canWrite,
			'UserCanNotWriteRelative' => $this->appConfig->encryptionEnabled(),
			'PostMessageOrigin' => $res['server_host'],
			'LastModifiedTime' => Helper::toISO8601($file->getMTime())
		];

		$canExport = $res['attributes'] & WOPI::ATTR_CAN_EXPORT;
		$hasWatermark = $res['attributes'] & WOPI::ATTR_HAS_WATERMARK;

		if (!$canExport) {
			$result = \array_merge($result, [
				'DisableExport' => true,
				'HideExportOption' => true,
				'HideSaveOption' => true, // dont show the §save to OC§ option as user cannot download file
				'DisableCopy' => true, // disallow copying in document
			]);
		}

		if ($hasWatermark) {
			$watermark = \str_replace(
				'{viewer-email}',
				$editorEmail === null ? $editorDisplayName : $editorEmail,
				\OC::$server->getConfig()->getAppValue('richdocuments', 'watermark_text', '')
			);
			$result = \array_merge($result, [
				'WatermarkText' => $watermark,
			]);
		}

		$canPrint = $res['attributes'] & WOPI::ATTR_CAN_PRINT;
		if (!$canPrint) {
			$result = \array_merge($result, [
				'DisablePrint' => true,
				'HidePrintOption' => true,
			]);
		}

		$this->logger->debug("wopiCheckFileInfo(): Result: {result}", ['app' => $this->appName, 'result' => $result]);
		return $result;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * Given a request access token and a document id, returns the contents of the file.
	 * Expects a valid token in access_token parameter.
	 */
	public function wopiGetFile($documentId) {
		$token = $this->request->getParam('access_token');

		list($fileId, , $version, ) = Helper::parseDocumentId($documentId);
		$this->logger->info('wopiGetFile(): File {fileId}, version {version}, token {token}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version,
			'token' => $token ]);

		$row = new Db\Wopi();
		$row->loadBy('token', $token);

		//TODO: Support X-WOPIMaxExpectedSize header.
		$res = $row->getWopiForToken($token);
		if ($res == false) {
			$this->logger->debug('wopiGetFile(): getWopiForToken() failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$file = $this->getFileHandle($fileId, $res['owner'], $res['editor']);
		if (!$file) {
			$this->logger->warning('wopiGetFile(): Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DownloadResponse($this->request, $file);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * Given a request access token and a document id, replaces the files with the request body.
	 * Expects a valid token in access_token parameter.
	 */
	public function wopiPutFile($documentId) {
		$token = $this->request->getParam('access_token');

		$isPutRelative = ($this->request->getHeader('X-WOPI-Override') === 'PUT_RELATIVE');

		list($fileId, , $version, ) = Helper::parseDocumentId($documentId);
		$this->logger->debug('wopiputFile(): File {fileId}, version {version}, token {token}, WopiOverride {wopiOverride}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'version' => $version,
			'token' => $token,
			'wopiOverride' => $this->request->getHeader('X-WOPI-Override')]);

		$row = new Db\Wopi();
		$row->loadBy('token', $token);

		$res = $row->getWopiForToken($token);
		if ($res == false) {
			$this->logger->debug('wopiPutFile(): getWopiForToken() failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$canWrite = $res['attributes'] & WOPI::ATTR_CAN_UPDATE;
		if (!$canWrite) {
			$this->logger->debug('wopiPutFile(): getWopiForToken() failed.', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($isPutRelative) {
			// Retrieve suggested target
			$suggested = $this->request->getHeader('X-WOPI-SuggestedTarget');
			$suggested = \iconv('utf-7', 'utf-8', $suggested);

			return $this->putRelative($fileId, $res['owner'], $res['editor'], $suggested);
		} else {
			// Retrieve wopi timestamp header
			$wopiHeaderTime = $this->request->getHeader('X-LOOL-WOPI-Timestamp');
			$this->logger->debug('wopiPutFile(): WOPI header timestamp: {wopiHeaderTime}', [
				'app' => $this->appName,
				'wopiHeaderTime' => $wopiHeaderTime
			]);

			return $this->put($fileId, $res['owner'], $res['editor'], $wopiHeaderTime);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * Given a request access token and a document, replaces the files with the request body.
	 * Expects a valid token in access_token parameter.
	 * Just actually routes to the PutFile, the implementation of PutFile
	 * handles both saving and saving as.
	 */
	public function wopiPutRelativeFile($documentId) {
		return $this->wopiPutFile($documentId);
	}

	/**
	 * @NoAdminRequired
	 * lists the documents the user has access to (including shared files, once the code in core has been fixed)
	 * also adds session and member info for these files
	 */
	public function listAll() {
		return $this->prepareDocuments($this->storage->getDocuments());
	}

	/**
	 * Privileged put to original (owner) file as editor
	 * for given fileId
	 *
	 * @param int $fileId
	 * @param string $owner
	 * @param string $editor
	 * @param string $wopiHeaderTime
	 * @return JSONResponse
	 */
	private function put($fileId, $owner, $editor, $wopiHeaderTime) {
		$file = $this->getFileHandle($fileId, $owner, $editor);
		if (!$file) {
			$this->logger->warning('wopiPutFile(): Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// Handle wopiHeaderTime
		if (!$wopiHeaderTime) {
			$this->logger->debug('wopiPutFile(): X-LOOL-WOPI-Timestamp absent. Saving file.', ['app' => $this->appName]);
		} elseif ($wopiHeaderTime != Helper::toISO8601($file->getMTime())) {
			$this->logger->debug('wopiPutFile(): Document timestamp mismatch ! WOPI client says mtime {headerTime} but storage says {storageTime}', [
				'app' => $this->appName,
				'headerTime' => $wopiHeaderTime,
				'storageTime' => Helper::toISO8601($file->getMtime())
			]);
			// Tell WOPI client about this conflict.
			return new JSONResponse(['LOOLStatusCode' => self::LOOL_STATUS_DOC_CHANGED], Http::STATUS_CONFLICT);
		}

		// Read the contents of the file from the POST body and store.
		$content = \fopen('php://input', 'r');
		$this->logger->debug('wopiPutFile(): Storing file {fileId}, editor: {editor}, owner: {owner}.', [
				'app' => $this->appName,
				'fileId' => $fileId,
				'editor' => $editor,
				'owner' => $owner]
		);
		$file->putContent($content);

		$this->logger->debug('wopiPutFile(): mtime', ['app' => $this->appName]);

		$mtime = $file->getMtime();

		return new JSONResponse([
			'status' => 'success',
			'LastModifiedTime' => Helper::toISO8601($mtime)
		], Http::STATUS_OK);
	}

	/**
	 * Privileged put relative to original (owner) file as editor
	 * for given fileId
	 *
	 * @param int $fileId
	 * @param string $owner
	 * @param string $editor
	 * @param string $suggested
	 *
	 * @return JSONResponse
	 */
	private function putRelative($fileId, $owner, $editor, $suggested) {
		$file = $this->getFileHandle($fileId, $owner, $editor);
		if (!$file) {
			$this->logger->warning('wopiPutFile(): Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$root = \OC::$server->getRootFolder();

		$path = '';
		if ($suggested[0] === '.') {
			$path = \dirname($file->getPath()) . '/New File' . $suggested;
		} elseif ($suggested[0] !== '/') {
			$path = \dirname($file->getPath()) . '/' . $suggested;
		} else {
			$path = $root->getUserFolder($editor)->getPath() . $suggested;
		}

		if ($path === '') {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Cannot create the file'
			], Http::STATUS_BAD_REQUEST);
		}

		// create the folder first
		if (!$root->nodeExists(\dirname($path))) {
			$root->newFolder(\dirname($path));
		}

		// create a unique new file
		$path = $root->getNonExistingName($path);
		$file = $root->newFile($path);
		$file = $this->getFileHandle($file->getId(), $owner, $editor);
		if (!$file) {
			$this->logger->warning('wopiCheckFileInfo(): Could not retrieve file', ['app' => $this->appName]);
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// Read the contents of the file from the POST body and store.
		$content = \fopen('php://input', 'r');
		$this->logger->debug('wopiPutFile(): Storing file {fileId}, editor: {editor}, owner: {owner}.', [
			'app' => $this->appName,
			'fileId' => $fileId,
			'editor' => $editor,
			'owner' => $owner]
		);

		$file->putContent($content);
		$mtime = $file->getMtime();

		// generate a token for the new file
		$row = new Wopi();
		$serverHost = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();

		// Continue editing
		$attributes = WOPI::ATTR_CAN_VIEW | WOPI::ATTR_CAN_UPDATE | WOPI::ATTR_CAN_PRINT;
		$tokenArray = $row->generateToken($file->getId(), 0, $attributes, $serverHost, $owner, $editor);

		$wopi = 'index.php/apps/richdocuments/wopi/files/' . $file->getId() . '_' . $this->settings->getSystemValue('instanceid') . '?access_token=' . $tokenArray['access_token'];
		$url = \OC::$server->getURLGenerator()->getAbsoluteURL($wopi);

		return new JSONResponse([ 'Name' => $file->getName(), 'Url' => $url ], Http::STATUS_OK);
	}

	/**
	 * Get privileged access to original (owner) file handle as editor
	 * for given fileId
	 *
	 * @param int $fileId
	 * @param string $owner
	 * @param string $editor
	 *
	 * @return null|\OCP\Files\File
	 */
	private function getFileHandle($fileId, $owner, $editor) {
		if ($editor && $editor != '') {
			$user = $this->userManager->get($editor);
			if (!$user) {
				$this->logger->warning('wopiPutFile(): No such user', ['app' => $this->appName]);
				return null;
			}

			// Make sure editor session is opened for registering activity over file handle
			$this->logger->debug('wopiPutFile(): Register session as ' . $editor, ['app' => $this->appName]);
			if (!$this->appConfig->encryptionEnabled()) {
				// Set session for a user
				\OC::$server->getUserSession()->setUser($user);
			} elseif ($this->appConfig->masterEncryptionEnabled()) {
				// With master encryption, decryption is based on master key (no user password needed)
				// make sure audit/activity is triggered for editor session
				\OC::$server->getUserSession()->setUser($user);

				// emit login event to allow decryption of files via master key
				$afterEvent = new GenericEvent(null, ['loginType' => 'password', 'user' => $user, 'uid' => $user->getUID(), 'password' => '']);

				/** @phpstan-ignore-next-line */
				\OC::$server->getEventDispatcher()->dispatch($afterEvent, 'user.afterlogin');
			} else {
				// other type of encryption is enabled (e.g. user-key) that does not allow to decrypt files without incognito access to files
				\OC_User::setIncognitoMode(true);
			}
		} else {
			// Public link access
			\OC_User::setIncognitoMode(true);
		}

		// Setup FS of original file file-handle to be able to generate
		// file versions and write files with user session set for editor
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($owner);
		$userFolder = \OC::$server->getRootFolder()->getUserFolder($owner);
		$files = $userFolder->getById($fileId);
		if ($files !== [] && $files[0] instanceof File) {
			return $files[0];
		}
		return null;
	}
}
