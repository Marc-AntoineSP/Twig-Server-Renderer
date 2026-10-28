<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Personal profile metadata.
 */
final class Version20261027215409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional personal display name; preserve account identities and existing fields.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE studio_user ADD display_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE studio_user DROP display_name');
    }
}
