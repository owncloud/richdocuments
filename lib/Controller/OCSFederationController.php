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
use OCA\Richdocuments\DiscoveryService;
use \OCP\AppFramework\OCSController;
use \OCP\AppFramework\Http\DataResponse;
use \OCP\IConfig;
use \OCP\IRequest;

class OCSFederationController extends OCSController {
	private $config;
	private $discoveryService;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		DiscoveryService $discoveryService
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->discoveryService = $discoveryService;
	}

	/**
	* @PublicPage
	* @NoCSRFRequired
	*/
	public function index() {
		$wopiRemote = $this->discoveryService->getWopiUrl();
		$data = [
			'wopi_url' => $wopiRemote
		];
		$headers = ['X-Frame-Options' => 'ALLOW'];
		$response = new DataResponse($data, 200, $headers);
		return $response;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * Wopi info of a remote accessing a file
	 *
	 * @param string $token access token provided by remote server
	 * @return DataResponse
	 */
	public function getWopiForToken($token) {
		$row = new Wopi();
		$row->loadBy('token', $token);
		$wopi = $row->getWopiForToken($token);

		if ($wopi == false) {
			return new DataResponse([], 404);
		} 
		return new DataResponse([
			'owner_uid' => $wopi['owner_uid'],
			'editor_uid' => $wopi['editor_uid'],
			'attributes' => $wopi['attributes'],
			'server_host' => $wopi['server_host']
		], 200);
	}
}
