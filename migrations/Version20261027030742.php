<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027030742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lease isolated preview attempts for safe recovery after consumer interruption';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preview_job ADD started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE preview_job ADD run_token VARCHAR(32) DEFAULT NULL');
        $this->addSql("UPDATE preview_job SET status = 'queued' WHERE status = 'running'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE preview_job DROP started_at');
        $this->addSql('ALTER TABLE preview_job DROP run_token');
    }
}
