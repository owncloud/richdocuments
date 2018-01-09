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

use \OCP\AppFramework\App;

use \OCA\Richdocuments\Controller\SessionController;
use \OCA\Richdocuments\Controller\DocumentController;
use \OCA\Richdocuments\Controller\SettingsController;
use \OCA\Richdocuments\AppConfig;
use OCP\IContainer;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Migration\ISimpleMigration;

class Application extends App {
	public function __construct (array $urlParams = array()) {
		parent::__construct('richdocuments', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('SessionController', function($c) {
			/** @var IContainer $c */
			return new SessionController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('Logger'),
				$c->query('UserId')
			);
		});
		$container->registerService('DocumentController', function($c) {
			/** @var IContainer $c */
			return new DocumentController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('CoreConfig'),
				$c->query('AppConfig'),
				$c->query('L10N'),
				$c->query('UserId'),
				$c->query('ICacheFactory'),
				$c->query('Logger')
			);
		});
		$container->registerService('SettingsController', function($c) {
			/** @var IContainer $c */
			return new SettingsController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('L10N'),
				$c->query('AppConfig'),
				$c->query('UserId')
			);
		});

		$container->registerService('AppConfig', function($c) {
			/** @var IContainer $c */
			return new AppConfig(
				$c->query('CoreConfig')
			);
		});

		/**
		 * Core
		 */
		$container->registerService('Logger', function($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getLogger();
		});
		$container->registerService('CoreConfig', function($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getConfig();
		});
		$container->registerService('L10N', function($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getL10N($c->query('AppName'));
		});
		$container->registerService('UserId', function($c) {
			/** @var IContainer $c */
			/** @var IUser $user */
			$user = $c->query('ServerContainer')->getUserSession()->getUser();
			$uid = is_null($user) ? '' : $user->getUID();
			return $uid;
		});
		$container->registerService('ICacheFactory', function($c) {
			/** @var IContainer $c */
			return $c->query('ServerContainer')->getMemCacheFactory();
		});
	}

	public function isUserAllowedToUseCollabora() {
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
