<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251106101702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pictogram (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL, format VARCHAR(10) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN pictogram.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ingredient ADD pictogram_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF787016B7C33B FOREIGN KEY (pictogram_id) REFERENCES pictogram (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6BAF787016B7C33B ON ingredient (pictogram_id)');
        $this->addSql('ALTER TABLE step ADD pictogram_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE step ADD CONSTRAINT FK_43B9FE3C16B7C33B FOREIGN KEY (pictogram_id) REFERENCES pictogram (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_43B9FE3C16B7C33B ON step (pictogram_id)');
        $this->addSql('ALTER TABLE utensil ADD pictogram_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE utensil ADD CONSTRAINT FK_9F283CBC16B7C33B FOREIGN KEY (pictogram_id) REFERENCES pictogram (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_9F283CBC16B7C33B ON utensil (pictogram_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE ingredient DROP CONSTRAINT FK_6BAF787016B7C33B');
        $this->addSql('ALTER TABLE step DROP CONSTRAINT FK_43B9FE3C16B7C33B');
        $this->addSql('ALTER TABLE utensil DROP CONSTRAINT FK_9F283CBC16B7C33B');
        $this->addSql('DROP TABLE pictogram');
        $this->addSql('DROP INDEX IDX_6BAF787016B7C33B');
        $this->addSql('ALTER TABLE ingredient DROP pictogram_id');
        $this->addSql('DROP INDEX IDX_43B9FE3C16B7C33B');
        $this->addSql('ALTER TABLE step DROP pictogram_id');
        $this->addSql('DROP INDEX IDX_9F283CBC16B7C33B');
        $this->addSql('ALTER TABLE utensil DROP pictogram_id');
    }
}
