<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026232516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Private per-item renderer workspace presets with bounded typed settings.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE renderer_preset (id VARCHAR(26) NOT NULL, settings JSONB NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, name VARCHAR(80) NOT NULL, owner_id VARCHAR(26) NOT NULL, item_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX renderer_workspace_unique ON renderer_preset (owner_id, item_id)');
        $this->addSql('CREATE INDEX IDX_CD88E9117E3C61F9 ON renderer_preset (owner_id)');
        $this->addSql('CREATE INDEX IDX_CD88E911126F525E ON renderer_preset (item_id)');
        $this->addSql('ALTER TABLE renderer_preset ADD CONSTRAINT FK_CD88E9117E3C61F9 FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE renderer_preset ADD CONSTRAINT FK_CD88E911126F525E FOREIGN KEY (item_id) REFERENCES catalogue_item (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE renderer_preset DROP CONSTRAINT FK_CD88E9117E3C61F9');
        $this->addSql('ALTER TABLE renderer_preset DROP CONSTRAINT FK_CD88E911126F525E');
        $this->addSql('DROP TABLE renderer_preset');
    }
}
