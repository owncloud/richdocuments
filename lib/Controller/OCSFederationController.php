<?php
/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 * @author Szymon Kłos <szymon.klos@collabora.com>
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

use OCA\Richdocuments\Db\Wopi;
use OCA\Richdocuments\DiscoveryService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class OCSFederationController extends OCSController {
	private $discoveryService;

	public function __construct(
		string $appName,
		IRequest $request,
		DiscoveryService $discoveryService
	) {
		parent::__construct($appName, $request);
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
		$response = new DataResponse(['data' => $data], Http::STATUS_OK, $headers);
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
		$wopi = $row->getWopiForToken($token);

		if ($wopi == false) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse(['data' => [
			'owner' => $wopi['owner'],
			'editor' => $wopi['editor'],
			'attributes' => $wopi['attributes'],
			'server_host' => $wopi['server_host']
		]], Http::STATUS_OK);
	}
}
