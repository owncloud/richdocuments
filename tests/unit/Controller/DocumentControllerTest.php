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
namespace OCA\Richdocuments\Tests\Controller;

use \OCA\Richdocuments\Controller\DocumentController;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use \OCP\IRequest;
use \OCP\IConfig;
use \OCA\Richdocuments\AppConfig;
use \OCP\IL10N;
use \OCP\ICacheFactory;
use \OCP\ILogger;
use \OCA\Richdocuments\Storage;
use OCP\IUserManager;
use phpDocumentor\Reflection\Types\This;

/**
 * Class DocumentControllerTest
 *
 * @package OCA\Richdocuments\Tests\Controller
 */
class DocumentControllerTest extends \Test\TestCase {

	/**
	 * @var IRequest
	 */
	private $request;
	/**
	 * @var IConfig
	 */
	private $settings;
	/**
	 * @var AppConfig
	 */
	private $appConfig;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var ICacheFactory
	 */
	private $cache;
	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var Storage
	 */
	private $storage;
	/**
	 * @var IAppManager
	 */
	private $appManager;
	/**
	 * @var IGroupManager
	 */
	private $groupManager;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var DocumentController
	 */
	private $documentController;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->settings = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(AppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->cache = $this->createMock(ICacheFactory::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->storage = $this->createMock(Storage::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->documentController = new DocumentController(
			'richdocuments',
			$this->request,
			$this->settings,
			$this->appConfig,
			$this->l10n,
			'test',
			$this->cache,
			$this->logger,
			$this->storage,
			$this->appManager,
			$this->groupManager,
			$this->userManager
		);
	}

	public function testConstructor() {
		$this->assertInstanceOf(DocumentController::class, $this->documentController);
	}
}
