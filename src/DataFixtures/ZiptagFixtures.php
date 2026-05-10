<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Ziptag;

class ZiptagFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $ziptags = [
            [
                'serial_number'     => 'ZT-K9PQR47M',
                'firmware_version'  => 'v1.0.1',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-VN8XW19J',
                'firmware_version'  => 'v1.0.0',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-H4MZF62P',
                'firmware_version'  => 'v1.0.2',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-R7TGL85K',
                'firmware_version'  => 'v1.0.0',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-Q2JYB03N',
                'firmware_version'  => 'v1.1.0',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-F9XCV28L',
                'firmware_version'  => 'v1.0.1',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-M3NPD71S',
                'firmware_version'  => 'v1.0.0',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-W6BHK94T',
                'firmware_version'  => 'v1.0.3',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-Y1LQS56R',
                'firmware_version'  => 'v1.0.0',
                'model'             => 'Ziptag Pro',
            ],
            [
                'serial_number'     => 'ZT-P8GTR20V',
                'firmware_version'  => 'v1.0.2',
                'model'             => 'Ziptag Pro',
            ],
        ];

        foreach ($ziptags as $data) {
            $ziptag = new Ziptag();

            $ziptag->setSerialNumber($data['serial_number']);
            $ziptag->setFirmwareVersion($data['firmware_version']);
            $ziptag->setModel($data['model']);

            // If you have a relation to Dog and want to link some of them:
            // $ziptag->setDog($this->getReference('dog-' . strtolower($someDogName)));

            $manager->persist($ziptag);
        }

        $manager->flush();
    }
}
