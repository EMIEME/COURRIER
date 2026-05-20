<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration initiale MySQL pour une base gestion courrier vide.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE list_option (id INT AUTO_INCREMENT NOT NULL, category VARCHAR(40) NOT NULL, value VARCHAR(120) NOT NULL, label VARCHAR(160) NOT NULL, position INT NOT NULL, active TINYINT NOT NULL, locked TINYINT NOT NULL, UNIQUE INDEX UNIQ_LIST_OPTION_CATEGORY_VALUE (category, value), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE courrier (id INT AUTO_INCREMENT NOT NULL, mail_date DATE NOT NULL, direction VARCHAR(20) NOT NULL, sender VARCHAR(160) DEFAULT NULL, recipient VARCHAR(160) DEFAULT NULL, reference VARCHAR(120) NOT NULL, subject VARCHAR(255) NOT NULL, localisation VARCHAR(160) DEFAULT NULL, content LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, attachment_filename VARCHAR(255) DEFAULT NULL, response_due_at DATE DEFAULT NULL, response_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sender_contact_id INT DEFAULT NULL, reply_to_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BEF47CAAAEA34913 (reference), INDEX IDX_BEF47CAAB3257B87 (sender_contact_id), INDEX IDX_BEF47CAAFFDF7169 (reply_to_id), INDEX IDX_BEF47CAAB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE courrier_imputation (courrier_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_3A0B83FB8BF41DC7 (courrier_id), INDEX IDX_3A0B83FBA76ED395 (user_id), PRIMARY KEY (courrier_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE courrier_destinataire (courrier_id INT NOT NULL, destinataire_id INT NOT NULL, INDEX IDX_5DCF6EE8BF41DC7 (courrier_id), INDEX IDX_5DCF6EEA4F84F6E (destinataire_id), PRIMARY KEY (courrier_id, destinataire_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE courrier_action (id INT AUTO_INCREMENT NOT NULL, action_type VARCHAR(40) NOT NULL, summary VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, courrier_id INT NOT NULL, actor_id INT DEFAULT NULL, INDEX IDX_61DDE5BF8BF41DC7 (courrier_id), INDEX IDX_61DDE5BF10DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, service VARCHAR(100) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE destinataire (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, service VARCHAR(160) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, UNIQUE INDEX UNIQ_FEA9FF925E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAAB3257B87 FOREIGN KEY (sender_contact_id) REFERENCES destinataire (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAAFFDF7169 FOREIGN KEY (reply_to_id) REFERENCES courrier (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAAB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE courrier_imputation ADD CONSTRAINT FK_3A0B83FB8BF41DC7 FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE courrier_imputation ADD CONSTRAINT FK_3A0B83FBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE courrier_destinataire ADD CONSTRAINT FK_5DCF6EE8BF41DC7 FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE courrier_destinataire ADD CONSTRAINT FK_5DCF6EEA4F84F6E FOREIGN KEY (destinataire_id) REFERENCES destinataire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE courrier_action ADD CONSTRAINT FK_61DDE5BF8BF41DC7 FOREIGN KEY (courrier_id) REFERENCES courrier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE courrier_action ADD CONSTRAINT FK_61DDE5BF10DAF24A FOREIGN KEY (actor_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->seedListOptions();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier_action DROP FOREIGN KEY FK_61DDE5BF10DAF24A');
        $this->addSql('ALTER TABLE courrier_action DROP FOREIGN KEY FK_61DDE5BF8BF41DC7');
        $this->addSql('ALTER TABLE courrier_destinataire DROP FOREIGN KEY FK_5DCF6EEA4F84F6E');
        $this->addSql('ALTER TABLE courrier_destinataire DROP FOREIGN KEY FK_5DCF6EE8BF41DC7');
        $this->addSql('ALTER TABLE courrier_imputation DROP FOREIGN KEY FK_3A0B83FBA76ED395');
        $this->addSql('ALTER TABLE courrier_imputation DROP FOREIGN KEY FK_3A0B83FB8BF41DC7');
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAAB03A8386');
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAAFFDF7169');
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAAB3257B87');
        $this->addSql('DROP TABLE courrier_action');
        $this->addSql('DROP TABLE courrier_destinataire');
        $this->addSql('DROP TABLE courrier_imputation');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('DROP TABLE destinataire');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE list_option');
    }

    private function seedListOptions(): void
    {
        $values = [
            ['nature', 'entrant', 'Arrivé', 10, 1, 1],
            ['nature', 'sortant', 'Départ', 20, 1, 1],
            ['nature', 'note_interne', 'Note interne', 30, 1, 1],
            ['status', 'en_cours', 'En cours', 10, 1, 1],
            ['status', 'traite', 'Traité', 20, 1, 1],
            ['status', 'urgent', 'Urgent', 30, 1, 1],
            ['type', 'administratif', 'Administratif', 10, 1, 0],
            ['type', 'facture', 'Facture', 20, 1, 0],
            ['type', 'rh', 'Ressources humaines (RH)', 30, 1, 0],
            ['type', 'juridique', 'Juridique', 40, 1, 0],
            ['type', 'technique', 'Technique', 50, 1, 0],
            ['type', 'invitation', 'Invitation', 60, 1, 0],
            ['type', 'rapport', 'Rapport', 70, 1, 0],
            ['type', 'autre', 'Autre', 80, 1, 0],
        ];

        foreach ($values as [$category, $value, $label, $position, $active, $locked]) {
            $this->addSql(
                'INSERT INTO list_option (category, value, label, position, active, locked) VALUES (?, ?, ?, ?, ?, ?)',
                [$category, $value, $label, $position, $active, $locked]
            );
        }
    }
}
