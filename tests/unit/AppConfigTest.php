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

use OCP\License\ILicenseManager;
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

	/** @var ILicenseManager */
	private $licenseManager;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->licenseManager = $this->createMock(ILicenseManager::class);
		$this->appConfig = new AppConfig($this->config, $this->appManager, $this->licenseManager);
	}

	public function testOpenInNewtabDefault() {
		$this->config->method('getAppValue')
			->willReturn('true');
		$this->licenseManager->method('checkLicenseFor')
			->willReturn(true);
		$value = $this->appConfig->getAppValue('open_in_new_tab');
		$enterpriseEdition = $this->appConfig->enterpriseFeaturesEnabled();
		$this->assertEquals('true', $value);
		$this->assertEquals(true, $enterpriseEdition);
	}

	public function enterpriseFeaturesEnabledProvider() {
		return [
			[true, 'false', true],
			[false, 'false', false],
			[true, 'true', true],
			[false, 'true', true],
		];
	}

	/**
	 * @dataProvider enterpriseFeaturesEnabledProvider
	 */
	public function testEnterpriseFeaturesEnabled($licenseCheckReturn, $gracePeriodFlag, $expectedResult) {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('richdocuments', 'start_grace_period', 'false')
			->willReturn($gracePeriodFlag);

		$gracePeriodFlagBool = \filter_var($gracePeriodFlag, FILTER_VALIDATE_BOOLEAN);
		$options = [
			'startGracePeriod' => $gracePeriodFlagBool,
			'disableApp' => false,
		];
		$this->licenseManager->expects($this->once())
			->method('checkLicenseFor')
			->with('richdocuments', $options)
			->willReturn($licenseCheckReturn || $gracePeriodFlagBool);  // license or grace period active

		$this->assertSame($expectedResult, $this->appConfig->enterpriseFeaturesEnabled());
	}
}
