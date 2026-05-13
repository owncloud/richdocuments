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

use OCA\Federation\TrustedServers;
use OCA\Richdocuments\FederationService;
use OCP\Http\Client\IClientService;
use OCP\ILogger;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FederationServiceTest extends TestCase {
	/** @var ILogger|MockObject */
	private $logger;

	/** @var IClientService|MockObject */
	private $httpClient;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var TrustedServers|MockObject */
	private $trustedServers;

	/** @var FederationService */
	private $federationService;

	protected function setUp(): void {
		parent::setUp();

		$this->urlGenerator   = $this->createMock(IURLGenerator::class);
		$this->logger         = $this->createMock(ILogger::class);
		$this->httpClient     = $this->createMock(IClientService::class);
		$this->trustedServers = $this->createMock(TrustedServers::class);

		$this->federationService = new FederationService(
			$this->logger,
			$this->urlGenerator,
			$this->httpClient,
			$this->trustedServers
		);
	}

	// -------------------------------------------------------------------------
	// Existing tests
	// -------------------------------------------------------------------------

	public function dataGenerateFederatedCloudID() {
		$userPrefix = ['username', '1234'];
		$remotes    = ['localhost', 'local.host', 'dev.local.host', '127.0.0.1'];

		$testCases = [];
		foreach ($userPrefix as $user) {
			foreach ($remotes as $remote) {
				$testCases[] = [$user, $remote];
			}
		}
		return $testCases;
	}

	/**
	 * @dataProvider dataGenerateFederatedCloudID
	 */
	public function testSplitUserRemote($userId, $remote) {
		$this->urlGenerator->method('getAbsoluteUrl')
			->with('/')
			->willReturn("https://{$remote}/");

		$federatedCloudID = $this->federationService->generateFederatedCloudID($userId);

		$this->assertSame("{$userId}@{$remote}", $federatedCloudID);
	}

	// -------------------------------------------------------------------------
	// isServerAllowed() tests
	// -------------------------------------------------------------------------

	public function testIsServerAllowedReturnsFalseWhenTrustedServersIsNull(): void {
		$service = new FederationService(
			$this->logger,
			$this->urlGenerator,
			$this->httpClient,
			null
		);

		$this->assertFalse($service->isServerAllowed('https://remote.example.com'));
	}

	public function testIsServerAllowedReturnsTrueForExactMatch(): void {
		$this->trustedServers->method('isTrustedServer')
			->willReturnCallback(fn($url) => $url === 'https://trusted.example.com');

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com'));
	}

	public function testIsServerAllowedStripsTrailingSlash(): void {
		$this->trustedServers->method('isTrustedServer')
			->willReturnCallback(fn($url) => $url === 'https://trusted.example.com');

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com/'));
	}

	public function testIsServerAllowedStripsMultipleTrailingSlashes(): void {
		$this->trustedServers->method('isTrustedServer')
			->willReturnCallback(fn($url) => $url === 'https://trusted.example.com');

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com///'));
	}

	public function testIsServerAllowedSwapsHttpToHttps(): void {
		// Admin stored https://, request arrives as http://
		$this->trustedServers->method('isTrustedServer')
			->willReturnCallback(fn($url) => $url === 'https://trusted.example.com');

		$this->assertTrue($this->federationService->isServerAllowed('http://trusted.example.com'));
	}

	public function testIsServerAllowedSwapsHttpsToHttp(): void {
		// Admin stored http://, request arrives as https://
		$this->trustedServers->method('isTrustedServer')
			->willReturnCallback(fn($url) => $url === 'http://trusted.example.com');

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com'));
	}

	public function testIsServerAllowedReturnsFalseForUntrustedServer(): void {
		$this->trustedServers->method('isTrustedServer')
			->willReturn(false);

		$this->assertFalse($this->federationService->isServerAllowed('https://evil.attacker.com'));
	}

}
