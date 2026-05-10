<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow storing avatar as Base64 data URI in user.user_image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user MODIFY user_image LONGTEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user MODIFY user_image VARCHAR(255) NOT NULL');
    }
}

