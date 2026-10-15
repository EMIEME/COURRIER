<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme le libellé du statut en_cours en Nécessite une réponse.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE list_option SET label = ? WHERE category = ? AND value = ?', [
            'Nécessite une réponse', 'status', 'en_cours',
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE list_option SET label = ? WHERE category = ? AND value = ?', [
            'En cours', 'status', 'en_cours',
        ]);
    }
}
