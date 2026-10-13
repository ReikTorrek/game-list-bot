<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013182308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_e48fd905bf700bd5');
        $this->addSql('ALTER TABLE game ADD is_early_access BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE game ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE game ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN game.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN game.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER INDEX uniq_game_steam_app_id RENAME TO UNIQ_232B318C4C1A4273');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE game DROP is_early_access');
        $this->addSql('ALTER TABLE game ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE game ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN game.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN game.updated_at IS NULL');
        $this->addSql('CREATE INDEX idx_e48fd905bf700bd5 ON game (status)');
        $this->addSql('ALTER INDEX uniq_232b318c4c1a4273 RENAME TO uniq_game_steam_app_id');
    }
}
