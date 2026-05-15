# Federation Allowlist via System Config Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `OCA\Federation\TrustedServers` in `FederationService` with a `richdocuments.federation_allowlist` system config key as the sole authority for permitted federation servers.

**Architecture:** Inject `IConfig` (ownCloud's core config interface) into `FederationService` in place of `TrustedServers`. `isServerAllowed()` reads the allowlist from `config.php` via `getSystemValue()`, applies trailing-slash normalisation and http/https scheme-swap matching per entry, and returns false by default when the key is absent or non-array. `Application.php` DI registration simplifies — no federation-app check needed.

**Tech Stack:** PHP 8.3, ownCloud app framework, `OCP\IConfig`, PHPUnit 9/10.

---

## File Map

| File | Action | Responsibility |
|---|---|---|
| `lib/FederationService.php` | Modify | Replace `TrustedServers` with `IConfig`; rewrite `isServerAllowed()` |
| `lib/AppInfo/Application.php` | Modify | Simplify DI registration for `FederationService` |
| `tests/unit/FederationServiceTest.php` | Modify | Replace mock type and all `isServerAllowed` test cases |

---

## Task 1: Replace tests for `isServerAllowed()` with IConfig-based versions

**Files:**
- Modify: `tests/unit/FederationServiceTest.php`

The existing `isServerAllowed` tests use a `TrustedServers` mock. Replace them wholesale with `IConfig`-based tests. The `testSplitUserRemote` test and its data provider are unchanged except for updating the constructor call in `setUp()` to pass an `IConfig` mock instead of a `TrustedServers` mock.

- [ ] **Step 1: Replace the entire file with the new test class**

```php
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

	public function testIsServerAllowedReturnsFalseWhenListIsEmpty(): void {
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
			->willReturn(['https://trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com'));
	}

	public function testIsServerAllowedStripsTrailingSlash(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['https://trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com/'));
	}

	public function testIsServerAllowedStripsMultipleTrailingSlashes(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['https://trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com///'));
	}

	public function testIsServerAllowedSwapsHttpToHttps(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['https://trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('http://trusted.example.com'));
	}

	public function testIsServerAllowedSwapsHttpsToHttp(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['http://trusted.example.com']);

		$this->assertTrue($this->federationService->isServerAllowed('https://trusted.example.com'));
	}

	public function testIsServerAllowedReturnsFalseForUntrustedServer(): void {
		$this->config->method('getSystemValue')
			->with('richdocuments.federation_allowlist', [])
			->willReturn(['https://trusted.example.com']);

		$this->assertFalse($this->federationService->isServerAllowed('https://evil.attacker.com'));
	}
}
```

- [ ] **Step 2: Verify the test count**

```bash
grep -c "public function test" tests/unit/FederationServiceTest.php
```

Expected output: `11`

---

## Task 2: Rewrite `FederationService` to use `IConfig`

**Files:**
- Modify: `lib/FederationService.php`

Replace the `TrustedServers` dependency with `IConfig`. The new `isServerAllowed()` iterates the allowlist array, normalises each entry, and checks for exact or scheme-swapped match.

- [ ] **Step 1: Replace the entire file**

```php
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
	 * Check if the given server URL is in the richdocuments.federation_allowlist system config.
	 *
	 * Returns false when the key is absent, empty, or not an array. Each entry is
	 * normalised (trailing slashes stripped) and checked against the incoming URL
	 * both as-is and with the http/https scheme swapped, to tolerate minor
	 * mismatches between how the admin stored the URL and how the request arrives.
	 *
	 * @param string $remote a remote url
	 * @return bool
	 */
	public function isServerAllowed(string $remote): bool {
		$allowlist = $this->config->getSystemValue('richdocuments.federation_allowlist', []);

		if (!\is_array($allowlist) || empty($allowlist)) {
			return false;
		}

		$normalized = \rtrim($remote, '/');

		if (\strpos($normalized, 'https://') === 0) {
			$swapped = 'http://' . \substr($normalized, 8);
		} elseif (\strpos($normalized, 'http://') === 0) {
			$swapped = 'https://' . \substr($normalized, 7);
		} else {
			$swapped = null;
		}

		foreach ($allowlist as $entry) {
			$e = \rtrim($entry, '/');
			if ($normalized === $e) {
				return true;
			}
			if ($swapped !== null && $swapped === $e) {
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
```

- [ ] **Step 2: Commit**

```bash
git add lib/FederationService.php tests/unit/FederationServiceTest.php
git commit -s -m "feat(security): replace TrustedServers with richdocuments.federation_allowlist system config

isServerAllowed() now reads a PHP array from config.php via
richdocuments.federation_allowlist. Absent/empty/non-array values deny all.
Each entry is checked with trailing-slash stripping and http/https scheme
swap to tolerate minor URL mismatches.

Removes the optional federation-app dependency entirely."
```

---

## Task 3: Simplify DI registration in `Application.php`

**Files:**
- Modify: `lib/AppInfo/Application.php`

Remove the federation-app `isInstalled()` check and Phan suppression. Pass `$server->getConfig()` as the fourth argument.

- [ ] **Step 1: Update `registerServices()` in `lib/AppInfo/Application.php`**

Replace the entire `FederationService` registration block (lines 41–56):

```php
		$container->registerService(FederationService::class, function () use ($server) {
			return new FederationService(
				$server->getLogger(),
				$server->getURLGenerator(),
				$server->getHTTPClientService(),
				$server->getConfig()
			);
		});
```

The block being replaced is:

```php
		/**
		 * FederationService — inject TrustedServers only when the federation app is installed.
		 * When null, FederationService::isServerAllowed() denies all remote servers (secure default).
		 */
		$container->registerService(FederationService::class, function () use ($server) {
			$trustedServers = null;
			if ($server->getAppManager()->isInstalled('federation')) {
				/* @phan-suppress-next-line PhanUndeclaredClassReference */
				$trustedServers = $server->query(\OCA\Federation\TrustedServers::class);
			}
			return new FederationService(
				$server->getLogger(),
				$server->getURLGenerator(),
				$server->getHTTPClientService(),
				$trustedServers
			);
		});
```

Also remove the `use OCA\Richdocuments\FederationService;` import only if it is no longer referenced elsewhere in the file — check first. It IS still used in `registerService(FederationService::class, ...)` so keep it.

- [ ] **Step 2: Run the code style check locally**

```bash
make test-php-style
```

Expected: no output errors, exit 0.

- [ ] **Step 3: Commit**

```bash
git add lib/AppInfo/Application.php
git commit -s -m "fix: simplify FederationService DI — inject IConfig directly

No longer needs the federation app isInstalled() check or Phan
suppression. IConfig is a core ownCloud interface, always available."
```

---

## Self-Review

**Spec coverage:**
- ✅ `richdocuments.federation_allowlist` PHP array in system config — Task 2
- ✅ Absent key → deny all — Task 2 (`getSystemValue` default `[]`) + test in Task 1
- ✅ Non-array value → deny all — Task 2 (`is_array` guard) + test in Task 1
- ✅ Empty array → deny all — Task 2 + test in Task 1
- ✅ Exact match — Task 2 + test in Task 1
- ✅ Trailing slash stripping — Task 2 + tests in Task 1
- ✅ http/https scheme swap — Task 2 + tests in Task 1
- ✅ Untrusted server → false — Task 2 + test in Task 1
- ✅ DI registration simplified in Application.php — Task 3
- ✅ All Phan suppressions removed — Task 2 (FederationService.php) + Task 3 (Application.php)

**Placeholder scan:** None found.

**Type consistency:**
- `IConfig $config` — constructor param in Task 2, mock in Task 1, DI in Task 3. Consistent.
- `isServerAllowed(string $remote): bool` — signature unchanged, used in `getWopiForToken()` and `getRemoteWopiSrc()` in Task 2, tested in Task 1. Consistent.
- `getSystemValue('richdocuments.federation_allowlist', [])` — called in Task 2, mocked with same signature in Task 1. Consistent.
