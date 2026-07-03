<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Augmente la longueur maximale de l objet des courriers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier CHANGE subject subject VARCHAR(500) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier CHANGE subject subject VARCHAR(255) NOT NULL');
    }
}
