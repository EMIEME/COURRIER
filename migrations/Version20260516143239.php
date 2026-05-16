<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516143239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relie l emetteur au referentiel des destinataires et rend l emetteur optionnel pour les courriers envoyes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT OR IGNORE INTO destinataire (name) SELECT DISTINCT sender FROM courrier WHERE sender IS NOT NULL AND sender <> \'\'');
        $this->addSql('CREATE TEMPORARY TABLE __temp__courrier AS SELECT id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id FROM courrier');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('CREATE TABLE courrier (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) DEFAULT NULL, recipient VARCHAR(160) DEFAULT NULL, type VARCHAR(80) NOT NULL, subject VARCHAR(255) NOT NULL, content CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INTEGER DEFAULT NULL, sender_contact_id INTEGER DEFAULT NULL, CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BEF47CAAB3257B87 FOREIGN KEY (sender_contact_id) REFERENCES destinataire (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO courrier (id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, sender_contact_id) SELECT t.id, t.mail_date, t.direction, t.sender, t.recipient, t.type, t.subject, t.content, t.status, t.attachment_filename, t.response_due_at, t.response_notes, t.created_at, t.updated_at, t.created_by_id, d.id FROM __temp__courrier t LEFT JOIN destinataire d ON d.name = t.sender');
        $this->addSql('DROP TABLE __temp__courrier');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB03A8386 ON courrier (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB3257B87 ON courrier (sender_contact_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__courrier AS SELECT id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id FROM courrier');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('CREATE TABLE courrier (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) NOT NULL, recipient VARCHAR(160) DEFAULT NULL, type VARCHAR(80) NOT NULL, subject VARCHAR(255) NOT NULL, content CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO courrier (id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id) SELECT id, mail_date, direction, COALESCE(sender, \'\'), recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id FROM __temp__courrier');
        $this->addSql('DROP TABLE __temp__courrier');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB03A8386 ON courrier (created_by_id)');
    }
}
