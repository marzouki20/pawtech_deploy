<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create iot_device table for IoT device configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE iot_device (
            id INT AUTO_INCREMENT NOT NULL,
            station_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            device_type VARCHAR(50) NOT NULL DEFAULT \'ESP32\',
            device_id VARCHAR(100) DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'inactive\',
            firmware_version VARCHAR(50) DEFAULT NULL,
            wifi_ssid VARCHAR(100) DEFAULT NULL,
            wifi_password VARCHAR(255) DEFAULT NULL,
            api_server_url VARCHAR(255) DEFAULT NULL,
            api_endpoint VARCHAR(100) DEFAULT NULL,
            sensor_config JSON DEFAULT NULL,
            reporting_interval INT DEFAULT NULL,
            heartbeat_interval INT DEFAULT NULL,
            last_seen DATETIME DEFAULT NULL,
            last_heartbeat DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_iot_device_station (station_id),
            INDEX idx_iot_device_device_id (device_id),
            INDEX idx_iot_device_status (status),
            CONSTRAINT fk_iot_device_station FOREIGN KEY (station_id) REFERENCES observation_station (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE iot_device');
    }
}
