<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402134500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Steam rub price column to game table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD steam_price_rub VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP steam_price_rub');
    }
}
