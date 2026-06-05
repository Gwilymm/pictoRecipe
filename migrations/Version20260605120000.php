<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605120000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Rend les pictogrammes multi-source et ajoute les metadonnees Wikimedia Commons.';
	}

	public function up(Schema $schema): void
	{
		$this->addSql("ALTER TABLE pictogram ADD source VARCHAR(80) DEFAULT 'user_upload' NOT NULL");
		$this->addSql('ALTER TABLE pictogram ADD source_id VARCHAR(255) DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD label VARCHAR(255) DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD image_url TEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD local_path TEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD thumbnail_url TEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD license VARCHAR(255) DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD license_url TEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD author TEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD credit TEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD attribution TEXT DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD mime VARCHAR(120) DEFAULT NULL');
		$this->addSql('ALTER TABLE pictogram ADD validated BOOLEAN DEFAULT true NOT NULL');
		$this->addSql('ALTER TABLE pictogram ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
		$this->addSql("COMMENT ON COLUMN pictogram.updated_at IS '(DC2Type:datetime_immutable)'");
		$this->addSql('UPDATE pictogram SET local_path = file_path WHERE local_path IS NULL');
		$this->addSql('CREATE INDEX IDX_PICTOGRAM_SOURCE ON pictogram (source)');
		$this->addSql('CREATE INDEX IDX_PICTOGRAM_SOURCE_ID ON pictogram (source, source_id)');
		$this->addSql('CREATE INDEX IDX_PICTOGRAM_VALIDATED ON pictogram (validated)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP INDEX IDX_PICTOGRAM_VALIDATED');
		$this->addSql('DROP INDEX IDX_PICTOGRAM_SOURCE_ID');
		$this->addSql('DROP INDEX IDX_PICTOGRAM_SOURCE');
		$this->addSql('ALTER TABLE pictogram DROP source');
		$this->addSql('ALTER TABLE pictogram DROP source_id');
		$this->addSql('ALTER TABLE pictogram DROP label');
		$this->addSql('ALTER TABLE pictogram DROP image_url');
		$this->addSql('ALTER TABLE pictogram DROP local_path');
		$this->addSql('ALTER TABLE pictogram DROP thumbnail_url');
		$this->addSql('ALTER TABLE pictogram DROP license');
		$this->addSql('ALTER TABLE pictogram DROP license_url');
		$this->addSql('ALTER TABLE pictogram DROP author');
		$this->addSql('ALTER TABLE pictogram DROP credit');
		$this->addSql('ALTER TABLE pictogram DROP attribution');
		$this->addSql('ALTER TABLE pictogram DROP mime');
		$this->addSql('ALTER TABLE pictogram DROP validated');
		$this->addSql('ALTER TABLE pictogram DROP updated_at');
	}
}
