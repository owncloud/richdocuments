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
use \OCP\IRequest;
use \OCP\IConfig;
use \OCA\Richdocuments\AppConfig;
use \OCP\IL10N;
use \OCP\ICacheFactory;
use \OCP\ILogger;
use \OCA\Richdocuments\Storage;

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

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->settings = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(AppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->cache = $this->createMock(ICacheFactory::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->storage = $this->createMock(Storage::class);
	}

	public function testConstructor() {
		$documentController = new DocumentController(
			'richdocuments',
			$this->request,
			$this->settings,
			$this->appConfig,
			$this->l10n,
			'test',
			$this->cache,
			$this->logger,
			$this->storage
		);
		$this->assertInstanceOf(DocumentController::class, $documentController);
	}
}
