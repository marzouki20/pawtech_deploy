<?php

namespace App\DataFixtures;

use App\Entity\Evenement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class EvenementFixtures extends Fixture
{
    public const EVENT_REFERENCE_PREFIX = 'event_';
    public const EVENT_COUNT = 20;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $villes = ['Tunis', 'Sousse', 'Sfax', 'Bizerte', 'Nabeul', 'Monastir', 'Kairouan', 'Gabès'];
        $lieux = [
            'Parc du Belvédère', 'Centre Culturel', 'Hôtel de Ville', 'Place Centrale',
            'Salle des Conférences', 'Espace Vert Municipal', 'Centre Commercial',
            'Clinique Vétérinaire El Amal', 'Refuge Animal PawTech'
        ];

        $eventTemplates = [
            'VACCINATION' => [
                'Campagne Vaccination Animaux', 'Journée Vaccination Gratuite',
                'Vaccination Anti-Rabique', 'Campagne Santé Animale'
            ],
            'ADOPTION' => [
                'Weekend Adoption', 'Journée Portes Ouvertes Adoption',
                'Foire à l\'Adoption', 'Rencontrez Votre Compagnon'
            ],
            'SENSIBILISATION' => [
                'Conférence Bien-être Animal', 'Atelier Protection Animale',
                'Journée Mondiale des Animaux', 'Sensibilisation Maltraitance'
            ],
            'COLLECTE_DONS' => [
                'Collecte Nourriture Animaux', 'Marathon Solidaire PawTech',
                'Gala de Charité Animaux', 'Vente Caritative'
            ]
        ];

        // Descriptions réalistes par type d'événement
        $descriptions = [
            'VACCINATION' => [
                'Venez vacciner gratuitement vos animaux de compagnie contre les maladies courantes. Nos vétérinaires professionnels seront présents pour administrer les vaccins et répondre à toutes vos questions sur la santé de vos compagnons.',
                'Grande campagne de vaccination anti-rabique organisée en partenariat avec les autorités sanitaires locales. Tous les chiens et chats sont les bienvenus. Carnet de vaccination fourni gratuitement.',
                'Journée dédiée à la prévention des maladies animales. Vaccination, déparasitage et conseils vétérinaires gratuits pour tous les propriétaires d\'animaux. Inscription sur place.',
                'Notre équipe de vétérinaires bénévoles vous attend pour une journée de soins préventifs. Vaccinations à jour, vermifuges et traitements antiparasitaires disponibles gratuitement.'
            ],
            'ADOPTION' => [
                'Venez rencontrer nos adorables pensionnaires en quête d\'une famille aimante. Chiens, chats et autres animaux vous attendent. Tous nos animaux sont vaccinés, stérilisés et identifiés.',
                'Grande journée d\'adoption organisée par PawTech. Des dizaines d\'animaux attendent leur famille pour toujours. Conseillers présents pour vous aider à trouver le compagnon idéal.',
                'Ouvrez votre cœur et votre foyer à un animal abandonné. Notre équipe vous accompagne dans le processus d\'adoption et assure un suivi post-adoption personnalisé.',
                'Journée portes ouvertes au refuge. Visitez nos installations, rencontrez nos pensionnaires et repartez peut-être avec un nouveau membre de la famille. Collation offerte.'
            ],
            'SENSIBILISATION' => [
                'Conférence interactive sur le bien-être animal et les bonnes pratiques de propriétaire responsable. Intervenants experts et témoignages de bénévoles. Entrée libre.',
                'Atelier éducatif destiné aux enfants et aux familles pour apprendre à respecter et protéger les animaux. Activités ludiques et documentation pédagogique fournie.',
                'Rejoignez-nous pour une journée de sensibilisation contre la maltraitance animale. Expositions, témoignages et informations sur les actions à entreprendre face à la cruauté.',
                'Formation gratuite sur les premiers secours animaliers et la détection des signes de maltraitance. Ouverte à tous, particuliers et professionnels.'
            ],
            'COLLECTE_DONS' => [
                'Grande collecte de nourriture et d\'équipements pour nos animaux du refuge. Croquettes, pâtées, litière, couvertures et jouets sont les bienvenus. Chaque don compte !',
                'Événement caritatif au profit des animaux abandonnés. Vente de produits artisanaux, tombola et animations. Tous les bénéfices seront reversés au refuge PawTech.',
                'Marathon solidaire pour récolter des fonds destinés aux soins vétérinaires des animaux abandonnés. Participez en courant, marchant ou en faisant un don.',
                'Gala de charité annuel au profit de la protection animale. Dîner, ventes aux enchères et spectacle. Réservation obligatoire. Tenue de soirée appréciée.'
            ]
        ];

        for ($i = 0; $i < self::EVENT_COUNT; $i++) {
            $type = $faker->randomElement(Evenement::TYPES);
            $titreBase = $faker->randomElement($eventTemplates[$type]);
            $ville = $faker->randomElement($villes);

            $event = new Evenement();
            $event->setTitre($titreBase . ' - ' . $ville);
            $event->setDescription($faker->randomElement($descriptions[$type]));
            
            // Date de début entre maintenant et +6 mois
            $dateDebut = $faker->dateTimeBetween('-1 month', '+6 months');
            $event->setDateDebut($dateDebut);
            
            // Date de fin: même jour ou +1 à 3 jours
            $dateFin = clone $dateDebut;
            $dateFin->modify('+' . $faker->numberBetween(0, 3) . ' days');
            $dateFin->setTime($faker->numberBetween(16, 20), 0);
            $event->setDateFin($dateFin);

            $event->setLieu($faker->randomElement($lieux));
            $event->setVille($ville);
            $event->setCapaciteMax($faker->optional(0.7)->numberBetween(20, 200));
            $event->setType($type);
            
            // Statut basé sur la date
            if ($dateDebut < new \DateTime()) {
                $event->setStatut($faker->randomElement(['TERMINE', 'ANNULE', 'EN_COURS']));
            } else {
                $event->setStatut('PLANIFIE');
            }

            $manager->persist($event);
            $this->addReference(self::EVENT_REFERENCE_PREFIX . $i, $event);
        }

        $manager->flush();
    }

    public static function getEventCount(): int
    {
        return self::EVENT_COUNT;
    }
}
