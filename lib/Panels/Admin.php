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
namespace OCA\Richdocuments\Panels;

use OCP\ILogger;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Template;

use OCA\Richdocuments\AppConfig;

class Admin implements ISettings {
	/** @var ILogger */
	protected $logger;

	/** @var IUserSession */
	private $userSession;

	/** @var AppConfig */
	private $appConfig;

	/**
	 * @param ILogger $logger
	 * @param IUserSession $userSession
	 */
	public function __construct(
		ILogger $logger,
		IUserSession $userSession,
		AppConfig $appConfig
	) {
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->appConfig = $appConfig;
	}

	public function getPriority() {
		return 0;
	}

	public function getSectionID() {
		return 'additional';
	}

	/**
	 * @return \OCP\AppFramework\Http\TemplateResponse|Template|null
	 */
	public function getPanel() {
		$template = new Template('richdocuments', 'settings-admin');

		$template->assign('wopi_url', $this->appConfig->getAppValue('wopi_url'));
		$template->assign('edit_groups', $this->appConfig->getAppValue('edit_groups'));
		$template->assign('doc_format', $this->appConfig->getAppValue('doc_format'));
		$template->assign('test_wopi_url', $this->appConfig->getAppValue('test_wopi_url'));
		$template->assign('test_server_groups', $this->appConfig->getAppValue('test_server_groups'));
		$template->assign('canonical_webroot', $this->appConfig->getAppValue('canonical_webroot'));
		$template->assign('menu_option', $this->appConfig->getAppValue('menu_option'));
		$template->assign('encryption_enabled', $this->appConfig->encryptionEnabled() ? 'true' : 'false');
		$template->assign('masterkey_encryption_enabled', $this->appConfig->masterEncryptionEnabled() ? 'true' : 'false');
		$template->assign('secure_view_allowed', $this->appConfig->enterpriseFeaturesEnabled() ? 'true' : 'false');
		$template->assign('secure_view_option', ($this->appConfig->secureViewOptionEnabled() && $this->appConfig->enterpriseFeaturesEnabled()) ? 'true' : 'false');
		$template->assign('secure_view_open_action_default', $this->appConfig->secureViewOpenActionDefaultEnabled() ? 'true' : 'false');
		$template->assign('secure_view_has_watermark_default', $this->appConfig->secureViewHasWatermarkDefaultEnabled() ? 'true' : 'false');
		$template->assign('secure_view_can_print_default', $this->appConfig->secureViewCanPrintDefaultEnabled() ? 'true' : 'false');
		$template->assign('watermark_text', $this->appConfig->getAppValue('watermark_text'));

		return $template;
	}
}
