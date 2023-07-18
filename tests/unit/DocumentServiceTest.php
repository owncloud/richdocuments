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

use OC\Share20\ShareAttributes;
use OCA\Richdocuments\AppConfig;
use OCA\Richdocuments\DocumentService;
use OCP\Files\Storage\IStorage;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\FileInfo;
use OCP\Share\IAttributes;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\ISession;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class DocumentServiceTest extends TestCase {
	/**
	 * @var IRootFolder|MockObject The root folder mock object.
	 */
	private $rootFolder;

	/**
	 * @var AppConfig|MockObject The app config mock object.
	 */
	private $appConfig;

	/**
	 * @var IManager|MockObject
	 */
	private $shareManager;

	/**
	 * @var ISession|MockObject
	 */
	private $session;

	/**
	 * @var DocumentService|MockObject The document service mock object.
	 */
	private $documentService;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->appConfig = $this->createMock(AppConfig::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->session = $this->createMock(ISession::class);

		$this->documentService = $this->getMockBuilder(DocumentService::class)
			->onlyMethods(['reportError'])
			->setConstructorArgs([
				$this->rootFolder,
				$this->appConfig,
				$this->shareManager,
				$this->session,
			])
			->getMock();
	}

	public function testGetDocumentByUserIdWithNormalFile(): void {
		// Mock the root folder
		$rootFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with($this->equalTo('testuser'))
			->willReturn($rootFolder);

		// Mock the file mount
		$fileMount = $this->createMock(File::class);
		$fileMounts = [$fileMount];
		$rootFolder->expects($this->once())
			->method('getById')
			->with($this->equalTo(123))
			->willReturn($fileMounts);

		$this->appConfig->method('secureViewOptionEnabled')->willReturn(false);

		$storage = $this->createMock(IStorage::class);
		$storage->expects($this->any())
			->method('instanceOfStorage')
			->willReturnCallback(function (string $instance) {
				switch ($instance) {
					case '\OCA\Files_Sharing\SharedStorage':
						return false;
					case '\OCA\Files_Sharing\External\Storage':
						return false;
					default:
						return true;
				}
			});

		// Mock the document
		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getUID')
			->willReturn('owner');
		$fileMount->expects($this->once())
			->method('getStorage')
			->willReturn($storage);
		$fileMount->expects($this->once())
			->method('getOwner')
			->willReturn($owner);
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

		// Call the method being tested
		$result = $this->documentService->getDocumentByUserId('testuser', 123, null);

		// Assert the result
		$expected = [
			'owner' => 'owner',
			'allowEdit' => true,
			'allowExport' => true,
			'allowPrint' => true,
			'mimetype' => 'text/plain',
			'path' => 'file.txt',
			'name' => 'file.txt',
			'fileid' => 123,
			'version' => '0',
			'secureView' => false,
			'secureViewId' => null,
			'federatedServer' => null,
			'federatedShareToken' => null,
			'federatedShareRelativePath' => null,
		];
		$this->assertEquals($expected, $result);
	}

	// TODO
	public function testGetDocumentByUserIdWithGroupShare(): void {
		// Mock the root folder
		$rootFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with($this->equalTo('testuser'))
			->willReturn($rootFolder);

		// Mock the file mount
		$fileMount = $this->createMock(File::class);
		$fileMounts = [$fileMount];
		$rootFolder->expects($this->once())
			->method('getById')
			->with($this->equalTo(123))
			->willReturn($fileMounts);

		// Mock the share attributes
		$shareAttributes = $this->createMock(IAttributes::class);
		$shareAttributes->expects($this->any())
			->method('getAttribute')
			->willReturnCallback(function (string $scope, string $key) {
				switch ($key) {
					case 'download':
						return false;
					case 'print':
						return true;
					case 'view-with-watermark':
						return true;
					default:
						return false;
				}
			});

		// Mock the share
		$share = $this->createMock(IShare::class);
		$share->expects($this->any())
			->method('getId')
			->willReturn(567);
		$share->expects($this->any())
			->method('getAttributes')
			->willReturn($shareAttributes);

		$this->appConfig->method('secureViewOptionEnabled')->willReturn(true);

		$storage = $this->createMock('\OCA\Files_Sharing\SharedStorage');
		$storage->expects($this->any())
			->method('instanceOfStorage')
			->willReturnCallback(function (string $instance) {
				switch ($instance) {
					case '\OCA\Files_Sharing\SharedStorage':
						return true;
					case '\OCA\Files_Sharing\External\Storage':
						return false;
					default:
						return true;
				}
			});
		$storage->expects($this->any())
			->method('getShare')
			->willReturn($share);

		// Mock the document
		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getUID')
			->willReturn('owner');
		$fileMount->expects($this->once())
			->method('getStorage')
			->willReturn($storage);
		$fileMount->expects($this->once())
			->method('getOwner')
			->willReturn($owner);
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

		// Call the method being tested
		$result = $this->documentService->getDocumentByUserId('testuser', 123, null);

		// Assert the result
		$expected = [
			'owner' => 'owner',
			'allowEdit' => false,
			'allowExport' => false,
			'allowPrint' => true,
			'mimetype' => 'text/plain',
			'path' => 'file.txt',
			'name' => 'file.txt',
			'fileid' => 123,
			'version' => '0',
			'secureView' => true,
			'secureViewId' => 567,
			'federatedServer' => null,
			'federatedShareToken' => null,
			'federatedShareRelativePath' => null,
		];
		$this->assertEquals($expected, $result);
	}

	public function testGetDocumentByUserIdWithFedShare(): void {
		// Mock the root folder
		$rootFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with($this->equalTo('testuser'))
			->willReturn($rootFolder);

		// Mock the file mount
		$fileMount = $this->createMock(File::class);
		$fileMounts = [$fileMount];
		$rootFolder->expects($this->once())
			->method('getById')
			->with($this->equalTo(123))
			->willReturn($fileMounts);

		$this->appConfig->method('secureViewOptionEnabled')->willReturn(false);

		$storage = $this->createMock('\OCA\Files_Sharing\External\Storage');
		$storage->expects($this->any())
			->method('instanceOfStorage')
			->willReturnCallback(function (string $instance) {
				switch ($instance) {
					case '\OCA\Files_Sharing\SharedStorage':
						return false;
					case '\OCA\Files_Sharing\External\Storage':
						return true;
					default:
						return true;
				}
			});
		$storage->expects($this->any())
			->method('getRemote')
			->willReturn('fedinstance');
		$storage->expects($this->any())
			->method('getToken')
			->willReturn('fedsharetoken');

		// Mock the document
		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getUID')
			->willReturn('owner');
		$fileMount->expects($this->once())
			->method('getStorage')
			->willReturn($storage);
		$fileMount->expects($this->once())
			->method('getOwner')
			->willReturn($owner);
		$fileMount->expects($this->once())
			->method('isUpdateable')
			->willReturn(true);
		$fileMount->expects($this->once())
			->method('getMimeType')
			->willReturn('text/plain');
		$fileMount->expects($this->once())
			->method('getPath')
			->willReturn('/path/to/file.txt');
		$fileMount->expects($this->once())
			->method('getInternalPath')
			->willReturn('file.txt');
		$fileMount->expects($this->once())
			->method('getName')
			->willReturn('file.txt');
		$rootFolder->expects($this->once())
			->method('getRelativePath')
			->with($this->equalTo('/path/to/file.txt'))
			->willReturn('file.txt');

		// Call the method being tested
		$result = $this->documentService->getDocumentByUserId('testuser', 123, null);

		// Assert the result
		$expected = [
			'owner' => 'owner',
			'allowEdit' => true,
			'allowExport' => true,
			'allowPrint' => true,
			'mimetype' => 'text/plain',
			'path' => 'file.txt',
			'name' => 'file.txt',
			'fileid' => 123,
			'version' => '0',
			'secureView' => false,
			'secureViewId' => null,
			'federatedServer' => 'fedinstance',
			'federatedShareToken' => 'fedsharetoken',
			'federatedShareRelativePath' => 'file.txt',
		];
		$this->assertEquals($expected, $result);
	}

	public function testGetDocumentByUserIdWithDir(): void {
		// Mock the root folder
		$rootFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with($this->equalTo('testuser'))
			->willReturn($rootFolder);

		// Mock the dir
		$dir = $this->createMock(Folder::class);
		$rootFolder->expects($this->once())
			->method('get')
			->with($this->equalTo('/testdir'))
			->willReturn($dir);

		// Mock the file mount
		$fileMount = $this->createMock(File::class);
		$fileMounts = [$fileMount];
		$dir->expects($this->once())
			->method('getById')
			->with($this->equalTo(123))
			->willReturn($fileMounts);

		$this->appConfig->method('secureViewOptionEnabled')->willReturn(false);
		
		$storage = $this->createMock(IStorage::class);
		$storage->expects($this->any())
			->method('instanceOfStorage')
			->willReturnCallback(function (string $instance) {
				switch ($instance) {
					case '\OCA\Files_Sharing\SharedStorage':
						return false;
					case '\OCA\Files_Sharing\External\Storage':
						return false;
					default:
						return true;
				}
			});

		// Mock the document
		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getUID')
			->willReturn('owner');
		$fileMount->expects($this->once())
			->method('getStorage')
			->willReturn($storage);
		$fileMount->expects($this->once())
			->method('getOwner')
			->willReturn($owner);
		$fileMount->expects($this->once())
			->method('isUpdateable')
			->willReturn(true);
		$fileMount->expects($this->once())
			->method('getMimeType')
			->willReturn('text/plain');
		$fileMount->expects($this->once())
			->method('getPath')
			->willReturn('/path/to/' . '/testdir' . '/file.txt');
		$rootFolder->expects($this->once())
			->method('getRelativePath')
			->with($this->equalTo('/path/to/' . '/testdir' . '/file.txt'))
			->willReturn('/testdir' . '/file.txt');
		$fileMount->expects($this->once())
			->method('getName')
			->willReturn('file.txt');

		// Call the method being tested
		$result = $this->documentService->getDocumentByUserId('testuser', 123, '/testdir');

		// Assert the result
		$expected = [
			'owner' => 'owner',
			'allowEdit' => true,
			'allowExport' => true,
			'allowPrint' => true,
			'mimetype' => 'text/plain',
			'path' => '/testdir/file.txt',
			'name' => 'file.txt',
			'fileid' => 123,
			'version' => '0',
			'secureView' => false,
			'secureViewId' => null,
			'federatedServer' => null,
			'federatedShareToken' => null,
			'federatedShareRelativePath' => null,
		];
		$this->assertEquals($expected, $result);
	}

	public function testGetDocumentByShareTokenWithoutPassword(): void {
		$document = $this->createMock(File::class);
		$document->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$document->method('getMimeType')->willReturn('application/pdf');
		$document->method('getPath')->willReturn('/path/to/document.pdf');
		$document->method('getName')->willReturn('document.pdf');
		$document->method('getId')->willReturn(123);

		$share = $this->createMock(IShare::class);
		$share->method('getNode')->willReturn($document);
		$share->method('getPassword')->willReturn(null);
		$share->method('getShareOwner')->willReturn('owner');
		$share->method('getPermissions')->willReturn(Constants::PERMISSION_UPDATE);
		$share->method('getId')->willReturn(456);

		$this->shareManager->method('getShareByToken')->willReturn($share);

		$this->rootFolder->method('getUserFolder')->willReturn($this->rootFolder);
		$this->rootFolder->method('getRelativePath')->willReturn('/path/to/document.pdf');

		$expected = [
			'owner' => 'owner',
			'allowEdit' => true,
			'allowExport' => true,
			'allowPrint' => true,
			'mimetype' => 'application/pdf',
			'path' => '/path/to/document.pdf',
			'name' => 'document.pdf',
			'fileid' => 123,
			'version' => '0'
		];

		$this->assertEquals($expected, $this->documentService->getDocumentByShareToken('testtoken', null));
	}

	public function testGetDocumentByShareTokenInFolder(): void {
		$document = $this->createMock(File::class);
		$document->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$document->method('getMimeType')->willReturn('application/pdf');
		$document->method('getPath')->willReturn('/path/to/document.pdf');
		$document->method('getName')->willReturn('document.pdf');
		$document->method('getId')->willReturn(123);

		$sharedFolder = $this->createMock(Folder::class);
		$sharedFolder->method('getById')
			->with($this->equalTo(1))
			->willReturn([$document]);

		$share = $this->createMock(IShare::class);
		$share->method('getNode')->willReturn($sharedFolder);
		$share->method('getPassword')->willReturn(null);
		$share->method('getShareOwner')->willReturn('owner');
		$share->method('getPermissions')->willReturn(Constants::PERMISSION_UPDATE);
		$share->method('getId')->willReturn(456);

		$this->shareManager->method('getShareByToken')->willReturn($share);

		$this->rootFolder->method('getUserFolder')->willReturn($this->rootFolder);
		$this->rootFolder->method('getRelativePath')->willReturn('/path/to/document.pdf');

		$expected = [
			'owner' => 'owner',
			'allowEdit' => true,
			'allowExport' => true,
			'allowPrint' => true,
			'mimetype' => 'application/pdf',
			'path' => '/path/to/document.pdf',
			'name' => 'document.pdf',
			'fileid' => 123,
			'version' => '0'
		];

		$this->assertEquals($expected, $this->documentService->getDocumentByShareToken('testtoken', 1));
	}

	public function testGetDocumentByShareTokenWithPassword(): void {
		$document = $this->createMock(File::class);
		$document->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$document->method('getMimeType')->willReturn('application/pdf');
		$document->method('getPath')->willReturn('/path/to/document.pdf');
		$document->method('getName')->willReturn('document.pdf');
		$document->method('getId')->willReturn(123);

		$share = $this->createMock(IShare::class);
		$share->method('getNode')->willReturn($document);
		$share->method('getPassword')->willReturn('sharePassword');
		$share->method('getShareOwner')->willReturn('owner');
		$share->method('getPermissions')->willReturn(Constants::PERMISSION_UPDATE);
		$share->method('getId')->willReturn(456);

		$this->shareManager->method('getShareByToken')->willReturn($share);

		$this->rootFolder->method('getUserFolder')->willReturn($this->rootFolder);
		$this->rootFolder->method('getRelativePath')->willReturn('/path/to/document.pdf');

		$this->session->expects($this->once())
			->method('exists')
			->with($this->equalTo('public_link_authenticated'))
			->willReturn(true);
		$this->session->expects($this->once())
			->method('get')
			->with($this->equalTo('public_link_authenticated'))
			->willReturn('456');

		$expected = [
			'owner' => 'owner',
			'allowEdit' => true,
			'allowExport' => true,
			'allowPrint' => true,
			'mimetype' => 'application/pdf',
			'path' => '/path/to/document.pdf',
			'name' => 'document.pdf',
			'fileid' => 123,
			'version' => '0'
		];

		$this->assertEquals($expected, $this->documentService->getDocumentByShareToken('testtoken', null));
	}

	public function testGetDocumentByShareTokenWithInvalidPassword(): void {
		$document = $this->createMock(File::class);
		$document->method('getType')->willReturn(FileInfo::TYPE_FILE);
		$document->method('getMimeType')->willReturn('application/pdf');
		$document->method('getPath')->willReturn('/path/to/document.pdf');
		$document->method('getName')->willReturn('document.pdf');
		$document->method('getId')->willReturn(123);
	
		$share = $this->createMock(IShare::class);
		$share->method('getNode')->willReturn($document);
		$share->method('getPassword')->willReturn('sharePassword');
		$share->method('getShareOwner')->willReturn('owner');
		$share->method('getPermissions')->willReturn(Constants::PERMISSION_UPDATE);
		$share->method('getId')->willReturn(456);
	
		$this->shareManager->method('getShareByToken')->willReturn($share);
	
		$this->rootFolder->method('getUserFolder')->willReturn($this->rootFolder);
		$this->rootFolder->method('getRelativePath')->willReturn('/path/to/document.pdf');
	
		$expected = null;
		$this->assertEquals($expected, $this->documentService->getDocumentByShareToken('testtoken', null));
	}
}
