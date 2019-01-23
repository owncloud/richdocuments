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

namespace OCA\Richdocuments\AppInfo;

use OCA\Richdocuments\Storage;
use \OCP\AppFramework\App;

use \OCA\Richdocuments\Controller\DocumentController;
use \OCA\Richdocuments\Controller\SettingsController;
use \OCA\Richdocuments\AppConfig;
use OCP\IContainer;
use OCP\IUser;
use OCP\Share;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('richdocuments', $urlParams);

		$this->registerServices();
	}

	private function registerServices() {
		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('DocumentController', function ($c) {
			$storage = new Storage();
			/** @var IContainer $c */
			return new DocumentController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('CoreConfig'),
				$c->query('AppConfig'),
				$c->query('L10N'),
				$c->query('UserId'),
				$c->query('ICacheFactory'),
				$c->query('Logger'),
				$storage
			);
		});
		$container->registerService('SettingsController', function ($c) {
			/** @var IContainer $c */
			return new SettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('L10N'),
				$c->query('AppConfig'),
				$c->query('UserId')
			);
		});

		$container->registerService('AppConfig', function ($c) {
			/** @var IContainer $c */
			return new AppConfig(
				$c->query('CoreConfig')
			);
		});

		/**
		 * Core
		 */
		$container->registerService('Logger', function ($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getLogger();
		});
		$container->registerService('CoreConfig', function ($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getConfig();
		});
		$container->registerService('L10N', function ($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getL10N($c->query('AppName'));
		});
		$container->registerService('UserId', function ($c) {
			/** @var IContainer $c */
			/** @var IUser $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = $user === null ? '' : $user->getUID();
			return $uid;
		});
		$container->registerService('ICacheFactory', function ($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getMemCacheFactory();
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
				function () {
					\OCP\Util::addScript('richdocuments', 'viewer/viewer');
					\OCP\Util::addStyle('richdocuments', 'viewer/odfviewer');
				}
			);

			$secureViewOption = $container->getServer()->getConfig()->getAppValue('richdocuments', 'secure_view_option');

			if ($secureViewOption === 'true') {
				$container->getServer()->getEventDispatcher()->addListener(
					'OCA\Files::loadAdditionalScripts',
					function () {
						\OCP\Util::addScript('richdocuments', 'viewer/extrapermissions');
					}
				);
			}

			if (\class_exists('\OC\Files\Type\TemplateManager')) {
				$manager = \OC_Helper::getFileTemplateManager();

				$manager->registerTemplate('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'apps/richdocuments/assets/docxtemplate.docx');
				$manager->registerTemplate('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'apps/richdocuments/assets/xlsxtemplate.xlsx');
				$manager->registerTemplate('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'apps/richdocuments/assets/pptxtemplate.pptx');
			}
		}

		if ($this->publicLinksAllowedToUseCollabora()) {
			\OCP\Util::connectHook(Share::class, "share_link_access", \OCA\Richdocuments\HookHandler::class, "addViewerScripts");
		}
	}

	private function publicLinksAllowedToUseCollabora() {
		// FIXME: some more rules? additional collabora flag?
		return ($this->getContainer()->getServer()->getConfig()->getAppValue('core', 'shareapi_allow_links', 'yes') == 'yes');
	}

	private function isUserAllowedToUseCollabora() {
		// no user -> no
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
