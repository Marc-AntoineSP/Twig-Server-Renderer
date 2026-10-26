<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026225507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Owner-scoped, expiring preview jobs for isolated worker orchestration.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE preview_job (id VARCHAR(26) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, error JSONB DEFAULT NULL, request_sequence INT NOT NULL, input_hash VARCHAR(64) NOT NULL, payload JSONB NOT NULL, owner_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX preview_owner_status_idx ON preview_job (owner_id, status, expires_at)');
        $this->addSql('CREATE INDEX IDX_81C067587E3C61F9 ON preview_job (owner_id)');
        $this->addSql('ALTER TABLE preview_job ADD CONSTRAINT FK_81C067587E3C61F9 FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE preview_job DROP CONSTRAINT FK_81C067587E3C61F9');
        $this->addSql('DROP TABLE preview_job');
    }
}
