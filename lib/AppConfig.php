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

namespace OCA\Richdocuments;

use OCP\License\ILicenseManager;
use OCP\App\IAppManager;
use OCP\IConfig;

class AppConfig {
	private $appName = 'richdocuments';
	private $defaults = [
		'secure_view_option' => 'false',
		'secure_view_open_action_default' => 'false',
		'secure_view_can_print_default' => 'false',
		'secure_view_has_watermark_default' => 'true',
		'open_in_new_tab' => 'true',
		'start_grace_period' => 'false',
		'zotero' => 'false',
		'watermark_text' => '',
		'test_server_groups' => '',
		'canonical_webroot' => '',
	];

	private $config;
	private $appManager;
	private $licenseManager;

	public function __construct(IConfig $config, IAppManager $appManager, ILicenseManager $licenseManager) {
		$this->config = $config;
		$this->appManager = $appManager;
		$this->licenseManager = $licenseManager;
	}

	/**
	 * Get a value by key
	 * @param string $key
	 * @return string
	 */
	public function getAppValue($key) {
		$defaultValue = null;
		if (\array_key_exists($key, $this->defaults)) {
			$defaultValue = $this->defaults[$key];
		}
		return $this->config->getAppValue($this->appName, $key, $defaultValue);
	}

	/**
	 * Set a value by key
	 * @param string $key
	 * @param string $value
	 */
	public function setAppValue($key, $value) {
		$this->config->setAppValue($this->appName, $key, $value);
	}

	/**
	 * Get a value by key for a user
	 * @param string $userId
	 * @param string $key
	 * @return string
	 */
	public function getUserValue($userId, $key) {
		$defaultValue = null;
		if (\array_key_exists($key, $this->defaults)) {
			$defaultValue = $this->defaults[$key];
		}
		return $this->config->getUserValue($userId, $this->appName, $key, $defaultValue);
	}

	/**
	 * Set a value by key for a user
	 * @param string $userId
	 * @param string $key
	 * @param string $value
	 */
	public function setUserValue($userId, $key, $value) {
		$this->config->setUserValue($userId, $this->appName, $key, $value);
	}

	/**
	 * Check if app can have enterprise features enabled
	 *
	 * @return bool
	 */
	public function enterpriseFeaturesEnabled() {
		$startGracePeriod = $this->getAppValue('start_grace_period');
		$startGracePeriod = \filter_var($startGracePeriod, FILTER_VALIDATE_BOOLEAN);
		$options = [
			'startGracePeriod' => $startGracePeriod,
			'disableApp' => false,
		];
		return $this->licenseManager->checkLicenseFor($this->appName, $options);
	}

	/**
	 * Check if encryption is enabled for the server in general
	 *
	 * @return bool
	 */
	public function encryptionEnabled() {
		if (!\OC::$server->getEncryptionManager()->isEnabled()) {
			return false;
		}

		return true;
	}

	/**
	 * Check if master key encryption encryption module is enabled
	 *
	 * @return bool
	 */
	public function masterEncryptionEnabled() {
		if (!$this->encryptionEnabled()) {
			return false;
		}

		// check if default encryption module app is installed and enabled
		if (!$this->appManager->isEnabledForUser('encryption') && !\getenv('CI')) {
			return false;
		}

		// if encryption app is enabled, check for master key
		return \OC::$server->query('\OCA\Encryption\Util')->isMasterKeyEnabled();
	}

	/**
	 * @return bool
	 */
	public function secureViewOptionEnabled() {
		return \filter_var($this->getAppValue('secure_view_option'), FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * @return bool
	 */
	public function secureViewOpenActionDefaultEnabled() {
		return \filter_var($this->getAppValue('secure_view_open_action_default'), FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * @return bool
	 */
	public function secureViewCanPrintDefaultEnabled() {
		return \filter_var($this->getAppValue('secure_view_can_print_default'), FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * @return bool
	 */
	public function secureViewHasWatermarkDefaultEnabled() {
		return \filter_var($this->getAppValue('secure_view_has_watermark_default'), FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * @return bool
	 */
	public function openInNewTabEnabled() {
		return \filter_var($this->getAppValue('open_in_new_tab'), FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Return true if the currently logged in user is a tester.
	 * This depends on whether current user is the member of one of the groups
	 * mentioned in settings (test_server_groups)
	 *
	 * WARNING: This method is legacy, use with caution.
	 *
	 * @return bool
	 */
	public function testUserSessionEnabled() {
		$tester = false;

		$user = \OC::$server->getUserSession()->getUser();
		if ($user === null) {
			return false;
		}

		$uid = $user->getUID();
		$testgroups = \array_filter(\explode('|', $this->getAppValue('test_server_groups')));
		foreach ($testgroups as $testgroup) {
			$test = \OC::$server->getGroupManager()->get($testgroup);
			if ($test !== null && \sizeof($test->searchUsers($uid)) > 0) {
				$tester = true;
				break;
			}
		}

		return $tester;
	}
}
