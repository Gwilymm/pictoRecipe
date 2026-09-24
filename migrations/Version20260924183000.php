<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924183000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Ajoute une image principale aux recettes.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE recipe ADD image_path TEXT DEFAULT NULL');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE recipe DROP image_path');
	}
}
