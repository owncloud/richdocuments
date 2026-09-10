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

use OCA\Richdocuments\Controller\DocumentController;
use OCA\Richdocuments\DocumentService;
use OCA\Richdocuments\DiscoveryService;
use OCA\Richdocuments\FederationService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IConfig;
use OCA\Richdocuments\AppConfig;
use OCP\IL10N;
use OCP\ICacheFactory;
use OCP\ILogger;
use OCP\IUserManager;

/**
 * Class DocumentControllerTest
 *
 * @group DB
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
	 * @var DocumentService
	 */
	private $documentService;
	/**
	 * @var DiscoveryService
	 */
	private $discoveryService;
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
	 * @var IPreview
	 */
	private $previewManager;
	/**
	 * @var INavigationManager
	 */
	private $navigationManager;
	/**
	 * @var FederationService
	 */
	private $federationService;
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
		$this->documentService = $this->createMock(DocumentService::class);
		$this->discoveryService = $this->createMock(DiscoveryService::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->previewManager = $this->createMock(IPreview::class);
		$this->navigationManager = $this->createMock(INavigationManager::class);
		$this->federationService = $this->createMock(FederationService::class);

		$this->documentController = new DocumentController(
			'richdocuments',
			$this->request,
			$this->settings,
			$this->appConfig,
			$this->l10n,
			$this->logger,
			$this->documentService,
			$this->discoveryService,
			$this->appManager,
			$this->groupManager,
			$this->userManager,
			$this->previewManager,
			$this->navigationManager,
			$this->federationService
		);
	}

	public function testConstructor() {
		$this->assertInstanceOf(DocumentController::class, $this->documentController);
	}

	/**
	 * Tests different filenames on create
	 *
	 * @dataProvider invalidFilenameProvider
	 * @param $filename string
	 */
	public function testCreateWithInvalidFilename(string $filename) {
		$dir = "/";
		$mimetype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";

		$this->request
			->expects($this->exactly(3))
			->method('getParam')
			->withConsecutive(
				['mimetype'],
				['filename'],
				['dir'],
			)
			->willReturnOnConsecutiveCalls(
				$mimetype,
				$filename,
				$dir,
			);

		$this->assertEquals(
			$this->documentController->create(),
			[
			'status' => 'error',
			'message' => $this->l10n->t('Invalid filename'),
		]
		);
	}

	public function invalidFilenameProvider(): array {
		return [
			["filename with\t tab"],
			["filename with / slash"]
		];
	}

	/**
	 * The server parameter ends up as a navigation target in the browser, so
	 * federated() has to reject everything that is not an absolute http(s) URL.
	 *
	 * @dataProvider invalidServerProvider
	 * @param $server mixed
	 */
	public function testFederatedRejectsInvalidServer($server) {
		// the request must not be processed any further
		$this->documentService
			->expects($this->never())
			->method('getDocumentByFederatedToken');

		$response = $this->documentController->federated('sharetoken', '/document.odt', $server, 'accesstoken');

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('error', $response->getTemplateName());
	}

	public function invalidServerProvider(): array {
		return [
			'javascript scheme' => ['javascript:alert(document.domain)'],
			'javascript scheme uppercase' => ['JaVaScRiPt:alert(document.domain)'],
			'data scheme' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
			'scheme relative' => ['//evil.tld'],
			'scheme relative with path' => ['//evil.tld/owncloud'],
			'relative path' => ['/index.php/apps/files'],
			'no scheme' => ['remote.example.com'],
			'scheme without host' => ['https://'],
			'empty' => [''],
			'null' => [null],
			'not a string' => [42],
		];
	}

	/**
	 * A well formed remote server must pass the validation - in particular one
	 * with a path, ownCloud can be installed in a subdirectory.
	 *
	 * @dataProvider validServerProvider
	 * @param $server string
	 */
	public function testFederatedAcceptsValidServer(string $server) {
		// reaching the document lookup means the server was accepted
		$this->documentService
			->expects($this->once())
			->method('getDocumentByFederatedToken')
			->with('sharetoken', '/document.odt')
			->willReturn(null);

		$response = $this->documentController->federated('sharetoken', '/document.odt', $server, 'accesstoken');

		// the document cannot be resolved, so this is still an error response
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('error', $response->getTemplateName());
	}

	public function validServerProvider(): array {
		return [
			'https' => ['https://remote.example.com'],
			'http' => ['http://remote.example.com'],
			'trailing slash' => ['https://remote.example.com/'],
			'subdirectory install' => ['https://remote.example.com/owncloud'],
			'with port' => ['https://remote.example.com:8443/owncloud'],
			'uppercase scheme' => ['HTTPS://remote.example.com'],
		];
	}

	/**
	 * The validated server has to reach the template, that is where the JS picks
	 * it up now instead of reading it from the URL.
	 *
	 * @group DB
	 */
	public function testFederatedPassesServerToTemplate() {
		$server = 'https://remote.example.com/owncloud';

		$this->documentService
			->method('getDocumentByFederatedToken')
			->willReturn($this->documentInfo());
		$this->federationService
			->method('getWopiForToken')
			->with($server, 'accesstoken')
			->willReturn(['editor' => 'alice@remote.example.com', 'attributes' => 1]);
		$this->settings->method('getUserValue')->willReturn('en');
		$this->mockDiscovery();

		$response = $this->documentController->federated('sharetoken', '/document.odt', $server, 'accesstoken');

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('documents', $response->getTemplateName());
		$this->assertEquals($server, $response->getParams()['return_to_server']);
	}

	/**
	 * A public link is never opened from a remote server, so it must not carry a
	 * return_to_server value that the JS would navigate to.
	 *
	 * @group DB
	 */
	public function testPublicEmitsNoReturnToServer() {
		$this->documentService
			->method('getDocumentByShareToken')
			->willReturn($this->documentInfo());
		$this->settings->method('getUserValue')->willReturn('en');
		$this->mockDiscovery();

		$response = $this->documentController->public('sharetoken', null);

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('documents', $response->getTemplateName());
		$params = $response->getParams();
		$this->assertArrayHasKey('return_to_server', $params);
		$this->assertSame('', $params['return_to_server']);
	}

	/**
	 * public() must not accept a server at all, so that no request parameter can
	 * ever influence where the editor returns to.
	 */
	public function testPublicHasNoServerParameter() {
		$parameters = (new \ReflectionMethod(DocumentController::class, 'public'))->getParameters();

		$names = \array_map(static function (\ReflectionParameter $parameter) {
			return $parameter->getName();
		}, $parameters);

		$this->assertEquals(['shareToken', 'fileId'], $names);
	}

	/**
	 * Minimal document index as returned by the DocumentService.
	 */
	private function documentInfo(): array {
		return [
			'name' => 'document.odt',
			'fileid' => 1234,
			'path' => '/document.odt',
			'owner' => 'alice',
			'version' => 0,
			'mimetype' => 'application/vnd.oasis.opendocument.text',
			'allowEdit' => false,
		];
	}

	/**
	 * Let the discovery return a usable Collabora Online endpoint.
	 */
	private function mockDiscovery(): void {
		$this->discoveryService
			->method('getWopiSrc')
			->willReturn([
				'action' => 'view',
				'urlsrc' => 'https://collabora.example.com/browser/abc/cool.html?',
			]);
		$this->discoveryService
			->method('getWopiUrl')
			->willReturn('https://collabora.example.com:9980');
	}
}
