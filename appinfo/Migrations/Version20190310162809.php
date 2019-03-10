<?php
/**
 * @author Piotr Mrowczynski piotr@owncloud.com
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
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

namespace OCA\richdocuments\Migrations;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISchemaMigration;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version20190310162809 implements ISchemaMigration {

	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];

		// clean old legacy table if exists (only temporary tokens)
		if ($schema->hasTable("{$prefix}richdocuments_wopi")) {
			$schema->dropTable("{$prefix}richdocuments_wopi");
		}

		// create richdocuments wopi tokens table, which will be used for
		// wopi session tokens for the users
		$table = $schema->createTable("${prefix}richdocuments_wopi");

		// Document owner UserId - a textual user identifier
		$table->addColumn('owner_uid', 'text', [
			'length' => 64,
		]);

		// Document editor's UserId, can be different from uid if shared
		$table->addColumn('editor_uid', 'text', [
			'length' => 64,
		]);

		// The unique ID of the file authorized
		$table->addColumn('fileid', 'integer', [
			'length' => 10,
			'notnull' => true,
		]);

		// Authorized version, if any, of given fileid
		$table->addColumn('version', 'integer', [
			'length' => 4,
			'default' => 0,
			'notnull' => true,
		]);

		// Relative to storage e.g. /welcome.odt
		$table->addColumn('path', 'text', [
			'length' => 512,
			'notnull' => true,
		]);

		// Attributes of file authorized
		$table->addColumn('attributes', 'integer', [
			'length' => 4,
			'default' => 0,
			'notnull' => true,
		]);

		// Host from which token generation request originated
		$table->addColumn('server_host', 'text', [
			'default' => 'localhost',
			'notnull' => true,
		]);

		// File access token
		$table->addColumn('token', 'string', [
			'length' => 32,
			'default' => '',
			'notnull' => true,
		]);

		// Expiration time of the token
		$table->addColumn('expiry', 'integer', [
			'length' => 4,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addUniqueIndex(['token'], 'richdocuments_wopi_token_idx');
	}
}
