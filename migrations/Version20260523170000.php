<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le workflow de demande et approbation de suppression des courriers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier ADD deletion_requested_by_id INT DEFAULT NULL, ADD deletion_requested_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_BEF47CAACA0DA9F ON courrier (deletion_requested_by_id)');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAA2796A0D FOREIGN KEY (deletion_requested_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAA2796A0D');
        $this->addSql('DROP INDEX IDX_BEF47CAACA0DA9F ON courrier');
        $this->addSql('ALTER TABLE courrier DROP deletion_requested_by_id, DROP deletion_requested_at');
    }
}
