<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027004207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hashed, scoped, expiring and revocable registry read tokens';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE read_token (id VARCHAR(26) NOT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, label VARCHAR(100) NOT NULL, token_hash VARCHAR(64) NOT NULL, scopes JSONB NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, owner_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX read_token_hash_unique ON read_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_B7DDEF7E3C61F9 ON read_token (owner_id)');
        $this->addSql('ALTER TABLE read_token ADD CONSTRAINT FK_B7DDEF7E3C61F9 FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE read_token DROP CONSTRAINT FK_B7DDEF7E3C61F9');
        $this->addSql('DROP TABLE read_token');
    }
}
