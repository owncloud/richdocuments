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
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Http\JSONResponse;

use \OCA\Richdocuments\Db;
use \OCA\Richdocuments\File;
use \OCA\Richdocuments\Helper;
use OCA\Richdocuments\Filter;
use \OC\Files\View;

class BadRequestException extends \Exception {

	protected $body = "";

	public function setBody($body){
		$this->body = $body;
	}

	public function getBody(){
		return $this->body;
	}
}

class SessionController extends Controller{

	protected $uid;
	protected $logger;
	protected $shareToken;

	public function __construct($appName, IRequest $request, $logger, $uid){
		parent::__construct($appName, $request);
		$this->uid = $uid;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 */
	public function join($fileId){
		try {
			$view = \OC\Files\Filesystem::getView();
			$path = $view->getPath($fileId);

			$file = new File($fileId);
			$response = Db\Session::start($this->uid, $file);

			$response = array_merge(
					$response,
					[ 'status'=>'success' ]
			);
		} catch (\Exception $e){
			$this->logger->warning('Starting a session failed. Reason: ' . $e->getMessage(), [ 'app' => $this->appName ]);
			$response = [ 'status'=>'error' ];
		}

		return $response;
	}

	protected function validateSession($session){
		try {
			if (is_null($this->shareToken)) {
				new File($session->getFileId());
			} else {
				File::getByShareToken($this->shareToken);
			}
		} catch (\Exception $e){
			$this->logger->warning('Error. Session no longer exists. ' . $e->getMessage(), [ 'app' => $this->appName ]);
			$ex = new BadRequestException();
			$ex->setBody(
					implode(',', $this->request->getParams())
			);
			throw $ex;
		}
	}

	protected function loadSession($esId){
		if (!$esId){
			throw new \Exception('Session id can not be empty');
		}

		$session = new Db\Session();
		$session->load($esId);
		if (!$session->getEsId()){
			throw new \Exception('Session does not exist');
		}
		return $session;
	}

	protected function loadMember($memberId, $expectedEsId = null){
		if (!$memberId){
			throw new \Exception('Member id can not be empty');
		}
		$member = new Db\Member();
		$member->load($memberId);
		//check if member belongs to the session
		if (!is_null($expectedEsId) && $expectedEsId !== $member->getEsId()){
			throw new \Exception($memberId . ' does not belong to session ' . $expectedEsId);
		}
		return $member;
	}
}
