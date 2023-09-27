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

use OCA\Richdocuments\Controller\DocumentRevisionController;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Class DocumentRevisionControllerTest
 *
 * @group DB
 *
 * @package OCA\Richdocuments\Tests\Controller
 */
class DocumentRevisionControllerTest extends \Test\TestCase {
	/**
	 * @var IUserSession The user session service
	 */
	private $userSession;

	/**
	 * @var IRootFolder The root folder service
	 */
	private $rootFolder;

	/**
	 * @var IRequest
	 */
	private $request;
	
	/**
	 * @var DocumentRevisionController
	 */
	private $documentRevisionController;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$this->documentRevisionController = new DocumentRevisionController(
			'richdocuments',
			$this->request,
			$this->userSession,
			$this->rootFolder
		);
	}

	public function testConstructor() {
		$this->assertInstanceOf(DocumentRevisionController::class, $this->documentRevisionController);
	}
}
