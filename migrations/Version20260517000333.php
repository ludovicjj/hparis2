<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517000333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `option` CHANGE external_id external_id VARCHAR(100) DEFAULT NULL, CHANGE provider provider VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE team CHANGE external_id external_id VARCHAR(100) DEFAULT NULL, CHANGE provider provider VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `option` CHANGE external_id external_id VARCHAR(100) NOT NULL, CHANGE provider provider VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE team CHANGE external_id external_id VARCHAR(100) NOT NULL, CHANGE provider provider VARCHAR(32) NOT NULL');
    }
}
