<?php
/**
 * @author Victor Dubiniuk <victor.dubiniuk@gmail.com>
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
namespace OCA\Richdocuments\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;

use OCA\Richdocuments\AppConfig;

class SettingsController extends Controller {
	/** @var IL10N */
	private $l10n;

	/** @var AppConfig */
	private $appConfig;

	/** @var IUserSession */
	private $userSession;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param AppConfig $appConfig
	 * @param IUserSession $userSession
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IL10N $l10n,
		AppConfig $appConfig,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->appConfig = $appConfig;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getWopiSettings() {
		return [
			'doc_format' => $this->appConfig->getAppValue('doc_format'),
			'wopi_url' => $this->appConfig->getAppValue('wopi_url'),
			'test_wopi_url' => $this->appConfig->getAppValue('test_wopi_url'),
			'test_server_groups' => $this->appConfig->getAppValue('test_server_groups'),
		];
	}

	/**
	 * @AdminRequired
	 *
	 * @param array $settings
	 * @return DataResponse
	 */
	public function setAdminSettings($settings) {
		$message = $this->l10n->t('Saved');

		if (isset($settings['wopi_url'])) {
			$this->appConfig->setAppValue('wopi_url', $settings['wopi_url']);

			$colon = \strpos($settings['wopi_url'], ':', 0);
			if (\OC::$server->getRequest()->getServerProtocol() !== \substr($settings['wopi_url'], 0, $colon)) {
				$message = $this->l10n->t('Saved with error: Collabora Online should use the same protocol as the server installation.');
			}
		}

		if (isset($settings['edit_groups'])) {
			$this->appConfig->setAppValue('edit_groups', $settings['edit_groups']);
		}

		if (isset($settings['doc_format'])) {
			$this->appConfig->setAppValue('doc_format', $settings['doc_format']);
		}

		if (isset($settings['test_wopi_url'])) {
			$this->appConfig->setAppValue('test_wopi_url', $settings['test_wopi_url']);
		}

		if (isset($settings['test_server_groups'])) {
			$this->appConfig->setAppValue('test_server_groups', $settings['test_server_groups']);
		}

		if (isset($settings['canonical_webroot'])) {
			$this->appConfig->setAppValue('canonical_webroot', $settings['canonical_webroot']);
		}

		if (isset($settings['menu_option'])) {
			$this->appConfig->setAppValue('menu_option', $settings['menu_option']);
		}

		if (isset($settings['secure_view_option'])) {
			$this->appConfig->setAppValue('secure_view_option', $settings['secure_view_option']);
		}

		if (isset($settings['secure_view_open_action_default'])) {
			$this->appConfig->setAppValue('secure_view_open_action_default', $settings['secure_view_open_action_default']);
		}

		if (isset($settings['secure_view_can_print_default'])) {
			$this->appConfig->setAppValue('secure_view_can_print_default', $settings['secure_view_can_print_default']);
		}

		if (isset($settings['secure_view_has_watermark_default'])) {
			$this->appConfig->setAppValue('secure_view_has_watermark_default', $settings['secure_view_has_watermark_default']);
		}

		if (isset($settings['watermark_text'])) {
			$this->appConfig->setAppValue('watermark_text', $settings['watermark_text']);
		}

		if (isset($settings['zotero'])) {
			$this->appConfig->setAppValue('zotero', $settings['zotero']);
		}

		$richMemCache = \OC::$server->getMemCacheFactory()->create('richdocuments');
		$richMemCache->clear('discovery.xml');

		// FIXME: this is a workaround, translations in messages should be handled by the client
		return new DataResponse([
			'status' => 'success',
			'data' => ['message' => (string) $message]
		]);
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 *
	 * @param array $settings
	 * @return DataResponse
	 */
	public function setPersonalSettings($settings) {
		$message = $this->l10n->t('Saved');
		
		$uid = $this->userSession->getUser()->getUID();

		if (isset($settings['zoteroAPIPrivateKey'])) {
			$this->appConfig->setUserValue($uid, 'zoteroAPIPrivateKey', $settings['zoteroAPIPrivateKey']);
		}

		// FIXME: this is a workaround, translations in messages should be handled by the client
		return new DataResponse(
			[
				'status' => 'success',
				'data' => ['message' => (string) $message]
			]
		);
	}
}
