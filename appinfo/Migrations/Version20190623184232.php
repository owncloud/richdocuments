<?php
namespace OCA\richdocuments\Migrations;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISchemaMigration;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version20190623184232 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];

		$table = $schema->getTable("{$prefix}richdocuments_wopi");

		if ($table->hasColumn('path')) {
			$table->dropColumn('path');
		}
	}
}
