<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le stockage persistant des notifications internes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE in_app_notification (id INT AUTO_INCREMENT NOT NULL, recipient_id INT NOT NULL, fingerprint VARCHAR(64) NOT NULL, type VARCHAR(40) NOT NULL, severity VARCHAR(20) NOT NULL, title VARCHAR(160) NOT NULL, message LONGTEXT NOT NULL, route VARCHAR(120) NOT NULL, route_params JSON NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_IN_APP_NOTIFICATION_RECIPIENT_FINGERPRINT (recipient_id, fingerprint), INDEX IDX_IN_APP_NOTIFICATION_RECIPIENT_ACTIVE_READ (recipient_id, active, read_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE in_app_notification ADD CONSTRAINT FK_IN_APP_NOTIFICATION_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE in_app_notification DROP FOREIGN KEY FK_IN_APP_NOTIFICATION_RECIPIENT');
        $this->addSql('DROP TABLE in_app_notification');
    }
}
