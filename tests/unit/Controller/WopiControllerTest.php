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
namespace OCA\Richdocuments\Tests\Controller;

use OCA\Richdocuments\Controller\WopiController;
use OCA\Richdocuments\AppConfig;
use OCA\Richdocuments\FileService;

use OCP\IRequest;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\Files\IRootFolder;

/**
 * Class WopiControllerTest
 *
 * @group DB
 *
 * @package OCA\Richdocuments\Tests\Controller
 */
class WopiControllerTest extends \Test\TestCase {
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
	 * @var ILogger
	 */
	private $logger;
	
	/**
	 * @var FileService
	 */
	private $fileService;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var WopiController
	 */
	private $wopiController;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->settings = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(AppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->wopiController = new WopiController(
			'richdocuments',
			$this->request,
			$this->settings,
			$this->appConfig,
			$this->l10n,
			$this->logger,
			$this->fileService,
			$this->rootFolder,
			$this->urlGenerator,
			$this->userManager
		);
	}

	public function testConstructor() {
		// NOTE: Wopi controller implements protocol is similar fashion to https://github.com/owncloud/wopi
		//  and code heavily overlaps, it is ok to test only constructor for the moment
		$this->assertInstanceOf(WopiController::class, $this->wopiController);
	}
}
