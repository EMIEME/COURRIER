<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l historique des actions sur les courriers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE courrier_action (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, courrier_id INTEGER NOT NULL, actor_id INTEGER DEFAULT NULL, action_type VARCHAR(40) NOT NULL, summary VARCHAR(255) NOT NULL, details CLOB DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_A7A6D0B58BF41DC7 FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A7A6D0B510DAF24A FOREIGN KEY (actor_id) REFERENCES "user" (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_61DDE5BF8BF41DC7 ON courrier_action (courrier_id)');
        $this->addSql('CREATE INDEX IDX_61DDE5BF10DAF24A ON courrier_action (actor_id)');
        $this->addSql('INSERT INTO courrier_action (courrier_id, actor_id, action_type, summary, details, created_at) SELECT id, created_by_id, \'created\', \'Courrier existant\', \'Historique initial genere automatiquement.\', created_at FROM courrier');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE courrier_action');
    }
}
