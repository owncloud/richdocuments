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

use \OCP\AppFramework\Controller;
use \OCP\IRequest;
use \OCP\IConfig;
use \OCP\IL10N;
use \OCP\AppFramework\Http\ContentSecurityPolicy;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\TemplateResponse;

use \OCA\Richdocuments\Db;
use \OCA\Richdocuments\Helper;
use \OCA\Richdocuments\Storage;
use \OCA\Richdocuments\Download;
use \OCA\Richdocuments\DownloadResponse;
use \OCA\Richdocuments\File;
use \OCA\Richdocuments\Genesis;
use \OC\Files\View;
use \OCP\ICacheFactory;

class DocumentController extends Controller{

	private $uid;
	private $l10n;
	private $settings;
	private $cache;

	const ODT_TEMPLATE_PATH = '/assets/odttemplate.odt';
	const CLOUDSUITE_TMP_PATH = '/documents-tmp/';

	public function __construct($appName, IRequest $request, IConfig $settings, IL10N $l10n, $uid, ICacheFactory $cache){
		parent::__construct($appName, $request);
		$this->uid = $uid;
		$this->l10n = $l10n;
		$this->settings = $settings;
		$this->cache = $cache->create($appName);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(){
		\OC::$server->getNavigationManager()->setActiveEntry( 'richdocuments_index' );
		$maxUploadFilesize = \OCP\Util::maxUploadFilesize("/");
		$response = new TemplateResponse('richdocuments', 'documents', [
			'enable_previews' => 		$this->settings->getSystemValue('enable_previews', true),
			'useUnstable' => 		$this->settings->getAppValue('richdocuments', 'unstable', 'false'),
			'savePath' => 			$this->settings->getUserValue($this->uid, 'richdocuments', 'save_path', '/'),
			'uploadMaxFilesize' =>		$maxUploadFilesize,
			'uploadMaxHumanFilesize' =>	\OCP\Util::humanFileSize($maxUploadFilesize),
			'allowShareWithLink' => 	$this->settings->getAppValue('core', 'shareapi_allow_links', 'yes'),
			'wopi_url' => 			$this->settings->getAppValue('richdocuments', 'wopi_url'),
		]);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedScriptDomain('\'self\' http://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js http://cdnjs.cloudflare.com/ajax/libs/jquery-mousewheel/3.1.12/jquery.mousewheel.min.js \'unsafe-eval\' ' . $this->settings->getAppValue('richdocuments', 'wopi_url'));
		$policy->addAllowedFrameDomain('\'self\' http://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js http://cdnjs.cloudflare.com/ajax/libs/jquery-mousewheel/3.1.12/jquery.mousewheel.min.js \'unsafe-eval\' ' . $this->settings->getAppValue('richdocuments', 'wopi_url'));
		$policy->addAllowedConnectDomain('ws://' . $_SERVER['SERVER_NAME'] . ':9980');
		$policy->addAllowedImageDomain('*');
		$policy->allowInlineScript(true);
		$policy->addAllowedFontDomain('data:');
		$response->setContentSecurityPolicy($policy);

		if(is_null($this->cache->get('discovery.xml'))) {
			// TODO GET http://domain/hosting/discovery
		}

		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(){
		$mimetype = $this->request->post['mimetype'];

		$view = new View('/' . $this->uid . '/files');
		$dir = $this->settings->getUserValue($this->uid, $this->appName, 'save_path', '/');
		if (!$view->is_dir($dir)){
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
			default:
				// to be safe
				$mimetype = 'application/vnd.oasis.opendocument.text';
				break;
		}

		$path = Helper::getNewFileName($view, $dir . '/' . $basename);

		$content = '';
		if (class_exists('\OC\Files\Type\TemplateManager')){
			$manager = \OC_Helper::getFileTemplateManager();
			$content = $manager->getTemplate($mimetype);
		}

		if (!$content){
			$content = file_get_contents(dirname(__DIR__) . self::ODT_TEMPLATE_PATH);
		}

		if ($content && $view->file_put_contents($path, $content)){
			$info = $view->getFileInfo($path);
			$response =  array(
				'status' => 'success',
				'fileid' => $info['fileid']
			);
		} else {
			$response =  array(
				'status' => 'error',
				'message' => (string) $this->l10n->t('Can\'t create document')
			);
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * Copy the file to a temporary location that is shared between the
	 * cloudsuite server part and owncloud.
	 */
	public function localLoad($fileId){
		$view = \OC\Files\Filesystem::getView();
		$path = $view->getPath($fileId);

		if (!$view->is_file($path)) {
			return array(
				'status' => 'error',
				'message' => (string) $this->l10n->t('Unable to copy document for CloudSuite access.')
			);
		}

		$filename = dirname(__DIR__) . self::CLOUDSUITE_TMP_PATH . 'ccs-' . $fileId;
		if (file_exists($filename)) {
		    return array(
		        'status' => 'success', 'filename' => $filename,
		        'basename' => basename($filename)
		    );
                }

		$content = $view->file_get_contents($path);

		file_put_contents($filename, $content);

		// set the needed attribs
		chmod($filename, 0660);
		$modified = $view->filemtime($path);
		if ($modified !== false)
			touch($filename, $modified);

		return array(
		    'status' => 'success', 'filename' => $filename,
		    'basename' => basename($filename)
		);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * Copy the file to a temporary location that is shared between the
	 * cloudsuite server part and owncloud.
	 */
	public function localSave($fileId){
		// get really just the basename for the case somebody tries to trick us
		$basename = basename($this->request->post['basename']);

		$filename = dirname(__DIR__) . self::CLOUDSUITE_TMP_PATH . $basename;

		$view = \OC\Files\Filesystem::getView();
		$path = $view->getPath($fileId);

		if (!is_file($filename) || !$view->is_file($path)) {
			return array(
				'status' => 'error',
				'message' => (string) $this->l10n->t('Unable to copy the document back from CloudSuite.')
			);
		}

		$content = file_get_contents($filename);

		$view->file_put_contents($path, $content);

		return array(
			'status' => 'success'
		);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * Remove the temporary local copy of the document.
	 */
	public function localClose($fileId){
		// get really just the basename for the case somebody tries to trick us
		$basename = basename($this->request->post['basename']);

		$filename = dirname(__DIR__) . self::CLOUDSUITE_TMP_PATH . $basename;

		// remove temp file only when all edit instances are closed
		$stat = stat($filename);
		if ($stat['nlink'] == 1){
			unlink($filename);
		}

		return array(
			'status' => 'success'
		);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * Process partial/complete file download
	 */
	public function serve($esId){
		$session = new Db\Session();
		$session->load($esId);

		$filename = $session->getGenesisUrl() ? $session->getGenesisUrl() : '';
		return new DownloadResponse($this->request, $session->getOwner(), $filename);
	}

	/**
	 * @NoAdminRequired
	 */
	public function download($path){
		if (!$path){
			$response = new JSONResponse();
			$response->setStatus(Http::STATUS_BAD_REQUEST);
			return $response;
		}

		$fullPath = '/files' . $path;
		$fileInfo = \OC\Files\Filesystem::getFileInfo($path);
		if ($fileInfo){
			if($fileInfo->getMimeType() !== \OCA\Richdocuments\Filter\Office::NATIVE_MIMETYPE){
				$file = new File($fileInfo->getId());
				$genesis = new Genesis($file);
				$fullPath = $genesis->getPath();
			}
		}
		return new DownloadResponse($this->request, $this->uid, $fullPath);
	}


	/**
	 * @NoAdminRequired
	 */
	public function rename($fileId){
		$name = $this->request->post['name'];

		$view = \OC\Files\Filesystem::getView();
		$path = $view->getPath($fileId);

		if ($name && $view->is_file($path) && $view->isUpdatable($path)) {
			$newPath = dirname($path) . '/' . $name;
			if ($view->rename($path, $newPath)) {
						return array('status' => 'success');
			}
		}
		return array(
			'status' => 'error',
			'message' => (string) $this->l10n->t('You don\'t have permission to rename this document')
		);
	}

	/**
	 * @NoAdminRequired
	 * lists the documents the user has access to (including shared files, once the code in core has been fixed)
	 * also adds session and member info for these files
	 */
	public function listAll(){
		$found = Storage::getDocuments();

		$fileIds = array();
		$documents = array();
		foreach ($found as $key=>$document) {
			if (is_object($document)){
				$documents[] = $document->getData();
			} else {
				$documents[$key] = $document;
			}
			$documents[$key]['icon'] = preg_replace('/\.png$/', '.svg', \OCP\Template::mimetype_icon($document['mimetype']));
			$documents[$key]['hasPreview'] = \OC::$server->getPreviewManager()->isMimeSupported($document['mimetype']);
			$fileIds[] = $document['fileid'];
		}

		usort($documents, function($a, $b){
			return @$b['mtime']-@$a['mtime'];
		});

		$session = new Db\Session();
		$sessions = $session->getCollectionBy('file_id', $fileIds);

		$members = array();
		$member = new Db\Member();
		foreach ($sessions as $session) {
			$members[$session['es_id']] = $member->getActiveCollection($session['es_id']);
		}

		return array(
			'status' => 'success', 'documents' => $documents,'sessions' => $sessions,'members' => $members
		);
	}
}
