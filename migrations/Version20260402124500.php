<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Steam metadata columns to game table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD steam_app_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD steam_price VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD steam_recent_review_summary VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD steam_overall_review_summary VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP steam_app_id');
        $this->addSql('ALTER TABLE game DROP steam_price');
        $this->addSql('ALTER TABLE game DROP steam_recent_review_summary');
        $this->addSql('ALTER TABLE game DROP steam_overall_review_summary');
    }
}
