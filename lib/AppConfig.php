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
	];
	private $defaultMimetypes = [
		'application/pdf',
		'application/vnd.oasis.opendocument.text',
		'application/vnd.oasis.opendocument.spreadsheet',
		'application/vnd.oasis.opendocument.graphics',
		'application/vnd.oasis.opendocument.presentation',
		'application/vnd.oasis.opendocument.text-flat-xml',
		'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
		'application/vnd.oasis.opendocument.graphics-flat-xml',
		'application/vnd.oasis.opendocument.presentation-flat-xml',
		'application/vnd.lotus-wordpro',
		'image/svg+xml',
		'application/vnd.visio',
		'application/vnd.wordperfect',
		'application/msonenote',
		'application/msword',
		'application/rtf',
		'text/rtf',
		'text/plain',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		'application/vnd.ms-word.document.macroEnabled.12',
		'application/vnd.ms-word.template.macroEnabled.12',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
		'application/vnd.ms-excel.sheet.macroEnabled.12',
		'application/vnd.ms-excel.template.macroEnabled.12',
		'application/vnd.ms-excel.addin.macroEnabled.12',
		'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/vnd.openxmlformats-officedocument.presentationml.template',
		'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
		'application/vnd.ms-powerpoint.addin.macroEnabled.12',
		'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
		'application/vnd.ms-powerpoint.template.macroEnabled.12',
		'application/vnd.ms-powerpoint.slideshow.macroEnabled.12'
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
		$this->config->setAppValue($this->appName, $key, $value);
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
	 * @return bool
	 */
	public function getSupportedMimetypes() {
		$supportedMimetypes = \json_decode($this->getAppValue('supported_mimetypes'));
		if (!$supportedMimetypes) {
			return $this->defaultMimetypes;
		}
		return $supportedMimetypes;
	}
}
