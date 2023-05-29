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

namespace OCA\Richdocuments\AppInfo;

use OCA\Richdocuments\DiscoveryService;
use OCA\Richdocuments\FileService;
use OCA\Richdocuments\DocumentService;
use OCA\Richdocuments\Controller\WopiController;
use OCA\Richdocuments\Controller\DocumentController;
use OCA\Richdocuments\Controller\SettingsController;
use OCA\Richdocuments\Controller\WebAssetController;
use OCA\Richdocuments\AppConfig;
use OCP\AppFramework\App;
use OC\AppFramework\Utility\SimpleContainer;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OCP\IUser;
use OCP\Share;
use OCP\Util;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('richdocuments', $urlParams);

		$this->registerServices();
	}

	private function registerServices() {
		$container = $this->getContainer();
		$server = $container->getServer();

		/**
		 * Controllers
		 */
		$container->registerService('DocumentController', function (SimpleContainer $c) use ($server) {
			return new DocumentController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('CoreConfig'),
				$c->query('AppConfig'),
				$c->query('L10N'),
				$c->query('UserId'),
				$c->query('ICacheFactory'),
				$c->query('Logger'),
				$c->query('DocumentService'),
				$c->query('DiscoveryService'),
				$c->query('OCP\App\IAppManager'),
				$server->getGroupManager(),
				$server->getUserManager(),
				$server->getPreviewManager()
			);
		});
		$container->registerService('WopiController', function (SimpleContainer $c) use ($server) {
			return new WopiController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('CoreConfig'),
				$c->query('AppConfig'),
				$c->query('L10N'),
				$c->query('Logger'),
				$c->query('FileService'),
				$server->getRootFolder(),
				$server->getURLGenerator(),
				$server->getUserManager()
			);
		});
		$container->registerService('SettingsController', function (SimpleContainer $c) {
			return new SettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('L10N'),
				$c->query('AppConfig'),
				$c->query('UserId')
			);
		});

		$container->registerService("WebAssetController", function (SimpleContainer $c) {
			return new WebAssetController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Logger')
			);
		});

		$container->registerService('AppConfig', function (SimpleContainer $c) use ($server) {
			$coreConfig = $c->query('CoreConfig');
			$appManager = $server->getAppManager();
			$licenseManager = $server->getLicenseManager();

			return new AppConfig(
				$coreConfig,
				$appManager,
				$licenseManager
			);
		});

		$container->registerService('DocumentService', function (SimpleContainer $c) use ($server) {
			$rootFolder = $server->getRootFolder();
			$coreConfig = $c->query('CoreConfig');
			return new DocumentService(
				$rootFolder,
				$coreConfig
			);
		});

		$container->registerService('DiscoveryService', function (SimpleContainer $c) use ($server) {
			$config = $c->query('AppConfig');
			$logger = $server->getLogger();
			$l10n = $server->getL10N($c->query('AppName'));
			$cacheFactory = $c->query('ICacheFactory');
			$httpClient = $server->getHTTPClientService();
			return new DiscoveryService(
				$config,
				$logger,
				$l10n,
				$cacheFactory,
				$httpClient
			);
		});

		$container->registerService('FileService', function (SimpleContainer $c) use ($server) {
			return new FileService(
				$c->query('Logger'),
				$c->query('AppConfig'),
				$server->getUserManager(),
				$server->getUserSession(),
				$server->getEventDispatcher(),
				$server->getRootFolder()
			);
		});

		/**
		 * Core
		 */
		$container->registerService('Logger', function (SimpleContainer $c) use ($server) {
			return $server->getLogger();
		});
		$container->registerService('CoreConfig', function (SimpleContainer $c) use ($server) {
			return $server->getConfig();
		});
		$container->registerService('L10N', function (SimpleContainer $c) use ($server) {
			return $server->getL10N($c->query('AppName'));
		});
		$container->registerService('UserId', function (SimpleContainer $c) use ($server) {
			/** @var IUser|null $user */
			$user = $server->getUserSession()->getUser();
			$uid = $user === null ? '' : $user->getUID();
			return $uid;
		});
		$container->registerService('ICacheFactory', function (SimpleContainer $c) use ($server) {
			return $server->getMemCacheFactory();
		});
	}

	public function registerScripts() {
		$container = $this->getContainer();

		if ($this->isUserAllowedToUseCollabora()) {
			$menuOption = $container->getServer()->getConfig()->getAppValue('richdocuments', 'menu_option');
			if ($menuOption !== 'false') {
				$navigationEntry = function () use ($container) {
					return [
						'id' => 'richdocuments_index',
						'order' => 2,
						'href' => $container->query('ServerContainer')->getURLGenerator()->linkToRoute('richdocuments.document.index'),
						'icon' => $container->query('ServerContainer')->getURLGenerator()->imagePath('richdocuments', 'app.svg'),
						'name' => $container->query('L10N')->t('Office')
					];
				};
				$container->getServer()->getNavigationManager()->add($navigationEntry);
			}

			//Script for registering file actions
			$container->getServer()->getEventDispatcher()->addListener(
				'OCA\Files::loadAdditionalScripts',
				[$this, 'addViewerScripts']
			);

			$appConfig = $container->query(AppConfig::class);
			if ($appConfig->secureViewOptionEnabled() && $appConfig->enterpriseFeaturesEnabled()) {
				$container->getServer()->getEventDispatcher()->addListener(
					'OCA\Files::loadAdditionalScripts',
					function () {
						Util::addScript('richdocuments', 'viewer/shareoptions');
					}
				);
			}

			if (\class_exists('\OC\Files\Type\TemplateManager')) {
				$manager = \OC_Helper::getFileTemplateManager();
				$appPath = \OC::$server->getAppManager()->getAppPath('richdocuments');

				$manager->registerTemplate('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $appPath . '/assets/docxtemplate.docx');
				$manager->registerTemplate('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $appPath . '/assets/xlsxtemplate.xlsx');
				$manager->registerTemplate('application/vnd.openxmlformats-officedocument.presentationml.presentation', $appPath . '/assets/pptxtemplate.pptx');
			}
		}

		if ($this->publicLinksAllowedToUseCollabora()) {
			Util::connectHook(Share::class, "share_link_access", $this, "addViewerScripts");
		}

		Util::connectHook('\OCP\Config', 'js', $this, 'addConfigScripts');
	}

	public function addViewerScripts() {
		Util::addScript('richdocuments', 'viewer/viewer');
		Util::addStyle('richdocuments', 'viewer/odfviewer');
	}

	/**
	 * @param mixed $array passed by reference when dispatching \OCP\Config 'js' hook
	 */
	public function addConfigScripts($array) {
		$appConfig = $this->getContainer()->query(AppConfig::class);
		$array['array']['oc_appconfig']['richdocuments'] = [
			'defaultShareAttributes' => [
				// is secure view is enabled for read-only shares, user cannot download by default
				'secureViewHasWatermark' => $appConfig->secureViewHasWatermarkDefaultEnabled(),
				'secureViewCanPrint' => $appConfig->secureViewCanPrintDefaultEnabled(),
			],
			'secureViewAllowed' => $appConfig->secureViewOptionEnabled(),
			'secureViewOpenActionDefault' => $appConfig->secureViewOpenActionDefaultEnabled(),
			'openInNewTab' => $appConfig->openInNewTabEnabled()
		];
	}

	private function publicLinksAllowedToUseCollabora() {
		// FIXME: some more rules? additional collabora flag?
		return ($this->getContainer()->getServer()->getConfig()->getAppValue('core', 'shareapi_allow_links', 'yes') == 'yes');
	}

	private function isUserAllowedToUseCollabora() {
		// no user -> no
		/** @var \OCP\IUserSession|null $userSession */
		$userSession = $this->getContainer()->getServer()->getUserSession();
		if ($userSession === null || !$userSession->isLoggedIn()) {
			return false;
		}
		// no group set -> all users are allowed
		$groupName = $this->getContainer()->getServer()->getConfig()->getSystemValue('collabora_group', null);
		if ($groupName === null) {
			return true;
		}
		// group unknown -> error and allow nobody
		$group = $this->getContainer()->getServer()->getGroupManager()->get($groupName);
		if ($group === null) {
			$this->getContainer()->getServer()->getLogger()->error("Group is unknown $groupName", ['app' => 'collabora']);
			return false;
		}

		return $group->inGroup($userSession->getUser());
	}
}
