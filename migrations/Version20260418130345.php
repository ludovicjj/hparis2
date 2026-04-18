<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418130345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gallery_category (gallery_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_33C1CB7A4E7AF8F (gallery_id), INDEX IDX_33C1CB7A12469DE2 (category_id), PRIMARY KEY (gallery_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE gallery_category ADD CONSTRAINT FK_33C1CB7A4E7AF8F FOREIGN KEY (gallery_id) REFERENCES gallery (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gallery_category ADD CONSTRAINT FK_33C1CB7A12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE gallery_category DROP FOREIGN KEY FK_33C1CB7A4E7AF8F');
        $this->addSql('ALTER TABLE gallery_category DROP FOREIGN KEY FK_33C1CB7A12469DE2');
        $this->addSql('DROP TABLE gallery_category');
    }
}
