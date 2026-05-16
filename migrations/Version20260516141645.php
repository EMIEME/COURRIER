<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516141645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le referentiel des destinataires et les destinataires multiples sur les courriers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE destinataire (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(160) NOT NULL, service VARCHAR(160) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FEA9FF925E237E06 ON destinataire (name)');
        $this->addSql('CREATE TABLE courrier_destinataire (courrier_id INTEGER NOT NULL, destinataire_id INTEGER NOT NULL, PRIMARY KEY (courrier_id, destinataire_id), CONSTRAINT FK_5DCF6EE8BF41DC7 FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5DCF6EEA4F84F6E FOREIGN KEY (destinataire_id) REFERENCES destinataire (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_5DCF6EE8BF41DC7 ON courrier_destinataire (courrier_id)');
        $this->addSql('CREATE INDEX IDX_5DCF6EEA4F84F6E ON courrier_destinataire (destinataire_id)');
        $this->addSql('INSERT OR IGNORE INTO destinataire (name) SELECT DISTINCT recipient FROM courrier WHERE recipient IS NOT NULL AND recipient <> \'\'');
        $this->addSql('INSERT OR IGNORE INTO courrier_destinataire (courrier_id, destinataire_id) SELECT c.id, d.id FROM courrier c INNER JOIN destinataire d ON d.name = c.recipient WHERE c.recipient IS NOT NULL AND c.recipient <> \'\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE courrier_destinataire');
        $this->addSql('DROP TABLE destinataire');
    }
}
