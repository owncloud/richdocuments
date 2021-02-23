<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Szymon Kłos
 * @copyright 2021 Szymon Kłos szymon.klos@collabora.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments\Controller;

use \OCA\Richdocuments\Db\Wopi;
use \OCP\AppFramework\OCSController;
use \OCP\AppFramework\Http\DataResponse;
use \OCP\IConfig;
use \OCP\IRequest;

class FederationController extends OCSController {
	private $config;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
	}

	/**
	* @PublicPage
	* @NoCSRFRequired
	*/
	public function getWopiUrl() {
		$data = ['wopi_url' => $this->config->getAppValue('richdocuments', 'wopi_url')];
		$headers = ['X-Frame-Options' => 'ALLOW'];
		$response = new DataResponse(['data' => $data], 200, $headers);
		return $response;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * Wopi info of a remote accessing a file
	 *
	 * @param $token access token provided by remote server
	 * @return DataResponse
	 */
	public function getRemoteWopiInfo($token) {
		$row = new Wopi();
		$row->loadBy('token', $token);
		$wopi = $row->getWopiForToken($token);

		if ($wopi == false) {
			$code = 404;
			$data = null;
		} else {
			$code = 200;
			$data = [
				'editorUid' => $wopi['editor'],
				'canwrite' => $wopi['attributes'] | Wopi::ATTR_CAN_UPDATE
			];
		}

		return new DataResponse(['data' => $data], $code);
	}
}
