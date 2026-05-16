<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516121835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video ADD page_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE video ADD CONSTRAINT FK_7CC7DA2CC4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7CC7DA2CC4663E4 ON video (page_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video DROP FOREIGN KEY FK_7CC7DA2CC4663E4');
        $this->addSql('DROP INDEX IDX_7CC7DA2CC4663E4 ON video');
        $this->addSql('ALTER TABLE video DROP page_id');
    }
}
