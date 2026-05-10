<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI analysis columns to suivi table';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('suivi')) {
            return;
        }

        $table = $schema->getTable('suivi');

        if (!$table->hasColumn('emergency_level')) {
            $this->addSql('ALTER TABLE suivi ADD emergency_level VARCHAR(20) DEFAULT NULL');
        }

        if (!$table->hasColumn('ai_analysis_report')) {
            $this->addSql('ALTER TABLE suivi ADD ai_analysis_report LONGTEXT DEFAULT NULL');
        }

        if (!$table->hasColumn('affected_body_parts')) {
            $this->addSql('ALTER TABLE suivi ADD affected_body_parts JSON DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('suivi')) {
            return;
        }

        $table = $schema->getTable('suivi');

        if ($table->hasColumn('affected_body_parts')) {
            $this->addSql('ALTER TABLE suivi DROP affected_body_parts');
        }

        if ($table->hasColumn('ai_analysis_report')) {
            $this->addSql('ALTER TABLE suivi DROP ai_analysis_report');
        }

        if ($table->hasColumn('emergency_level')) {
            $this->addSql('ALTER TABLE suivi DROP emergency_level');
        }
    }
}
