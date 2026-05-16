<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515191948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE courrier (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) NOT NULL, recipient VARCHAR(160) DEFAULT NULL, type VARCHAR(80) NOT NULL, subject VARCHAR(255) NOT NULL, content CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, assigned_to_id INTEGER DEFAULT NULL, created_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_BEF47CAAF4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAF4BD7827 ON courrier (assigned_to_id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAB03A8386 ON courrier (created_by_id)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, service VARCHAR(100) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE courrier');
        $this->addSql('DROP TABLE "user"');
    }
}
