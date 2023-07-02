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
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;

class OCSFederationController extends OCSController {
	private $config;
	private $discoveryService;
	private $urlGenerator;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		DiscoveryService $discoveryService,
		IURLGenerator $urlGenerator
	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->discoveryService = $discoveryService;
		$this->urlGenerator = $urlGenerator;
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
		$response = new DataResponse(['data' => $data], 200, $headers);
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

		return new DataResponse(['data' => [
			'owner' => $this->generateFederatedCloudID($wopi['owner']),
			'editor' => $this->generateFederatedCloudID($wopi['editor']),
			'attributes' => $wopi['attributes'],
			'server_host' => $wopi['server_host']
		]], 200);
	}

	/**
	 * split user and remote from federated cloud id, null if not federated cloud id
	 *
	 * @param string $userId user id
	 * @return string
	 */
	public function generateFederatedCloudID(string $userId) : string {
		if (\strpos($userId, '@') === false) {
			// generate federated cloud id
			$user =  $userId;
			$remote = \preg_replace('|^(.*?://)|', '', \rtrim($this->urlGenerator->getAbsoluteURL('/'), '/'));
			return "{$user}@{$remote}";
		}

		// Find the first character that is not allowed in user names
		$id = \str_replace('\\', '/', $userId);
		$posSlash = \strpos($id, '/');
		$posColon = \strpos($id, ':');

		if ($posSlash === false && $posColon === false) {
			$invalidPos = \strlen($id);
		} elseif ($posSlash === false) {
			$invalidPos = $posColon;
		} elseif ($posColon === false) {
			$invalidPos = $posSlash;
		} else {
			$invalidPos = \min($posSlash, $posColon);
		}

		// Find the last @ before $invalidPos
		$pos = $lastAtPos = 0;
		while ($lastAtPos !== false && $lastAtPos <= $invalidPos) {
			$pos = $lastAtPos;
			$lastAtPos = \strpos($id, '@', $pos + 1);
		}

		if ($pos !== false) {
			$user = \substr($id, 0, $pos);
			$remote = \substr($id, $pos + 1);
			$remote = \str_replace('\\', '/', $remote);
			if ($fileNamePosition = \strpos($remote, '/index.php')) {
				$remote = \substr($remote, 0, $fileNamePosition);
			}
			$remote = \rtrim($remote, '/');
			if (!empty($user) && !empty($remote)) {
				// already a federated cloud id
				return $userId;
			}
		}

		// generate federated cloud id
		$user =  $userId;
		$remote = \preg_replace('|^(.*?://)|', '', \rtrim($this->urlGenerator->getAbsoluteURL('/'), '/'));
		return "{$user}@{$remote}";
	}
}
