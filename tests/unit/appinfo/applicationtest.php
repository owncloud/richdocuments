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
namespace OCA\Richdocuments\Tests\unit;

use \OC_Hook;
use OCA\Richdocuments\AppInfo\Application;

/**
 * Class ApplicationTest
 *
 * @group DB
 *
 * @package OCA\Richdocuments\Tests\Unit
 */
class ApplicationTest extends \Test\TestCase {

	/**
	 * @var Application
	 */
	private $app;

	public function setUp(){
		parent::setUp();
		$this->app = new Application();
	}

	public function tearDown() {
		$this->app->getContainer()->getServer()->getConfig()->deleteAppValue('core', 'shareapi_allow_links');
		OC_Hook::clear();
		parent::tearDown();
	}

	private function tearDownUser() {
		$userManager = \OC::$server->getUserManager();
		if ($userManager->userExists('test')) {
			$userManager->get('test')->delete();
		}

		$groupManager = \OC::$server->getGroupManager();
		if ($groupManager->groupExists('test')) {
			$groupManager->get('test')->delete();
		}

		$this->app->getContainer()->getServer()->getConfig()->deleteSystemValue('collabora_group');
	}

	private function setUpUser($isCollaboraUser) {
		$userManager = \OC::$server->getUserManager();
		if ($userManager->userExists('test')) {
			$userManager->get('test')->delete();
		}
		$user = $userManager->createUser('test', 'test');

		$groupManager = \OC::$server->getGroupManager();
		if ($groupManager->groupExists('test')) {
			$groupManager->get('test')->delete();
		}
		$group = $groupManager->createGroup('test');
		$group->addUser($user);

		$this->app->getContainer()->getServer()->getUserSession()->login('test', 'test');
		if ($isCollaboraUser) {
			$this->app->getContainer()->getServer()->getConfig()->setSystemValue('collabora_group', $group->getGID());
		} else {
			$this->app->getContainer()->getServer()->getConfig()->setSystemValue('collabora_group', null);
		}
	}

	/**
	 * Ensure that hook to register viewer scripts is triggered when user opens public share link
	 */
	public function testRegisterForPublicLinksAllowedByDefault() {
		$this->app->getContainer()->getServer()->getConfig()->deleteAppValue('core', 'shareapi_allow_links');
		$this->app->registerScripts();

		$hooks = OC_Hook::getHooks();

		$this->assertArrayHasKey("OCP\Share", $hooks);
		$this->assertArrayHasKey("share_link_access", $hooks["OCP\Share"]);
		$this->assertEquals("addViewerScripts", $hooks["OCP\Share"]["share_link_access"][0]["name"]);
		$this->assertEquals("OCA\Richdocuments\HookHandler", $hooks["OCP\Share"]["share_link_access"][0]["class"]);
	}

	/**
	 * Ensure that hook to register viewer scripts is triggered when user opens public share link
	 */
	public function testRegisterForPublicLinksAllowed() {
		$this->app->getContainer()->getServer()->getConfig()->setAppValue('core', 'shareapi_allow_links', 'yes');
		$this->app->registerScripts();

		$hooks = OC_Hook::getHooks();

		$this->assertArrayHasKey("OCP\Share", $hooks);
		$this->assertArrayHasKey("share_link_access", $hooks["OCP\Share"]);
	}

	private function countListeners($name) {
		$listeners = $this->app->getContainer()->getServer()->getEventDispatcher()->getListeners();
		if (array_key_exists($name, $listeners)) {
			return count($listeners[$name]);
		}
		return 0;
	}

	/**
	 * Ensure that hook is not registered for viewer scripts if disallowed
	 */
	public function testRegisterForPublicLinksNotAllowed() {
		$this->app->getContainer()->getServer()->getConfig()->setAppValue('core', 'shareapi_allow_links', 'no');
		$this->app->registerScripts();

		$hooks = OC_Hook::getHooks();

		$this->assertArrayNotHasKey("OCP\Share", $hooks);
	}

	/**
	 * Ensure viewer scripts are not registered for non-authenticated user
	 */
	public function testRegisterForUserAuthUnauthenticated() {
		$listenersBefore = $this->countListeners('OCA\Files::loadAdditionalScripts');

		$this->app->registerScripts();

		$listenersAfter = $this->countListeners('OCA\Files::loadAdditionalScripts');
		$this->assertEquals($listenersAfter, $listenersBefore);
	}

	/**
	 * Ensure viewer scripts are registered for authenticated user when there are no collabora groups specified
	 */
	public function testRegisterForUserAuthAllowed() {
		$this->setUpUser(false);
		$listenersBefore = $this->countListeners('OCA\Files::loadAdditionalScripts');

		$this->app->registerScripts();

		$listenersAfter = $this->countListeners('OCA\Files::loadAdditionalScripts');
		$this->assertEquals($listenersAfter, $listenersBefore + 1);

		$this->tearDownUser();
	}

	/**
	 * Ensure viewer scripts are registered for authenticated user when there are no collabora groups specified
	 */
	public function testRegisterForUserAuthAllowedWithGroup() {
		$this->setUpUser(true);

		$listenersBefore = $this->app->getContainer()->getServer()->getEventDispatcher()->getListeners()['OCA\Files::loadAdditionalScripts'];

		$this->app->registerScripts();

		$listenersAfter = $this->app->getContainer()->getServer()->getEventDispatcher()->getListeners()['OCA\Files::loadAdditionalScripts'];
		$this->assertCount(count($listenersBefore)+1, $listenersAfter);

		$this->tearDownUser();
	}
}
