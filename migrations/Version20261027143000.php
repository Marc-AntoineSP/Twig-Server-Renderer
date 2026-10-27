<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20261027143000 extends AbstractMigration
{
    public function getDescription(): string { return 'Explicit theme families, immutable application snapshots and personal appearance preferences; preserves existing theme documents'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE theme_family (id VARCHAR(26) NOT NULL, light_theme_id VARCHAR(26) DEFAULT NULL, dark_theme_id VARCHAR(26) DEFAULT NULL, stable_key VARCHAR(100) NOT NULL, label VARCHAR(120) NOT NULL, built_in BOOLEAN NOT NULL, revision INT DEFAULT 1 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX theme_family_key_unique ON theme_family (stable_key)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FAMILY_LIGHT ON theme_family (light_theme_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FAMILY_DARK ON theme_family (dark_theme_id)');
        $this->addSql('ALTER TABLE theme_family ADD CONSTRAINT FK_FAMILY_LIGHT FOREIGN KEY (light_theme_id) REFERENCES theme (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE theme_family ADD CONSTRAINT FK_FAMILY_DARK FOREIGN KEY (dark_theme_id) REFERENCES theme (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE appearance_snapshot (id VARCHAR(64) NOT NULL, theme_id VARCHAR(26) DEFAULT NULL, owner_id VARCHAR(26) DEFAULT NULL, source_revision INT DEFAULT NULL, document JSONB NOT NULL, css TEXT NOT NULL, css_hash VARCHAR(64) NOT NULL, kind VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_APPEARANCE_SOURCE ON appearance_snapshot (theme_id)');
        $this->addSql('CREATE INDEX IDX_APPEARANCE_OWNER ON appearance_snapshot (owner_id)');
        $this->addSql('ALTER TABLE appearance_snapshot ADD CONSTRAINT FK_APPEARANCE_SOURCE FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE appearance_snapshot ADD CONSTRAINT FK_APPEARANCE_OWNER FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE appearance_preference (owner_id VARCHAR(26) NOT NULL, family_id VARCHAR(26) DEFAULT NULL, light_snapshot_id VARCHAR(64) DEFAULT NULL, dark_snapshot_id VARCHAR(64) DEFAULT NULL, mode VARCHAR(6) NOT NULL, revision INT DEFAULT 1 NOT NULL, PRIMARY KEY(owner_id))');
        foreach (['family' => ['theme_family', 'id'], 'light_snapshot' => ['appearance_snapshot', 'id'], 'dark_snapshot' => ['appearance_snapshot', 'id']] as $column => [$table, $key]) {
            $this->addSql('CREATE INDEX IDX_PREFERENCE_'.strtoupper($column).' ON appearance_preference ('.$column.'_id)');
            $this->addSql('ALTER TABLE appearance_preference ADD CONSTRAINT FK_PREFERENCE_'.strtoupper($column).' FOREIGN KEY ('.$column.'_id) REFERENCES '.$table.' ('.$key.') ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        }
        $this->addSql('ALTER TABLE appearance_preference ADD CONSTRAINT FK_PREFERENCE_OWNER FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Pair only the explicitly managed reference relationship, never a display name/suffix.
        $families = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, name, document FROM theme ORDER BY id') as $row) {
            $doc = json_decode($row['document']); $p = $doc->provenance ?? null;
            $known = ($p->managed ?? false) && ($p->collection ?? '') === 'website-inspired' && isset($p->reference);
            $key = $known ? 'websites/ref-'.substr(hash('sha256', $p->reference), 0, 24) : 'legacy/theme-'.strtolower($row['id']);
            $mode = $doc->colorScheme;
            if (isset($families[$key][$mode])) $key = 'legacy/theme-'.strtolower($row['id']);
            $families[$key] ??= ['id' => (string) new Ulid(), 'label' => $known ? $p->reference.'-inspired' : $row['name']];
            $families[$key][$mode] = $row['id'];
        }
        foreach ($families as $key => $family) $this->addSql('INSERT INTO theme_family (id, stable_key, label, built_in, revision, light_theme_id, dark_theme_id) VALUES (?, ?, ?, false, 1, ?, ?)', [$family['id'], $key, mb_substr($family['label'], 0, 120), $family['light'] ?? null, $family['dark'] ?? null]);
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE appearance_preference'); $this->addSql('DROP TABLE appearance_snapshot'); $this->addSql('DROP TABLE theme_family');
    }
}
