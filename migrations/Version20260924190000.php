<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924190000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Ajoute les réglages persistants de cadrage de l’image principale.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql('ALTER TABLE recipe ADD image_position_x SMALLINT DEFAULT 50 NOT NULL');
		$this->addSql('ALTER TABLE recipe ADD image_position_y SMALLINT DEFAULT 50 NOT NULL');
		$this->addSql('ALTER TABLE recipe ADD image_zoom DOUBLE PRECISION DEFAULT 1 NOT NULL');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('ALTER TABLE recipe DROP image_position_x');
		$this->addSql('ALTER TABLE recipe DROP image_position_y');
		$this->addSql('ALTER TABLE recipe DROP image_zoom');
	}
}
