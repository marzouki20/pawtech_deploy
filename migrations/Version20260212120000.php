<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore missing tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS dogs (id INT AUTO_INCREMENT NOT NULL, age INT DEFAULT NULL, gender VARCHAR(10) DEFAULT NULL, size VARCHAR(50) DEFAULT NULL, weight DOUBLE PRECISION DEFAULT NULL, vaccinated TINYINT(1) DEFAULT NULL, friendly_with_kids TINYINT(1) DEFAULT NULL, friendly_with_dogs TINYINT(1) DEFAULT NULL, friendly_with_cats TINYINT(1) DEFAULT NULL, health_status VARCHAR(100) DEFAULT NULL, adoption_status VARCHAR(50) DEFAULT NULL, arrival_date DATE DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, microchip_number VARCHAR(50) DEFAULT NULL, user_id INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE IF NOT EXISTS consultation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, dog_id INT NOT NULL, date DATETIME NOT NULL, type VARCHAR(50) NOT NULL, diagnostic LONGTEXT NOT NULL, traitement LONGTEXT DEFAULT NULL, INDEX IDX_964685A6A76ED395 (user_id), INDEX IDX_964685A6634DFEB (dog_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE IF NOT EXISTS suivi (id INT AUTO_INCREMENT NOT NULL, consultation_id INT NOT NULL, etat VARCHAR(50) NOT NULL, recommandation LONGTEXT NOT NULL, type VARCHAR(255) NOT NULL, prochaine_visite DATETIME NOT NULL, INDEX IDX_32EB8B30A42E8210 (consultation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE IF NOT EXISTS evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE IF NOT EXISTS participation (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, evenement_id INT DEFAULT NULL, INDEX IDX_AB55E24FA76CD395 (user_id), INDEX IDX_AB55E24F8F7B22E1 (evenement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A6634DFEB FOREIGN KEY (dog_id) REFERENCES dogs (id)');

        $this->addSql('ALTER TABLE suivi ADD CONSTRAINT FK_32EB8B30A42E8210 FOREIGN KEY (consultation_id) REFERENCES consultation (id)');

        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FA76CD395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F8F7B22E1 FOREIGN KEY (evenement_id) REFERENCES evenement (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A6634DFEB');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F8F7B22E1');
        $this->addSql('DROP TABLE IF EXISTS consultation');
        $this->addSql('DROP TABLE IF EXISTS evenement');
        $this->addSql('DROP TABLE IF EXISTS participation');
        $this->addSql('DROP TABLE IF EXISTS suivi');
        $this->addSql('DROP TABLE IF EXISTS dogs');
    }
}
