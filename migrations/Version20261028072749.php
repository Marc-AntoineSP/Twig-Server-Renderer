<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Personal favorites; existing catalogue and appearance records stay untouched.
 */
final class Version20261028072749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Personal favorites by stable theme variant or catalogue identity, unique per user, with deletion cleanup.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE personal_favorite (id VARCHAR(26) NOT NULL, theme_id VARCHAR(26) DEFAULT NULL, catalogue_item_id VARCHAR(26) DEFAULT NULL, owner_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE personal_favorite ADD CONSTRAINT favorite_exactly_one_target CHECK ((theme_id IS NOT NULL) <> (catalogue_item_id IS NOT NULL))');
        $this->addSql('CREATE UNIQUE INDEX favorite_user_theme_unique ON personal_favorite (owner_id, theme_id)');
        $this->addSql('CREATE UNIQUE INDEX favorite_user_item_unique ON personal_favorite (owner_id, catalogue_item_id)');
        $this->addSql('CREATE INDEX IDX_C4FA509059027487 ON personal_favorite (theme_id)');
        $this->addSql('CREATE INDEX IDX_C4FA509051BAC5AB ON personal_favorite (catalogue_item_id)');
        $this->addSql('CREATE INDEX IDX_C4FA50907E3C61F9 ON personal_favorite (owner_id)');
        $this->addSql('ALTER TABLE personal_favorite ADD CONSTRAINT FK_C4FA509059027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE personal_favorite ADD CONSTRAINT FK_C4FA509051BAC5AB FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE personal_favorite ADD CONSTRAINT FK_C4FA50907E3C61F9 FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personal_favorite DROP CONSTRAINT FK_C4FA509059027487');
        $this->addSql('ALTER TABLE personal_favorite DROP CONSTRAINT FK_C4FA509051BAC5AB');
        $this->addSql('ALTER TABLE personal_favorite DROP CONSTRAINT FK_C4FA50907E3C61F9');
        $this->addSql('DROP TABLE personal_favorite');
    }
}
