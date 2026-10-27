<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026235652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Typed theme drafts and immutable theme release documents and generated files.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE theme (id VARCHAR(26) NOT NULL, revision INT DEFAULT 1 NOT NULL, document JSONB NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX theme_name_unique ON theme (name)');
        $this->addSql('CREATE TABLE theme_release (id VARCHAR(26) NOT NULL, document JSONB NOT NULL, files JSONB NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, version VARCHAR(32) NOT NULL, theme_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX theme_release_version_unique ON theme_release (theme_id, version)');
        $this->addSql('CREATE INDEX IDX_C724063E59027487 ON theme_release (theme_id)');
        $this->addSql('ALTER TABLE theme_release ADD CONSTRAINT FK_C724063E59027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE theme_release DROP CONSTRAINT FK_C724063E59027487');
        $this->addSql('DROP TABLE theme');
        $this->addSql('DROP TABLE theme_release');
    }
}
