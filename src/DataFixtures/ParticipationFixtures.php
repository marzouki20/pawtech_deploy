<?php

namespace App\DataFixtures;

use App\Entity\Participation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ParticipationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Skipped: UserFixtures doesn't exist in this project
        // Participations will be created through the public event registration form
        // or manually in the admin panel
        
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            EvenementFixtures::class,
        ];
    }
}
