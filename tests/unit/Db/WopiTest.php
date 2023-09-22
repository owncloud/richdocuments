<?php

namespace OCA\Richdocuments\Db;

use PHPUnit\Framework\TestCase;

class WopiTest extends TestCase {
	/**
	 * @var Wopi $wopi
	 */
	private $wopi;

	public function setUp(): void {
		parent::setUp();
		$this->wopi = new Wopi();
	}

	public function tearDown(): void {
		parent::tearDown();
		\OC::$server->getDatabaseConnection()
			->executeUpdate('DELETE FROM `*PREFIX*richdocuments_wopi`');
	}

	public function testGenerateToken() {
		$token = $this->wopi->generateToken(
			1,
			0,
			7,
			'http://localhost',
			'user',
			'user'
		);
		$this->assertArrayHasKey('access_token', $token);
		$this->assertArrayHasKey('access_token_ttl', $token);

		$wopi = $this->wopi->getWopiForToken($token['access_token']);
		$this->assertSame('1', $wopi['fileid']); // convertion to string when retrieving
		$this->assertSame('0', $wopi['version']); // convertion to string when retrieving
		$this->assertSame('7', $wopi['attributes']); // convertion to string when retrieving
		$this->assertSame('http://localhost', $wopi['server_host']);
		$this->assertSame('user', $wopi['owner']);
		$this->assertSame('user', $wopi['editor']);

	}
}
