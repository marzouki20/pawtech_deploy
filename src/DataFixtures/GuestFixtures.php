<?php

namespace App\DataFixtures;

use App\Entity\Guest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class GuestFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $roles = Guest::ROLES;

        $organisations = [
            'Association Protection Animale Tunisie',
            'Clinique Vétérinaire El Amal',
            'Faculté de Médecine Vétérinaire de Sidi Thabet',
            'ONG Animaux Sans Frontières',
            'Institut Pasteur de Tunis',
            'SPA Tunisie',
            'Refuge Animal La Marsa'
        ];

        $prenoms = ['Dr. Sami', 'Dr. Leila', 'Prof. Mohamed', 'Dr. Amira', 'Dr. Karim', 'Prof. Fatma', 'Dr. Youssef', 'Dr. Nour'];
        $noms = ['Ben Salah', 'Triki', 'Mansouri', 'Haddad', 'Bouguerra', 'Ammar', 'Riahi', 'Jebali'];

        // Biographies réalistes pour les invités
        $bios = [
            'Vétérinaire spécialisé en médecine des animaux de compagnie avec plus de 15 ans d\'expérience. Diplômé de l\'École Nationale de Médecine Vétérinaire de Sidi Thabet.',
            'Professeur agrégé en sciences vétérinaires et chercheur en épidémiologie animale. Auteur de nombreuses publications sur la santé publique vétérinaire en Tunisie.',
            'Directrice du refuge animal de La Marsa depuis 2015. Passionnée par la cause animale, elle a contribué à l\'adoption de plus de 2000 animaux.',
            'Spécialiste en comportement animal et éducateur canin certifié. Intervenant régulier dans les médias sur les questions de bien-être animal.',
            'Fondateur de l\'association Protection Animale Tunisie. Militant actif pour les droits des animaux depuis plus de 20 ans.',
            'Chirurgienne vétérinaire spécialisée dans les interventions d\'urgence et la traumatologie. Bénévole active auprès des refuges de la région.',
            'Expert en nutrition animale et consultant pour plusieurs marques d\'alimentation premium. Conférencier apprécié sur les thèmes de santé préventive.',
            'Responsable du programme de stérilisation de masse pour les animaux errants. Coordinatrice des campagnes de vaccination dans les zones rurales.',
            'Psychologue animalier et thérapeute assisté par l\'animal. Pionnière de la zoothérapie en Tunisie auprès des personnes âgées et des enfants.',
            'Ancien directeur de l\'Institut Pasteur de Tunis, spécialiste des zoonoses et maladies transmissibles entre l\'animal et l\'homme.'
        ];

        $eventCount = EvenementFixtures::getEventCount();

        // Ajouter 1-3 invités à chaque événement
        for ($eventIndex = 0; $eventIndex < $eventCount; $eventIndex++) {
                $event = $this->getReference(EvenementFixtures::EVENT_REFERENCE_PREFIX . $eventIndex, \App\Entity\Evenement::class);
            
            $numGuests = $faker->numberBetween(1, 3);

            for ($g = 0; $g < $numGuests; $g++) {
                $guest = new Guest();
                $guest->setPrenom($faker->randomElement($prenoms));
                $guest->setNom($faker->randomElement($noms));
                $guest->setEmail($faker->unique()->safeEmail());
                $guest->setPhone($faker->optional(0.8)->numerify('9#######'));
                $guest->setOrganisation($faker->randomElement($organisations));
                $guest->setBio($faker->randomElement($bios));
                $guest->setRole($faker->randomElement($roles));
                $guest->setEvenement($event);

                $manager->persist($guest);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            EvenementFixtures::class,
        ];
    }
}
