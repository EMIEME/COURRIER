<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516130805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace l imputation unique des courriers par des imputations multiples.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE courrier_imputation (courrier_id INTEGER NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY (courrier_id, user_id), CONSTRAINT FK_3A0B83FB8BF41DC7 FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3A0B83FBA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3A0B83FB8BF41DC7 ON courrier_imputation (courrier_id)');
        $this->addSql('CREATE INDEX IDX_3A0B83FBA76ED395 ON courrier_imputation (user_id)');
        $this->addSql('INSERT INTO courrier_imputation (courrier_id, user_id) SELECT id, assigned_to_id FROM courrier WHERE assigned_to_id IS NOT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__courrier AS SELECT id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id FROM courrier');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('CREATE TABLE courrier (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) NOT NULL, recipient VARCHAR(160) DEFAULT NULL, type VARCHAR(80) NOT NULL, subject VARCHAR(255) NOT NULL, content CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO courrier (id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id) SELECT id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id FROM __temp__courrier');
        $this->addSql('DROP TABLE __temp__courrier');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB03A8386 ON courrier (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__courrier AS SELECT c.id, c.mail_date, c.direction, c.sender, c.recipient, c.type, c.subject, c.content, c.status, c.attachment_filename, c.response_due_at, c.response_notes, c.created_at, c.updated_at, c.created_by_id, (SELECT ci.user_id FROM courrier_imputation ci WHERE ci.courrier_id = c.id ORDER BY ci.user_id LIMIT 1) AS assigned_to_id FROM courrier c');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('DROP TABLE courrier_imputation');
        $this->addSql('CREATE TABLE courrier (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) NOT NULL, recipient VARCHAR(160) DEFAULT NULL, type VARCHAR(80) NOT NULL, subject VARCHAR(255) NOT NULL, content CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INTEGER DEFAULT NULL, assigned_to_id INTEGER DEFAULT NULL, CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BEF47CAAF4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES "user" (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO courrier (id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, assigned_to_id) SELECT id, mail_date, direction, sender, recipient, type, subject, content, status, attachment_filename, response_due_at, response_notes, created_at, updated_at, created_by_id, assigned_to_id FROM __temp__courrier');
        $this->addSql('DROP TABLE __temp__courrier');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB03A8386 ON courrier (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAF4BD7827 ON courrier (assigned_to_id)');
    }
}
