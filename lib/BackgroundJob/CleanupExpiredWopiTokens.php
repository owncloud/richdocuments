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

namespace OCA\Richdocuments\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;

class CleanupExpiredWopiTokens extends TimedJob {

	/**
	 * @var IDBConnection $connection
	 */
	protected $connection;

	/**
	 * @var ITimeFactory $timeFactory
	 */
	protected $timeFactory;

	/**
	 * @param IDBConnection $connection
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IDBConnection $connection, ITimeFactory $timeFactory) {
		$this->connection = $connection;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Makes the background job do its work
	 *
	 * @param array $argument unused argument
	 */
	public function run($argument) {
		$now = $this->timeFactory->getTime();
		$this->connection->executeUpdate(
			'DELETE FROM `*PREFIX*richdocuments_wopi` WHERE `expiry` <= ?',
			[$now]
		);
	}
}
