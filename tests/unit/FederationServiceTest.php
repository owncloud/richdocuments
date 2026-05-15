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

use OCA\Richdocuments\FederationService;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
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

	/** @var IConfig|MockObject */
	private $config;

	/** @var FederationService */
	private $federationService;

	protected function setUp(): void {
		parent::setUp();

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger       = $this->createMock(ILogger::class);
		$this->httpClient   = $this->createMock(IClientService::class);
		$this->config       = $this->createMock(IConfig::class);

		$this->federationService = new FederationService(
			$this->logger,
			$this->urlGenerator,
			$this->httpClient,
			$this->config
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

	public function testIsServerAllowedReturnsFalseWhenKeyIsAbsent(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn([]);

		$this->assertFalse($this->federationService->isServerAllowed('https://remote.example.com'));
	}

	public function testIsServerAllowedReturnsFalseForNonArrayConfig(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn('not-an-array');

		$this->assertFalse($this->federationService->isServerAllowed('https://remote.example.com'));
	}

	public function testIsServerAllowedReturnsTrueForExactMatch(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com'));
	}

	public function testIsServerAllowedStripsTrailingSlash(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com/'));
	}

	public function testIsServerAllowedStripsMultipleTrailingSlashes(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com///'));
	}

	public function testIsServerAllowedMatchesHttps(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com'));
	}

	public function testIsServerAllowedMatchesHttp(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('http://trusted.example.com'));
	}

	public function testIsServerAllowedReturnsFalseForUntrustedServer(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['trusted.example.com']);

		$this->assertFalse($this->federationService->isServerAllowed('https://evil.attacker.com'));
	}
}
