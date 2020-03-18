<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2020, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */
namespace OCA\Richdocuments\Tests;

use OCA\Richdocuments\AppConfig;
use OCP\App\IAppManager;
use OCP\IConfig;
use Test\TestCase;

class AppConfigTest extends TestCase {
	/** @var IConfig */
	private $config;

	/** @var IAppManager */
	private $appManager;

	/** @var AppConfig */
	private $appConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appConfig = new AppConfig($this->config, $this->appManager);
	}

	public function testOpenInNewtabDefault() {
		$this->config->method('getAppValue')
			->willReturn('true');
		$value = $this->appConfig->getAppValue('open_in_new_tab');
		$this->assertEquals('true', $value);
	}
}
