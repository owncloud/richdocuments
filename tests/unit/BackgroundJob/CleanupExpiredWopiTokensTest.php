<?php
/**
 * @author Semih Serhat Karakaya <karakayasemi@itu.edu.tr>
 *
 * @copyright Copyright (c) 2020, ownCloud GmbH
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

namespace OCA\Richdocuments\Tests\BackgroundJob;

use OCA\Richdocuments\BackgroundJob\CleanupExpiredWopiTokens;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CleanupExpiredWopiTokensTest extends TestCase {
	/**
	 * @var CleanupExpiredWopiTokens $job
	 */
	protected $job;

	/**
	 * @var IDBConnection | MockObject
	 */
	protected $connection;

	/**
	 * @var ITimeFactory | MockObject
	 */
	protected $timeFactory;

	public function setUp(): void {
		parent::setUp();

		$this->connection = $this->createMock(IDBConnection::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->job = new CleanupExpiredWopiTokens($this->connection, $this->timeFactory);
	}

	public function testRun() {
		$this->timeFactory->expects($this->once())
			->method('getTime')
			->willReturn(1000);
		$this->connection->expects($this->once())
			->method('executeUpdate')
			->with('DELETE FROM `*PREFIX*richdocuments_wopi` WHERE `expiry` <= ?', [1000]);

		$this->job->run([]);
	}
}
