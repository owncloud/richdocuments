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

use OCP\IConfig;
use OCP\ILogger;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;

class FederationService {
	/** @var ILogger */
	private $logger;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IClientService */
	private $httpClient;

	/** @var IConfig */
	private $config;

	public function __construct(
		ILogger $logger,
		IURLGenerator $urlGenerator,
		IClientService $httpClient,
		IConfig $config
	) {
		$this->logger       = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->httpClient   = $httpClient;
		$this->config       = $config;
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
	 * @param string $server address of a remote server
	 * @param string $accessToken wopi access token from a remote server
	 * @return array|null with additional wopi information
	 */
	public function getWopiForToken($server, $accessToken) {
		$remote = $server;

		if (!$this->isServerAllowed($remote)) {
			$this->logger->info("Server {server} is not allowed.", ["server" => $remote]);
			return null;
		}

		try {
			$client = $this->httpClient->newClient();
			$url = $remote . '/ocs/v2.php/apps/richdocuments/api/v1/federation';

			$response = $client->post(
				$url,
				[
					'form_params' => [
						'token' => $accessToken,
						'format' => 'json'
					],
					'timeout' => 3,
					'connect_timeout' => 3,
				]
			);

			$responseBody = $response->getBody();
			$data = \json_decode($responseBody, true, 512);
			if (\is_array($data)) {
				return $data['ocs']['data'];
			}
			return null;
		} catch (\Throwable $e) {
			$this->logger->error('Cannot get the wopi info from remote server: ' . $remote, ['exception' => $e]);
		}

		return null;
	}

	/**
	 * Check if the given server URL matches an entry in the richdocuments.federation_allowlist
	 * system config. Allowlist entries are plain domain names (no scheme).
	 *
	 * Returns false when the key is absent, empty, or not an array.
	 *
	 * Known limitation: path-prefixed installs (e.g. cloud.example.com/owncloud) are not
	 * supported — only the host component is extracted and matched against allowlist entries.
	 *
	 * @param string $remote a remote url
	 * @return bool
	 */
	public function isServerAllowed(string $remote): bool {
		$allowlist = $this->config->getSystemValue('richdocuments.federation_allowlist', []);

		if (!\is_array($allowlist) || empty($allowlist)) {
			return false;
		}

		$parsed = \parse_url($remote);
		$domain = isset($parsed['host']) ? \rtrim((string)$parsed['host'], '/') : '';

		foreach ($allowlist as $entry) {
			if ($domain === \rtrim((string)$entry, '/')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the wopiSrc Url from a remote server.
	 *
	 * @param string $server a remote
	 * @return string with the wopi src Url
	 */
	public function getRemoteWopiSrc($server) {
		if (!$this->isServerAllowed($server)) {
			$this->logger->info("Server {server} is not allowed.", ["server" => $server]);
			return '';
		}

		try {
			$getWopiSrcUrl = $server . '/ocs/v2.php/apps/richdocuments/api/v1/federation?format=json';
			$client = $this->httpClient->newClient();
			$response = $client->get($getWopiSrcUrl, ['timeout' => 5]);
			$data = \json_decode($response->getBody(), true);

			if (\is_array($data)) {
				return $data['ocs']['data']['wopi_url'];
			}
		} catch (\Throwable $e) {
			$this->logger->error('Cannot get the wopiSrc of remote server: ' . $server, ['exception' => $e]);
		}

		return '';
	}

	/**
	 * Given local userId return federated cloud id
	 *
	 * @param string $userId user id
	 * @return string
	 */
	public function generateFederatedCloudID(string $userId) : string {
		$remote = \preg_replace('|^(.*?://)|', '', \rtrim($this->urlGenerator->getAbsoluteURL('/'), '/'));
		return "{$userId}@{$remote}";
	}
}
