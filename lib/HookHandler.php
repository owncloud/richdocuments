<?php

/**
 * ownCloud - Richdocuments App
 *
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 * @copyright 2018 Piotr Mrowczynski <piotr@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */
namespace OCA\Richdocuments;

use OCP\Util;

/**
 * Class HookHandler
 *
 * handles hooks
 *
 * @package OCA\Richdocuments
 */
class HookHandler {
	public static function addViewerScripts() {
		Util::addScript('richdocuments', 'viewer/viewer');
		Util::addStyle('richdocuments', 'viewer/odfviewer');
	}

	public static function addConfigScripts($array) {
		$config = \OC::$server->getConfig();
		$appManager = \OC::$server->getAppManager();
		$richdocumentsConfig = new AppConfig($config, $appManager);
		$array['array']['oc_appconfig']['richdocuments'] = [
			'defaultShareAttributes' => [
				// is secure view is enabled for read-only shares, user cannot download by default
				'secureViewHasWatermark' => \json_decode($richdocumentsConfig->getAppValue('secure_view_has_watermark_default')),
				'secureViewCanPrint' => \json_decode($richdocumentsConfig->getAppValue('secure_view_can_print_default')),
			],
			'secureViewAllowed' => \json_decode($richdocumentsConfig->getAppValue('secure_view_option')),
			'openInNewTab' => \json_decode($richdocumentsConfig->getAppValue('open_in_new_tab'))
		];
	}
}
