<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251105185044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recipe_utensil (recipe_id INT NOT NULL, utensil_id INT NOT NULL, PRIMARY KEY(recipe_id, utensil_id))');
        $this->addSql('CREATE INDEX IDX_D3CC32FC59D8A214 ON recipe_utensil (recipe_id)');
        $this->addSql('CREATE INDEX IDX_D3CC32FCEC4313DE ON recipe_utensil (utensil_id)');
        $this->addSql('CREATE TABLE utensil (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, pictogram_url VARCHAR(500) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE recipe_utensil ADD CONSTRAINT FK_D3CC32FC59D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE recipe_utensil ADD CONSTRAINT FK_D3CC32FCEC4313DE FOREIGN KEY (utensil_id) REFERENCES utensil (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE recipe_utensil DROP CONSTRAINT FK_D3CC32FC59D8A214');
        $this->addSql('ALTER TABLE recipe_utensil DROP CONSTRAINT FK_D3CC32FCEC4313DE');
        $this->addSql('DROP TABLE recipe_utensil');
        $this->addSql('DROP TABLE utensil');
    }
}
