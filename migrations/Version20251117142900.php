<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout d'un index unique sur le nom du pictogramme
 */
final class Version20251117142900 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Ajoute un index unique sur le champ name de la table pictogram pour empêcher les doublons';
	}

	public function up(Schema $schema): void
	{
		// Suppression des éventuels doublons existants avant d'ajouter la contrainte
		// On garde le premier pictogramme de chaque nom et supprime les autres
		$this->addSql('
            DELETE FROM pictogram p1
            USING pictogram p2
            WHERE p1.id > p2.id 
            AND p1.name = p2.name
        ');

		// Ajout de l'index unique
		$this->addSql('CREATE UNIQUE INDEX UNIQ_pictogram_name ON pictogram (name)');
	}

	public function down(Schema $schema): void
	{
		$this->addSql('DROP INDEX UNIQ_pictogram_name');
	}
}
