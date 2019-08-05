<?php

namespace OCA\richdocuments\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use OCP\Migration\ISchemaMigration;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version20190710194710 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];

		$table = $schema->getTable("{$prefix}richdocuments_wopi");

		$fileidColumn = $table->getColumn('fileid');
		if ($fileidColumn) {
			$fileidColumn->setType(Type::getType(Type::BIGINT));
			$fileidColumn->setOptions(['length' => 20]);
		}
	}
}
