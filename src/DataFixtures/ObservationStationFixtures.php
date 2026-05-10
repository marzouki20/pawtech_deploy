<?php

namespace App\DataFixtures;

use App\Entity\ObservationStation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ObservationStationFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $stationsData = [
            [
                'code' => '123456',
                'zone' => 'djerba_Nord',
                'localisation' => '35.8765, 10.9876',
                'statut' => 'active',
            ],
            [
                'code' => '234567',
                'zone' => 'djerba_Sud',
                'localisation' => '35.6543, 10.7654',
                'statut' => 'active',
            ],
            [
                'code' => '345678',
                'zone' => 'houmt_Souk',
                'localisation' => '35.7890, 10.8765',
                'statut' => 'active',
            ],
            [
                'code' => '456789',
                'zone' => 'midoun_Nord',
                'localisation' => '35.9012, 10.7654',
                'statut' => 'inactive',
            ],
            [
                'code' => '567890',
                'zone' => 'djerba_Nord',
                'localisation' => '35.8901, 10.9987',
                'statut' => 'maintenance',
            ],
            [
                'code' => '678901',
                'zone' => 'djerba_Sud',
                'localisation' => '35.6789, 10.6543',
                'statut' => 'active',
            ],
            [
                'code' => '789012',
                'zone' => 'houmt_Souk',
                'localisation' => '35.7654, 10.8901',
                'statut' => 'active',
            ],
            [
                'code' => '890123',
                'zone' => 'midoun_Nord',
                'localisation' => '35.9234, 10.7890',
                'statut' => 'active',
            ],
            [
                'code' => '901234',
                'zone' => 'djerba_Nord',
                'localisation' => '35.8456, 10.9456',
                'statut' => 'inactive',
            ],
            [
                'code' => '012345',
                'zone' => 'djerba_Sud',
                'localisation' => '35.6123, 10.6789',
                'statut' => 'active',
            ],
        ];

        foreach ($stationsData as $data) {
            $station = new ObservationStation();

            $station->setCode($data['code']);
            $station->setZone($data['zone']);
            $station->setLocalisation($data['localisation']);
            $station->setStatut($data['statut']);

            $manager->persist($station);
        }

        $manager->flush();
    }
}
