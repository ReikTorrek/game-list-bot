<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Steam name column to game table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD steam_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP steam_name');
    }
}
