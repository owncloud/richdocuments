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
namespace OCA\Richdocuments\Tests;

use OCA\Richdocuments\DiscoveryService;
use OCA\Richdocuments\AppConfig;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ICacheFactory;
use OCP\ICache;
use OCP\ILogger;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class DiscoveryServiceTest extends TestCase {
	/**
	 * The AppConfig instance.
	 *
	 * @var AppConfig
	 */
	private $appConfig;

	/**
	 * The ILogger instance.
	 *
	 * @var ILogger
	 */
	private $logger;

	/**
	 * The ICache instance.
	 *
	 * @var ICache
	 */
	private $cache;

	/**
	 * The ICacheFactory instance.
	 *
	 * @var ICacheFactory
	 */
	private $cacheFactory;

	/**
	 * The IClientService instance.
	 *
	 * @var IClientService
	 */
	private $httpClient;

	/**
	 * @var DiscoveryService|MockObject $discoveryService The discovery service mock object.
	 */
	private $discoveryService;

	/**
	 * @var string $discoveryXml The XML string for the discovery service.
	 */
	private $discoveryXml = '<wopi-discovery><net-zone><app name="application/vnd.openxmlformats-officedocument.wordprocessingml.document"><action name="view" urlsrc="https://example.com/view"/></app></net-zone></wopi-discovery>';

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(AppConfig::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->httpClient = $this->createMock(IClientService::class);

		$this->cache = $this->createMock(ICache::class);
		$this->cacheFactory->expects($this->once())
			->method('create')
			->with('oca.richdocuments.discovery')
			->willReturn($this->cache);

		$this->discoveryService = new DiscoveryService(
			$this->appConfig,
			$this->logger,
			$this->cacheFactory,
			$this->httpClient
		);
	}

	public function testGetWopiSrcUrlWithMatchingMimetype() {
		$this->cache->expects($this->once())
			->method('get')
			->with('discovery.xml')
			->willReturn($this->discoveryXml);

		$result = $this->discoveryService->getWopiSrc('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		$this->assertEquals(['urlsrc' => 'https://example.com/view', 'action' => 'view'], $result);
	}
	
	public function testGetWopiSrcUrlWithNoMatchingMimetype() {
		$this->cache->expects($this->once())
			->method('get')
			->with('discovery.xml')
			->willReturn($this->discoveryXml);

		$result = $this->discoveryService->getWopiSrc('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

		$this->assertEquals(['urlsrc' => null, 'action' => null], $result);
	}
	
	public function testGetWopiSrcUrlWithWrongMimetype() {
		$this->cache->expects($this->once())
			->method('get')
			->with('discovery.xml')
			->willReturn($this->discoveryXml);

		$result = $this->discoveryService->getWopiSrc('wrong');

		$this->assertEquals(null, $result);
	}
	
	public function testGetWopiSrcUrlWithUnparsableDiscovery() {
		$this->cache->expects($this->once())
			->method('get')
			->with('discovery.xml')
			->willReturn("wrong");

		$result = $this->discoveryService->getWopiSrc('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

		$errors = \libxml_get_errors();
		\libxml_clear_errors();

		$this->assertNotEmpty($errors);
		$this->assertEquals(null, $result);
	}
	
	public function testGetWopiSrcUrlOK() {
		$this->cache->expects($this->once())
			->method('get')
			->with('discovery.xml')
			->willReturn(null);
		$this->cache->expects($this->once())
			->method('set')
			->with('discovery.xml', $this->discoveryXml, 3600)
			->willReturn(true);

		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($this->discoveryXml);

		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($response);
		
		$this->httpClient->method('newClient')->willReturn($client);

		$result = $this->discoveryService->getWopiSrc('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		$this->assertEquals(['urlsrc' => 'https://example.com/view', 'action' => 'view'], $result);
	}

	public function dataGetWopiSrcUrlException() {
		return [
			['Test exception'],
			['cURL error 1: Unsupported protocol'],
		];
	}

	/**
	 * @dataProvider dataGetWopiSrcUrlException
	 */
	public function testGetWopiSrcUrlException($exceptionMock) {
		$this->cache->expects($this->once())
			->method('get')
			->with('discovery.xml')
			->willReturn(null);
		$this->cache->expects($this->never())
			->method('set');

		$client = $this->createMock(IClient::class);
		$client->method('get')->willThrowException(new \Exception($exceptionMock));

		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn($this->discoveryXml);
		
		$this->httpClient->method('newClient')->willReturn($client);

		$result = $this->discoveryService->getWopiSrc('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
		$this->assertEquals(null, $result);
	}
}
