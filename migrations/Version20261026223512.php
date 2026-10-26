<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026223512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue identity, optimistic JSONB drafts and immutable release metadata.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalogue_item (id VARCHAR(26) NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, namespace VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, kind VARCHAR(20) NOT NULL, title VARCHAR(120) NOT NULL, description TEXT NOT NULL, tags JSONB NOT NULL, edit_mode VARCHAR(20) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX catalogue_updated_idx ON catalogue_item (updated_at)');
        $this->addSql('CREATE UNIQUE INDEX catalogue_name_unique ON catalogue_item (namespace, slug)');
        $this->addSql('CREATE TABLE catalogue_release (id VARCHAR(26) NOT NULL, manifest_data JSONB NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, version VARCHAR(32) NOT NULL, archive_hash VARCHAR(64) NOT NULL, archive_key VARCHAR(240) NOT NULL, item_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX release_version_unique ON catalogue_release (item_id, version)');
        $this->addSql('CREATE INDEX IDX_DD9294E8126F525E ON catalogue_release (item_id)');
        $this->addSql('CREATE TABLE draft (revision INT DEFAULT 1 NOT NULL, manifest_data JSONB NOT NULL, source_map JSONB NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, item_id VARCHAR(26) NOT NULL, updated_by_id VARCHAR(26) DEFAULT NULL, PRIMARY KEY (item_id))');
        $this->addSql('CREATE INDEX IDX_467C9694896DBBDE ON draft (updated_by_id)');
        $this->addSql('ALTER TABLE catalogue_release ADD CONSTRAINT FK_DD9294E8126F525E FOREIGN KEY (item_id) REFERENCES catalogue_item (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE draft ADD CONSTRAINT FK_467C9694126F525E FOREIGN KEY (item_id) REFERENCES catalogue_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE draft ADD CONSTRAINT FK_467C9694896DBBDE FOREIGN KEY (updated_by_id) REFERENCES studio_user (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalogue_release DROP CONSTRAINT FK_DD9294E8126F525E');
        $this->addSql('ALTER TABLE draft DROP CONSTRAINT FK_467C9694126F525E');
        $this->addSql('ALTER TABLE draft DROP CONSTRAINT FK_467C9694896DBBDE');
        $this->addSql('DROP TABLE catalogue_item');
        $this->addSql('DROP TABLE catalogue_release');
        $this->addSql('DROP TABLE draft');
    }
}
