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

use OCP\App\IAppManager;
use \OCP\IConfig;

class AppConfig {
	private $appName = 'richdocuments';
	private $defaults = [
		'wopi_url' => 'https://localhost:9980',
		'secure_view_option' => 'false',
		'secure_view_can_print_default' => 'false',
		'secure_view_has_watermark_default' => 'true',
		'open_in_new_tab' => 'true'
	];

	private $config;
	private $appManager;

	public function __construct(IConfig $config, IAppManager $appManager) {
		$this->config = $config;
		$this->appManager = $appManager;
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
	 * @return string
	 */
	public function setAppValue($key, $value) {
		return $this->config->setAppValue($this->appName, $key, $value);
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
	 * @return string
	 */
	public function setUserValue($userId, $key, $value) {
		return $this->config->setAppValue($userId, $this->appName, $key, $value);
	}

	/**
	 * Check if app can have enterprise features enabled
	 *
	 * @return bool
	 */
	public function enterpriseFeaturesEnabled() {
		if (!$this->appManager->isEnabledForUser('enterprise_key') && !\getenv('CI')) {
			return false;
		}

		return true;
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
}
