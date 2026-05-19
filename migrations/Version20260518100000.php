<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permet de rattacher un courrier comme reponse a un courrier d origine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__courrier AS SELECT id, mail_date, direction, sender, recipient, reference, subject, localisation, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, sender_contact_id FROM courrier');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('CREATE TABLE courrier (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) DEFAULT NULL, recipient VARCHAR(160) DEFAULT NULL, reference VARCHAR(120) NOT NULL, reply_to_id INTEGER DEFAULT NULL, subject VARCHAR(255) NOT NULL, localisation VARCHAR(160) DEFAULT NULL, content CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INTEGER DEFAULT NULL, sender_contact_id INTEGER DEFAULT NULL, CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BEF47CAAB3257B87 FOREIGN KEY (sender_contact_id) REFERENCES destinataire (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BEF47CAA5B0AD4A2 FOREIGN KEY (reply_to_id) REFERENCES courrier (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO courrier (id, mail_date, direction, sender, recipient, reference, reply_to_id, subject, localisation, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, sender_contact_id) SELECT id, mail_date, direction, sender, recipient, reference, NULL, subject, localisation, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, sender_contact_id FROM __temp__courrier');
        $this->addSql('DROP TABLE __temp__courrier');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB03A8386 ON courrier (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB3257B87 ON courrier (sender_contact_id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAFFDF7169 ON courrier (reply_to_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEF47CAAAEA34913 ON courrier (reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__courrier AS SELECT id, mail_date, direction, sender, recipient, reference, subject, localisation, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, sender_contact_id FROM courrier');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('CREATE TABLE courrier (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) DEFAULT NULL, recipient VARCHAR(160) DEFAULT NULL, reference VARCHAR(120) NOT NULL, subject VARCHAR(255) NOT NULL, localisation VARCHAR(160) DEFAULT NULL, content CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INTEGER DEFAULT NULL, sender_contact_id INTEGER DEFAULT NULL, CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BEF47CAAB3257B87 FOREIGN KEY (sender_contact_id) REFERENCES destinataire (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO courrier (id, mail_date, direction, sender, recipient, reference, subject, localisation, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, sender_contact_id) SELECT id, mail_date, direction, sender, recipient, reference, subject, localisation, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, sender_contact_id FROM __temp__courrier');
        $this->addSql('DROP TABLE __temp__courrier');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB03A8386 ON courrier (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB3257B87 ON courrier (sender_contact_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEF47CAAAEA34913 ON courrier (reference)');
    }
}
