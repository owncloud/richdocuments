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

use OC\User\Session;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\IUser;
use OCP\ILogger;
use OCP\IUserManager;
use OCA\Richdocuments\AppConfig;
use Symfony\Component\EventDispatcher\EventDispatcher;
use OCA\Richdocuments\FileService;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\TestCase;

class FileServiceTest extends TestCase {
	/**
	 * @var ILogger
	 */
	private $logger;
	
	/**
	 * @var AppConfig
	 */
	private $appConfig;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var Session
	 */
	private $userSession;

	/**
	 * @var EventDispatcher
	 */
	private $eventDispatcher;

	/**
	 * @var IRootFolder
	 */
	private $rootFolder;

	/** @var FileService */
	private $fileService;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(ILogger::class);
		$this->appConfig = $this->createMock(AppConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userSession = $this->createMock(Session::class);
		$this->eventDispatcher = $this->createMock(EventDispatcher::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$this->fileService = $this->getMockBuilder(FileService::class)
			->onlyMethods(['setIncognitoMode', 'setupFS'])
			->setConstructorArgs([
				$this->logger,
				$this->appConfig,
				$this->userManager,
				$this->userSession,
				$this->eventDispatcher,
				$this->rootFolder
			])
			->getMock();
	}

	public function testOwnerInvalid() {
		$fileId = 1;
		$owner = '';
		$editor = 'editor';
			
		$returnedFile = $this->fileService->getFileHandle($fileId, $owner, $editor);

		$this->assertEquals($returnedFile, null);
	}

	public function testEditorInvalid() {
		$fileId = 1;
		$owner = 'owner';
		$editor = 'invalid';

		$this->userManager->expects($this->once())
			->method('get')
			->with($editor)
			->willReturn(null);

		$returnedFile = $this->fileService->getFileHandle($fileId, $owner, $editor);

		$this->assertEquals($returnedFile, null);
	}

	public function testInvalidFileId() {
		$fileId = 1;
		$owner = 'owner';
		$editor = 'editor';

		$this->fileService->expects($this->never())
			->method('setIncognitoMode');
		$this->fileService->expects($this->any())
			->method('setupFS')
			->with($owner);

		$this->appConfig->expects($this->once())
			->method('encryptionEnabled')
			->willReturn(false);

		$this->userSession->expects($this->once())
			->method('setUser');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with($editor)
			->willReturn($user);

		$file = $this->createMock(File::class);
		$file->expects($this->any())
			->method('getId')
			->willReturn($fileId);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->any())
			->method('getById')
			->with($fileId)
			->willReturn([]);

		$this->rootFolder->expects($this->any())
			->method('getUserFolder')
			->with($owner)
			->willReturn($userFolder);
			
		$returnedFile = $this->fileService->getFileHandle($fileId, $owner, $editor);

		$this->assertEquals($returnedFile, null);
	}

	public function testGetFileHandleNoEncryption() {
		$fileId = 1;
		$owner = 'owner';
		$editor = 'editor';

		$this->fileService->expects($this->never())
			->method('setIncognitoMode');
		$this->fileService->expects($this->any())
			->method('setupFS')
			->with($owner);

		$this->appConfig->expects($this->once())
			->method('encryptionEnabled')
			->willReturn(false);

		$this->userSession->expects($this->once())
			->method('setUser');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with($editor)
			->willReturn($user);

		$file = $this->createMock(File::class);
		$file->expects($this->any())
			->method('getId')
			->willReturn($fileId);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->any())
			->method('getById')
			->with($fileId)
			->willReturn([$file]);

		$this->rootFolder->expects($this->any())
			->method('getUserFolder')
			->with($owner)
			->willReturn($userFolder);
			
		$returnedFile = $this->fileService->getFileHandle($fileId, $owner, $editor);

		$this->assertEquals($returnedFile->getId(), $fileId);
	}

	public function testGetFileHandleMasterEncryption() {
		$fileId = 1;
		$owner = 'owner';
		$editor = 'editor';

		$this->fileService->expects($this->never())
			->method('setIncognitoMode');
		$this->fileService->expects($this->any())
			->method('setupFS')
			->with($owner);

		$this->appConfig->expects($this->once())
			->method('encryptionEnabled')
			->willReturn(true);

		$this->appConfig->expects($this->once())
			->method('masterEncryptionEnabled')
			->willReturn(true);

		$this->userSession->expects($this->once())
			->method('setUser');

		$this->eventDispatcher->expects($this->once())
			->method('dispatch')
			->with($this->isInstanceOf(GenericEvent::class), 'user.afterlogin');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with($editor)
			->willReturn($user);

		$file = $this->createMock(File::class);
		$file->expects($this->any())
			->method('getId')
			->willReturn($fileId);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->any())
			->method('getById')
			->with($fileId)
			->willReturn([$file]);

		$this->rootFolder->expects($this->any())
			->method('getUserFolder')
			->with($owner)
			->willReturn($userFolder);
			
		$returnedFile = $this->fileService->getFileHandle($fileId, $owner, $editor);

		$this->assertEquals($returnedFile->getId(), $fileId);
	}

	public function testGetFileHandlePublicLink() {
		$fileId = 1;
		$owner = 'owner';
		$editor = '';

		$this->fileService->expects($this->once())
			->method('setIncognitoMode')
			->with(true);
		$this->fileService->expects($this->any())
			->method('setupFS')
			->with($owner);

		$file = $this->createMock(File::class);
		$file->expects($this->any())
			->method('getId')
			->willReturn($fileId);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->any())
			->method('getById')
			->with($fileId)
			->willReturn([$file]);

		$this->rootFolder->expects($this->any())
			->method('getUserFolder')
			->with($owner)
			->willReturn($userFolder);
			
		$returnedFile = $this->fileService->getFileHandle($fileId, $owner, $editor);

		$this->assertEquals($returnedFile->getId(), $fileId);
	}
}
