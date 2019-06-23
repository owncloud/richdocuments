<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Victor Dubiniuk
 * @copyright 2015 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments;

use OCP\App\IAppManager;
use \OCP\IConfig;
use \OCA\Encryption\Util;

class AppConfig {
	private $appName = 'richdocuments';
	private $defaults = [
		'wopi_url' => 'https://localhost:9980',
		'secure_view_option' => 'false',
		'secure_view_can_print_default' => 'true',
		'secure_view_has_watermark_default' => 'true'
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
	 * Check if encryption is enabled
	 *
	 * @return bool
	 */
	public function encryptionEnabled() {
		if (!$this->appManager->isEnabledForUser('encryption') && !\getenv('CI')) {
			return false;
		}

		if (!\OC::$server->getEncryptionManager()->isEnabled()) {
			return false;
		}

		return true;
	}

	/**
	 * Check if master encryption is enabled
	 *
	 * @return bool
	 */
	public function masterEncryptionEnabled() {
		if (!$this->encryptionEnabled()) {
			return false;
		}

		return \OC::$server->query(Util::class)->isMasterKeyEnabled();
	}

	/**
	 * Check if user encryption is enabled
	 *
	 * @return bool
	 */
	public function userEncryptionEnabled() {
		if (!$this->masterEncryptionEnabled()) {
			return false;
		}

		return true;
	}
}
