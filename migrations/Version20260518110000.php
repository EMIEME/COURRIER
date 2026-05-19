<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le parametrage administrable des listes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE list_option (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category VARCHAR(40) NOT NULL, value VARCHAR(120) NOT NULL, label VARCHAR(160) NOT NULL, position INTEGER NOT NULL, active BOOLEAN NOT NULL, locked BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_LIST_OPTION_CATEGORY_VALUE ON list_option (category, value)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'nature\', \'entrant\', \'Arrivé\', 10, 1, 1)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'nature\', \'sortant\', \'Départ\', 20, 1, 1)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'nature\', \'note_interne\', \'Note interne\', 30, 1, 1)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'status\', \'en_cours\', \'En cours\', 10, 1, 1)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'status\', \'traite\', \'Traité\', 20, 1, 1)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'status\', \'urgent\', \'Urgent\', 30, 1, 1)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'administratif\', \'Administratif\', 10, 1, 0)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'facture\', \'Facture\', 20, 1, 0)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'rh\', \'Ressources humaines (RH)\', 30, 1, 0)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'juridique\', \'Juridique\', 40, 1, 0)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'technique\', \'Technique\', 50, 1, 0)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'invitation\', \'Invitation\', 60, 1, 0)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'rapport\', \'Rapport\', 70, 1, 0)');
        $this->addSql('INSERT INTO list_option (category, value, label, position, active, locked) VALUES (\'type\', \'autre\', \'Autre\', 80, 1, 0)');
        $this->addSql('INSERT OR IGNORE INTO list_option (category, value, label, position, active, locked) SELECT \'localisation\', localisation, localisation, 100, 1, 0 FROM courrier WHERE localisation IS NOT NULL AND localisation <> \'\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE list_option');
    }
}
