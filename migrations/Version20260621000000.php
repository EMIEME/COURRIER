<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs de reinitialisation de mot de passe a l utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD password_reset_token VARCHAR(100) DEFAULT NULL, ADD password_reset_requested_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP password_reset_token, DROP password_reset_requested_at');
    }
}
