<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401232500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game table for Telegram game list bot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game (id SERIAL NOT NULL, url VARCHAR(2048) NOT NULL, description_text TEXT NOT NULL, description_entities JSON DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql("COMMENT ON COLUMN game.description_entities IS '(DC2Type:json)'");
        $this->addSql('CREATE INDEX IDX_E48FD905BF700BD5 ON game (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game');
    }
}
