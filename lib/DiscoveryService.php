<?php
/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
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
use SimpleXMLElement;

class DiscoveryService {
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
	 * @var IClientService
	 */
	private $httpClient;

	public function __construct(
		AppConfig $config,
		ILogger $logger,
		ICacheFactory $cacheFactory,
		IClientService $httpClient
	) {
		$this->appConfig = $config;
		$this->logger = $logger;
		$this->cache = $cacheFactory->create('oca.richdocuments.discovery');
		$this->httpClient = $httpClient;
	}

	/**
	 * Return the original wopi url or test wopi url
	 */
	public function getWopiUrl(): string {
		if ($this->appConfig->testUserSessionEnabled()) {
			return $this->appConfig->getAppValue('test_wopi_url');
		}
		return $this->appConfig->getAppValue('wopi_url');
	}

	/**
	 * Get urlsrc and action for a given mimetype from WOPI discovery
	 *
	 * @param string $mimetype
	 *
	 * @return array|null returns urlsrc and action if matched or null in case of error to retrieve discovery
	 */
	public function getWopiSrc(string $mimetype) : ?array {
		$discoveryXML =	$this->getDiscovery();
		if ($discoveryXML === null) {
			// error retrieving discovery
			return null;
		}

		// validate mimetype
		if (!\in_array($mimetype, Helper::$MIMETYPE_LIBREOFFICE_WORDPROCESSOR, true)) {
			return null;
		}

		$result = $discoveryXML->xpath(\sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
		if (($result !== false) && (\count($result) > 0)) {
			return [
				'urlsrc' => (string)$result[0]['urlsrc'],
				'action' => (string)$result[0]['name']
			];
		}

		// no matching mimetype found
		return [ 'urlsrc' => null, 'action' => null ];
	}

	/**
	 * Return the content of discovery.xml - either from cache, or download it.
	 *
	 * @return SimpleXMLElement|null returns discovery or null in case of discovery cannot be retrieved
	 */
	private function getDiscovery() : ?SimpleXMLElement {
		if ($this->appConfig->testUserSessionEnabled()) {
			$wopiRemote = $this->appConfig->getAppValue('test_wopi_url');
			$discoveryCacheKey = 'discovery.xml_test';
		} else {
			$wopiRemote = $this->appConfig->getAppValue('wopi_url');
			$discoveryCacheKey = 'discovery.xml';
		}

		// Provides access to information about the capabilities of a WOPI client
		// and the mechanisms for invoking those abilities through URIs.
		$wopiDiscovery = $wopiRemote . '/hosting/discovery';

		// Read the memcached value (if the memcache is installed)
		$discovery = $this->cache->get($discoveryCacheKey);

		if ($discovery === null) {
			$this->logger->debug('Fetching {discoveryKey} as not found in cache', ['app' => 'richdocuments', 'discoveryKey' => $discoveryCacheKey]);

			try {
				// If we are sending query to built-in CODE server, we avoid using IClient::get() method
				// because of an encoding issue in guzzle: https://github.com/guzzle/guzzle/issues/1758
				if (\strpos($wopiDiscovery, 'proxy.php') === false) {
					$wopiClient = $this->httpClient->newClient();
					$discovery = $wopiClient->get($wopiDiscovery)->getBody();
				} else {
					$discovery = \file_get_contents($wopiDiscovery);
				}
			} catch (\Exception $e) {
				$error_message = $e->getMessage();

				if (\preg_match('/^cURL error ([0-9]*):/', $error_message, $matches)) {
					$curl_error = $matches[1];
					switch ($curl_error) {
						case '1':
							$this->logger->error('Collabora Online: The protocol specified in {wopiRemote} is not allowed.', ['app' => 'richdocuments', 'wopiRemote' => $wopiRemote]);
							break;
						case '3':
							$this->logger->error('Collabora Online: Malformed URL {wopiRemote}.', ['app' => 'richdocuments', 'wopiRemote' => $wopiRemote]);
							break;
						case '6':
							$this->logger->error('Collabora Online: Cannot resolve the host {wopiRemote}.', ['app' => 'richdocuments', 'wopiRemote' => $wopiRemote]);
							break;
						case '7':
							$this->logger->error('Collabora Online: Cannot connect to the host {wopiRemote}.', ['app' => 'richdocuments', 'wopiRemote' => $wopiRemote]);
							break;
						case '35':
							$this->logger->error('Collabora Online: SSL/TLS handshake failed with the host {wopiRemote}.', ['app' => 'richdocuments', 'wopiRemote' => $wopiRemote]);
							break;
						case '60':
							$this->logger->error('Collabora Online: SSL certificate is not installed. Please ask your administrator to add ca-chain.cert.pem to the ca-bundle.crt, for example "cat /etc/loolwsd/ca-chain.cert.pem >> <server-installation>/resources/config/ca-bundle.crt". The exact error message was: {error_message}', ['app' => 'richdocuments', 'error_message' => $error_message]);
							break;
						default:
							$this->logger->error('Collabora Online cURL error: {error}', ['app' => 'richdocuments', 'error' => $error_message]);
							break;
					}
				} else {
					$this->logger->error('Collabora Online unknown error: {error}', ['app' => 'richdocuments', 'error' => $error_message]);
				}

				return null;
			}

			if (!$discovery) {
				$this->logger->error('Collabora Online: Unable to read discovery.xml from {wopiRemote}', ['app' => 'richdocuments', 'wopiRemote' => $wopiRemote]);
				return null;
			}

			$this->logger->debug('Storing {discoveryKey} to the cache.', ['app' => 'richdocuments', 'discoveryKey' => $discoveryCacheKey]);
			$this->cache->set($discoveryCacheKey, $discovery, 3600);
		} else {
			$this->logger->debug('{discoveryKey} found in cache', ['app' => 'richdocuments', 'discoveryKey' => $discoveryCacheKey]);
		}

		$loadEntities = \libxml_disable_entity_loader(true);
		$discoveryXML = \simplexml_load_string($discovery);
		\libxml_disable_entity_loader($loadEntities);

		if ($discoveryXML === false) {
			$this->cache->remove($discoveryCacheKey);
			$this->logger->error('Collabora Online: {discoveryKey} from {wopiRemote} is not a well-formed XML string.', ['app' => 'richdocuments', 'wopiRemote' => $wopiRemote, 'discoveryKey' => $discoveryCacheKey]);
			return null;
		}

		return $discoveryXML;
	}
}
