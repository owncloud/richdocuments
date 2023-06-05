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
namespace OCA\Richdocuments\Tests;

use OCA\Richdocuments\DocumentService;
use OCP\IConfig;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Folder;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class DocumentServiceTest extends TestCase {
	/**
	 * @var IRootFolder|MockObject $rootFolder The root folder mock object.
	 */
	private $rootFolder;

	/**
	 * @var IConfig|MockObject $config The config mock object.
	 */
	private $config;

	/**
	 * @var DocumentService|MockObject $documentService The document service mock object.
	 */
	private $documentService;

	/**
	 * @var string $userId The user ID.
	 */
	private $userId = 'testuser';

	/**
	 * @var int $fileId The file ID.
	 */
	private $fileId = 123;

	/**
	 * @var string $dir The directory.
	 */
	private $dir = '/testdir';

	/**
	 * @var string $token The token.
	 */
	private $token = 'testtoken';

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->config = $this->createMock(IConfig::class);

		$this->documentService = $this->getMockBuilder(DocumentService::class)
				->onlyMethods(['reportError'])
				->setConstructorArgs([
					$this->rootFolder,
					$this->config,
				])
				->getMock();
	}

	/**
	 * Test the getDocumentByUserId method of the DocumentService class
	 * with a valid file mount returns.
	 *
	 * @return void
	 */
	public function testGetDocumentByUserId(): void {
		// Mock the root folder
		$rootFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with($this->equalTo($this->userId))
				->willReturn($rootFolder);

		// Mock the file mount
		$fileMount = $this->createMock(Node::class);
		$fileMounts = [$fileMount];
		$rootFolder->expects($this->once())
				->method('getById')
				->with($this->equalTo($this->fileId))
				->willReturn($fileMounts);

		// Mock the document
		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
				->method('getUID')
				->willReturn('owner');
		$fileMount->expects($this->once())
				->method('getOwner')
				->willReturn($owner);
		$fileMount->expects($this->once())
				->method('getPermissions')
				->willReturn(Constants::PERMISSION_ALL);
		$fileMount->expects($this->once())
				->method('isUpdateable')
				->willReturn(true);
		$fileMount->expects($this->once())
				->method('getMimeType')
				->willReturn('text/plain');
		$fileMount->expects($this->once())
				->method('getPath')
				->willReturn('/path/to/file.txt');
		$rootFolder->expects($this->once())
				->method('getRelativePath')
				->with($this->equalTo('/path/to/file.txt'))
				->willReturn('file.txt');
		$fileMount->expects($this->once())
				->method('getName')
				->willReturn('file.txt');

		// Mock the config
		$this->config->expects($this->once())
				->method('getSystemValue')
				->with($this->equalTo('instanceid'))
				->willReturn('instanceid');

		// Call the method being tested
		$result = $this->documentService->getDocumentByUserId($this->userId, $this->fileId, null);

		// Assert the result
		$this->assertNotNull($result);
		$this->assertEquals('owner', $result['owner']);
		$this->assertEquals(Constants::PERMISSION_ALL, $result['permissions']);
		$this->assertTrue($result['updateable']);
		$this->assertEquals('text/plain', $result['mimetype']);
		$this->assertEquals('file.txt', $result['path']);
		$this->assertEquals('file.txt', $result['name']);
		$this->assertEquals($this->fileId, $result['fileid']);
		$this->assertEquals('0', $result['version']);
		$this->assertEquals('instanceid', $result['instanceid']);
	}

	/**
	 * Test the getDocumentByUserId method of the DocumentService class
	 * with a valid file mount returns.
	 *
	 * @return void
	 */
	public function testGetDocumentByUserIdWithDir(): void {
		// Mock the root folder
		$rootFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
				->method('getUserFolder')
				->with($this->equalTo($this->userId))
				->willReturn($rootFolder);

		// Mock the dir
		$dir = $this->createMock(Folder::class);
		$rootFolder->expects($this->once())
				->method('get')
				->with($this->equalTo($this->dir))
				->willReturn($dir);

		// Mock the file mount
		$fileMount = $this->createMock(Node::class);
		$fileMounts = [$fileMount];
		$dir->expects($this->once())
				->method('getById')
				->with($this->equalTo($this->fileId))
				->willReturn($fileMounts);

		// Mock the document
		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
				->method('getUID')
				->willReturn('owner');
		$fileMount->expects($this->once())
				->method('getOwner')
				->willReturn($owner);
		$fileMount->expects($this->once())
				->method('getPermissions')
				->willReturn(Constants::PERMISSION_ALL);
		$fileMount->expects($this->once())
				->method('isUpdateable')
				->willReturn(true);
		$fileMount->expects($this->once())
				->method('getMimeType')
				->willReturn('text/plain');
		$fileMount->expects($this->once())
				->method('getPath')
				->willReturn('/path/to/' . $this->dir . '/file.txt');
		$rootFolder->expects($this->once())
				->method('getRelativePath')
				->with($this->equalTo('/path/to/' . $this->dir . '/file.txt'))
				->willReturn($this->dir . '/file.txt');
		$fileMount->expects($this->once())
				->method('getName')
				->willReturn('file.txt');

		// Mock the config
		$this->config->expects($this->once())
				->method('getSystemValue')
				->with($this->equalTo('instanceid'))
				->willReturn('instanceid');

		// Call the method being tested
		$result = $this->documentService->getDocumentByUserId($this->userId, $this->fileId, $this->dir);

		// Assert the result
		$this->assertNotNull($result);
		$this->assertEquals('owner', $result['owner']);
		$this->assertEquals(Constants::PERMISSION_ALL, $result['permissions']);
		$this->assertTrue($result['updateable']);
		$this->assertEquals('text/plain', $result['mimetype']);
		$this->assertEquals('/testdir/file.txt', $result['path']);
		$this->assertEquals('file.txt', $result['name']);
		$this->assertEquals($this->fileId, $result['fileid']);
		$this->assertEquals('0', $result['version']);
		$this->assertEquals('instanceid', $result['instanceid']);
	}
}
