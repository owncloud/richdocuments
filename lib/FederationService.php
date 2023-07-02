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
namespace OCA\Richdocuments;

use OCP\ICache;
use OCP\ICacheFactory;
use OCP\ILogger;
use OCP\Http\Client\IClientService;
use OCA\Richdocuments\AppConfig;
use OCP\IURLGenerator;
use SimpleXMLElement;

class FederationService {
	/**
	 * @var AppConfig
	 */
	private $appConfig;

	/**
	 * @var ILogger
	 */
	private $logger;

	/**
	 * @var ICache
	 */
	private $cache;

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * @var IClientService
	 */
	private $httpClient;

	public function __construct(
		AppConfig $config,
		ILogger $logger,
		ICacheFactory $cacheFactory,
		IURLGenerator $urlGenerator,
		IClientService $httpClient
	) {
		$this->appConfig = $config;
		$this->logger = $logger;
		$this->cache = $cacheFactory->create('oca.richdocuments.federation');
		$this->urlGenerator = $urlGenerator;
		$this->httpClient = $httpClient;
	}

	/**
	 * Get the Url of the collabora document on a federated server.
	 *
	 * @param string $shareToken
	 * @param string $shareRelativePath
	 * @param string $server
	 * @param string $accessToken
	 * @return string with the Url to the given resource
	 */
	public function getRemoteFileUrl(string $shareToken, string $shareRelativePath, string $server, string $accessToken) : string {
		$serverHost = $this->urlGenerator->getAbsoluteURL('/');
		$remoteFileUrl = \rtrim($server, '/') . '/index.php/apps/richdocuments/documents.php/federated' .
			'?shareToken=' . $shareToken .
			'&shareRelativePath=' . $shareRelativePath .
			'&server=' . \rtrim($serverHost, '/') .
			'&accessToken=' . $accessToken;
		return $remoteFileUrl;
	}
	
	/**
	*
	* @param string $server addres of a remote server
	* @param string $accessToken wopi access token from a remote server
	* @return array|null with additional wopi information
	*/
	public function getWopiForToken($server, $remoteToken) {
		$remote = $server;
		// if (!$this->isTrustedServer($remote)) {
		// 	$this->logger->info("Server {server} is not trusted.", ["server" => $remote]);
		// 	return null;
		// }

		try {
			$client = $this->httpClient->newClient();
			$url = $remote . '/ocs/v2.php/apps/richdocuments/api/v1/federation';

			$response = $client->post(
				$url,
				[
					'form_params' => [
						'token' => $remoteToken,
						'format' => 'json'
					],
					'timeout' => 3,
					'connect_timeout' => 3,
				]
			);

			$responseBody = $response->getBody();
			$data = \json_decode($responseBody, true, 512);

			return $data['ocs']['data'];
		} catch (\Throwable $e) {
			$this->logger->info('Cannot get the wopi info from remote server: ' . $remote, ['exception' => $e]);
		}

		return null;
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
	public function getRemoteWopiSrc($server, $token) {
		if (\strpos($server, 'http://') === false && \strpos($server, 'https://') === false) {
			$remote = 'https://' . $server;
		}

		// if (!$this->isTrustedServer($remote)) {
		// 	$this->logger->info("Server {server} is not trusted.", ["server" => $remote]);
		// 	return '';
		// }

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
}
