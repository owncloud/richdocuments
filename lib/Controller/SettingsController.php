<?php
/**
 * ownCloud - Richdocuments App
 *
 * @author Victor Dubiniuk
 * @copyright 2014 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Richdocuments\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;
use \OCP\AppFramework\Http\TemplateResponse;

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
	 * @NoAdminRequired
	 * @UseSession
	 *
	 * @return DataResponse
	 */
	public function setAdminSettings($wopi_url, $edit_groups, $doc_format, $test_wopi_url, $test_server_groups, $external_apps, $canonical_webroot, $menu_option, $secure_view_option, $secure_view_open_action_default, $secure_view_can_print_default, $secure_view_has_watermark_default, $watermark_text, $zotero) {
		$message = $this->l10n->t('Saved');

		if ($wopi_url !== null) {
			$this->appConfig->setAppValue('wopi_url', $wopi_url);

			$colon = \strpos($wopi_url, ':', 0);
			if (\OC::$server->getRequest()->getServerProtocol() !== \substr($wopi_url, 0, $colon)) {
				$message = $this->l10n->t('Saved with error: Collabora Online should use the same protocol as the server installation.');
			}
		}

		if ($edit_groups !== null) {
			$this->appConfig->setAppValue('edit_groups', $edit_groups);
		}

		if ($doc_format !== null) {
			$this->appConfig->setAppValue('doc_format', $doc_format);
		}

		if ($test_wopi_url !== null) {
			$this->appConfig->setAppValue('test_wopi_url', $test_wopi_url);
		}

		if ($test_server_groups !== null) {
			$this->appConfig->setAppValue('test_server_groups', $test_server_groups);
		}

		if ($canonical_webroot !== null) {
			$this->appConfig->setAppValue('canonical_webroot', $canonical_webroot);
		}

		if ($menu_option !== null) {
			$this->appConfig->setAppValue('menu_option', $menu_option);
		}

		if ($secure_view_option !== null) {
			$this->appConfig->setAppValue('secure_view_option', $secure_view_option);
		}

		if ($secure_view_open_action_default !== null) {
			$this->appConfig->setAppValue('secure_view_open_action_default', $secure_view_open_action_default);
		}

		if ($secure_view_can_print_default !== null) {
			$this->appConfig->setAppValue('secure_view_can_print_default', $secure_view_can_print_default);
		}

		if ($secure_view_has_watermark_default !== null) {
			$this->appConfig->setAppValue('secure_view_has_watermark_default', $secure_view_has_watermark_default);
		}

		if ($watermark_text !== null) {
			$this->appConfig->setAppValue('watermark_text', $watermark_text);
		}

		if ($zotero !== null) {
			$this->appConfig->setAppValue('zotero', $zotero);
		}

		$richMemCache = \OC::$server->getMemCacheFactory()->create('richdocuments');
		$richMemCache->clear('discovery.xml');

		return new DataResponse( [
			'status' => 'success',
			'data' => ['message' => (string) $message]
		]);
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 *
	 * @param string $zoteroAPIPrivateKey
	 * @return DataResponse
	 */
	public function setPersonalSettings($zoteroAPIPrivateKey) {
		$message = $this->l10n->t('Saved');
		
		$uid = $this->userSession->getUser()->getUID();

		if ($zoteroAPIPrivateKey !== null) {
			$this->appConfig->setUserValue($uid, 'zoteroAPIPrivateKey', $zoteroAPIPrivateKey);
		}

		return new DataResponse(
			[
				'status' => 'success',
				'data' => ['message' => (string) $message]
			]
		);
	}
}
