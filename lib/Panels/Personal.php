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

class Personal implements ISettings {
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
		$uid = $this->userSession->getUser()->getUID();

		$zoteroAPIPrivateKey = $this->appConfig->getUserValue($uid, 'zoteroAPIPrivateKey');

		$template = new Template('richdocuments', 'settings-personal');
		if ($zoteroAPIPrivateKey !== null) {
			$template->assign('zoteroAPIPrivateKey', $zoteroAPIPrivateKey);
		}
		return $template;
	}
}
