<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516183912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `option` (id INT AUTO_INCREMENT NOT NULL, url LONGTEXT DEFAULT NULL, thumbnail_url LONGTEXT DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, visibility TINYINT DEFAULT 1 NOT NULL, token VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, external_id VARCHAR(100) NOT NULL, provider VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, page_id INT DEFAULT NULL, INDEX IDX_5A8600B0C4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE option_picture (id INT AUTO_INCREMENT NOT NULL, lightbox_path VARCHAR(255) NOT NULL, thumbnail_path VARCHAR(255) NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, option_id INT NOT NULL, INDEX IDX_E2F58FCB03A8386 (created_by_id), INDEX IDX_E2F58FCA7C41D6F (option_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, url LONGTEXT DEFAULT NULL, thumbnail_url LONGTEXT DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, visibility TINYINT DEFAULT 1 NOT NULL, token VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, external_id VARCHAR(100) NOT NULL, provider VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, page_id INT DEFAULT NULL, INDEX IDX_C4E0A61FC4663E4 (page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_picture (id INT AUTO_INCREMENT NOT NULL, lightbox_path VARCHAR(255) NOT NULL, thumbnail_path VARCHAR(255) NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, team_id INT NOT NULL, INDEX IDX_E9A23EBEB03A8386 (created_by_id), INDEX IDX_E9A23EBE296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `option` ADD CONSTRAINT FK_5A8600B0C4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE option_picture ADD CONSTRAINT FK_E2F58FCB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE option_picture ADD CONSTRAINT FK_E2F58FCA7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61FC4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE team_picture ADD CONSTRAINT FK_E9A23EBEB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team_picture ADD CONSTRAINT FK_E9A23EBE296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `option` DROP FOREIGN KEY FK_5A8600B0C4663E4');
        $this->addSql('ALTER TABLE option_picture DROP FOREIGN KEY FK_E2F58FCB03A8386');
        $this->addSql('ALTER TABLE option_picture DROP FOREIGN KEY FK_E2F58FCA7C41D6F');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61FC4663E4');
        $this->addSql('ALTER TABLE team_picture DROP FOREIGN KEY FK_E9A23EBEB03A8386');
        $this->addSql('ALTER TABLE team_picture DROP FOREIGN KEY FK_E9A23EBE296CD8AE');
        $this->addSql('DROP TABLE `option`');
        $this->addSql('DROP TABLE option_picture');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_picture');
    }
}
