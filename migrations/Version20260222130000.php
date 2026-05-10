<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI analysis and emergency fields to suivi table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi
            ADD ai_analysis_report LONGTEXT DEFAULT NULL,
            ADD affected_body_parts JSON DEFAULT NULL,
            ADD emergency_level VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi
            DROP COLUMN ai_analysis_report,
            DROP COLUMN affected_body_parts,
            DROP COLUMN emergency_level');
    }
}

