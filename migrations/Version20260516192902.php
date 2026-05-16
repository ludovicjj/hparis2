<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516192902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE video_picture DROP FOREIGN KEY `FK_F336A62429C1004E`');
        $this->addSql('ALTER TABLE video_picture DROP FOREIGN KEY `FK_F336A624B03A8386`');
        $this->addSql('DROP TABLE video_picture');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE video_picture (id INT AUTO_INCREMENT NOT NULL, lightbox_path VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, thumbnail_path VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, video_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_F336A624B03A8386 (created_by_id), INDEX IDX_F336A62429C1004E (video_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE video_picture ADD CONSTRAINT `FK_F336A62429C1004E` FOREIGN KEY (video_id) REFERENCES video (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE video_picture ADD CONSTRAINT `FK_F336A624B03A8386` FOREIGN KEY (created_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
