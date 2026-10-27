<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Composer MCP authentication and recoverable editing.
 */
final class Version20261027203258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composer credentials, durable operation receipts, recovery history, preview bindings and OAuth server tables; existing content is unchanged.';
    }

    public function up(Schema $schema): void
    {
        // Additive migration; no existing catalogue, theme, draft or preference rows change.
        $this->addSql('CREATE TABLE compose_token (id VARCHAR(26) NOT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, label VARCHAR(100) NOT NULL, token_hash VARCHAR(64) NOT NULL, scopes JSONB NOT NULL, catalogue_patterns JSONB NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, owner_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX compose_token_hash ON compose_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_97C40A8C7E3C61F9 ON compose_token (owner_id)');
        $this->addSql('CREATE TABLE composition_operation (id VARCHAR(26) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, operation VARCHAR(64) NOT NULL, target VARCHAR(100) NOT NULL, operation_id VARCHAR(64) NOT NULL, payload_hash VARCHAR(64) NOT NULL, result JSONB NOT NULL, owner_id VARCHAR(26) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX composition_operation_key ON composition_operation (owner_id, operation, target, operation_id)');
        $this->addSql('CREATE INDEX IDX_7DB4CD237E3C61F9 ON composition_operation (owner_id)');
        $this->addSql('CREATE TABLE composition_preview (revision INT NOT NULL, job_id VARCHAR(26) NOT NULL, item_id VARCHAR(26) NOT NULL, PRIMARY KEY (job_id))');
        $this->addSql('CREATE INDEX IDX_A4F23848126F525E ON composition_preview (item_id)');
        $this->addSql('CREATE TABLE composition_revision (id VARCHAR(26) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, revision INT NOT NULL, document JSONB NOT NULL, title VARCHAR(120) NOT NULL, description TEXT NOT NULL, summary VARCHAR(200) NOT NULL, item_id VARCHAR(26) NOT NULL, actor_id VARCHAR(26) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX composition_revision_number ON composition_revision (item_id, revision)');
        $this->addSql('CREATE INDEX IDX_33A09BD5126F525E ON composition_revision (item_id)');
        $this->addSql('CREATE INDEX IDX_33A09BD510DAF24A ON composition_revision (actor_id)');
        $this->addSql('CREATE TABLE oauth2_access_token (identifier CHAR(80) NOT NULL, expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_identifier VARCHAR(128) DEFAULT NULL, scopes TEXT DEFAULT NULL, revoked BOOLEAN NOT NULL, client VARCHAR(32) NOT NULL, PRIMARY KEY (identifier))');
        $this->addSql('CREATE INDEX IDX_454D9673C7440455 ON oauth2_access_token (client)');
        $this->addSql('CREATE TABLE oauth2_authorization_code (identifier CHAR(80) NOT NULL, expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_identifier VARCHAR(128) DEFAULT NULL, scopes TEXT DEFAULT NULL, revoked BOOLEAN NOT NULL, client VARCHAR(32) NOT NULL, PRIMARY KEY (identifier))');
        $this->addSql('CREATE INDEX IDX_509FEF5FC7440455 ON oauth2_authorization_code (client)');
        $this->addSql('CREATE TABLE oauth2_client (name VARCHAR(128) NOT NULL, secret VARCHAR(128) DEFAULT NULL, redirect_uris TEXT DEFAULT NULL, grants TEXT DEFAULT NULL, scopes TEXT DEFAULT NULL, active BOOLEAN NOT NULL, allow_plain_text_pkce BOOLEAN DEFAULT false NOT NULL, identifier VARCHAR(32) NOT NULL, PRIMARY KEY (identifier))');
        $this->addSql('CREATE TABLE oauth2_refresh_token (identifier CHAR(80) NOT NULL, expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked BOOLEAN NOT NULL, access_token CHAR(80) DEFAULT NULL, PRIMARY KEY (identifier))');
        $this->addSql('CREATE INDEX IDX_4DD90732B6A2DD68 ON oauth2_refresh_token (access_token)');
        $this->addSql('ALTER TABLE compose_token ADD CONSTRAINT FK_97C40A8C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE composition_operation ADD CONSTRAINT FK_7DB4CD237E3C61F9 FOREIGN KEY (owner_id) REFERENCES studio_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE composition_preview ADD CONSTRAINT FK_A4F23848BE04EA9 FOREIGN KEY (job_id) REFERENCES preview_job (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE composition_preview ADD CONSTRAINT FK_A4F23848126F525E FOREIGN KEY (item_id) REFERENCES catalogue_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE composition_revision ADD CONSTRAINT FK_33A09BD5126F525E FOREIGN KEY (item_id) REFERENCES catalogue_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE composition_revision ADD CONSTRAINT FK_33A09BD510DAF24A FOREIGN KEY (actor_id) REFERENCES studio_user (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE oauth2_access_token ADD CONSTRAINT FK_454D9673C7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth2_authorization_code ADD CONSTRAINT FK_509FEF5FC7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth2_refresh_token ADD CONSTRAINT FK_4DD90732B6A2DD68 FOREIGN KEY (access_token) REFERENCES oauth2_access_token (identifier) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE compose_token DROP CONSTRAINT FK_97C40A8C7E3C61F9');
        $this->addSql('ALTER TABLE composition_operation DROP CONSTRAINT FK_7DB4CD237E3C61F9');
        $this->addSql('ALTER TABLE composition_preview DROP CONSTRAINT FK_A4F23848BE04EA9');
        $this->addSql('ALTER TABLE composition_preview DROP CONSTRAINT FK_A4F23848126F525E');
        $this->addSql('ALTER TABLE composition_revision DROP CONSTRAINT FK_33A09BD5126F525E');
        $this->addSql('ALTER TABLE composition_revision DROP CONSTRAINT FK_33A09BD510DAF24A');
        $this->addSql('ALTER TABLE oauth2_access_token DROP CONSTRAINT FK_454D9673C7440455');
        $this->addSql('ALTER TABLE oauth2_authorization_code DROP CONSTRAINT FK_509FEF5FC7440455');
        $this->addSql('ALTER TABLE oauth2_refresh_token DROP CONSTRAINT FK_4DD90732B6A2DD68');
        $this->addSql('DROP TABLE compose_token');
        $this->addSql('DROP TABLE composition_operation');
        $this->addSql('DROP TABLE composition_preview');
        $this->addSql('DROP TABLE composition_revision');
        $this->addSql('DROP TABLE oauth2_access_token');
        $this->addSql('DROP TABLE oauth2_authorization_code');
        $this->addSql('DROP TABLE oauth2_client');
        $this->addSql('DROP TABLE oauth2_refresh_token');
    }
}
