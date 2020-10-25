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
		$tokenResult = $this->wopi->generateToken(
			1,
			0,
			7,
			'ttp://localhost',
			'user',
			'user'
		);
		$this->assertArrayHasKey('access_token', $tokenResult);
		$this->assertArrayHasKey('access_token_ttl', $tokenResult);
	}
}
