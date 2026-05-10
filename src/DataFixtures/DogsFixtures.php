<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Dogs;
use DateTime;

class DogsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $dogsData = [
            [
                'name' => 'Bella',
                'breed' => 'Golden Retriever',
                'age' => 3,
                'gender' => 'Female',
                'size' => 'Large',
                'weight' => 28,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '14/01/2026',
                'microchipNumber' => '819625190369601',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => true,
                'description' => 'Sweet, playful, very social',
                'imageUrl' => 'https://images.unsplash.com/photo-1552053831-71594a27632d?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Luna',
                'breed' => 'Shih Tzu',
                'age' => 4,
                'gender' => 'Female',
                'size' => 'Small',
                'weight' => 7,
                'healthStatus' => 'Minor issues',
                'adoptionStatus' => 'Adopted',
                'arrivalDate' => '05/02/2026',
                'microchipNumber' => '819625190369603',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => false,
                'description' => 'Calm, needs regular eye care',
                'imageUrl' => 'https://pet-health-content-media.chewy.com/wp-content/uploads/2024/09/11171635/202105GettyImages-1467947700-scaled-1.jpg',
            ],
            [
                'name' => 'Milo',
                'breed' => 'Pug',
                'age' => 3,
                'gender' => 'Male',
                'size' => 'Small',
                'weight' => 8,
                'healthStatus' => 'Chronic condition',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '22/12/2025',
                'microchipNumber' => '819625190369607',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => false,
                'description' => 'Breathing condition monitored',
                'imageUrl' => 'https://images.unsplash.com/photo-1596492784531-6e6eb5ea9993?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Coco',
                'breed' => 'Maltese',
                'age' => 7,
                'gender' => 'Female',
                'size' => 'XS',
                'weight' => 4,
                'healthStatus' => 'Minor issues',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '19/12/2025',
                'microchipNumber' => '819625190369609',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => true,
                'description' => 'Senior dog, gentle and cuddly',
                'imageUrl' => 'https://assets.orvis.com/is/image/orvisprd/AdobeStock_104871188',
            ],
            [
                'name' => 'Pixie',
                'breed' => 'Chihuahua',
                'age' => 2,
                'gender' => 'Female',
                'size' => 'XS',
                'weight' => 2.8,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '10/02/2026',
                'microchipNumber' => '819625190369611',
                'vaccinated' => true,
                'goodWithKids' => 'Moderate',
                'goodWithDogs' => 'Moderate',
                'goodWithCats' => false,
                'description' => 'Tiny but bold personality',
                'imageUrl' => 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Teddy',
                'breed' => 'Pomeranian',
                'age' => 1,
                'gender' => 'Male',
                'size' => 'XS',
                'weight' => 3.2,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '05/02/2026',
                'microchipNumber' => '819625190369612',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => 'Moderate',
                'description' => 'Fluffy pom-pom, energetic puppy',
                'imageUrl' => 'https://www.dogster.com/wp-content/uploads/2023/03/owner-holding-teddy-bear-pomeranian_Varvara-Serebrova_Shutterstock.jpg',
            ],
            [
                'name' => 'Daisy',
                'breed' => 'Samoyed',
                'age' => 5,
                'gender' => 'Female',
                'size' => 'Medium',
                'weight' => 22,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '28/12/2025',
                'microchipNumber' => '819625190369605',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => true,
                'description' => 'Very friendly, fluffy cloud',
                'imageUrl' => 'https://images.unsplash.com/photo-1601758177266-bc599de87707?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Max',
                'breed' => 'Beagle',
                'age' => 2,
                'gender' => 'Male',
                'size' => 'Medium',
                'weight' => 12,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Reserved',
                'arrivalDate' => '20/12/2025',
                'microchipNumber' => '819625190369602',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => true,
                'description' => 'Energetic with a curious nose',
                'imageUrl' => 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Rosie',
                'breed' => 'Cocker Spaniel',
                'age' => 4,
                'gender' => 'Female',
                'size' => 'Medium',
                'weight' => 13,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '12/01/2026',
                'microchipNumber' => '819625190369613',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => true,
                'description' => 'Affectionate, loves everyone',
                'imageUrl' => 'https://images.unsplash.com/photo-1588943211346-0908a1fb0b01?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Charlie',
                'breed' => 'German Shepherd',
                'age' => 1,
                'gender' => 'Male',
                'size' => 'Large',
                'weight' => 30,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '18/01/2026',
                'microchipNumber' => '819625190369604',
                'vaccinated' => true,
                'goodWithKids' => 'Moderate',
                'goodWithDogs' => true,
                'goodWithCats' => 'Moderate',
                'description' => 'Smart, needs consistent training',
                'imageUrl' => 'https://images.unsplash.com/photo-1586671267731-da2cf3ceeb80?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Rocky',
                'breed' => 'Labrador Retriever',
                'age' => 6,
                'gender' => 'Male',
                'size' => 'Large',
                'weight' => 31,
                'healthStatus' => 'Minor issues',
                'adoptionStatus' => 'Reserved',
                'arrivalDate' => '10/01/2026',
                'microchipNumber' => '819625190369606',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => true,
                'description' => 'Minor joint stiffness, still very playful',
                'imageUrl' => 'https://images.unsplash.com/photo-1558788353-f76d92427f16?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Nala',
                'breed' => 'Siberian Husky',
                'age' => 2,
                'gender' => 'Female',
                'size' => 'Large',
                'weight' => 24,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '15/01/2026',
                'microchipNumber' => '819625190369608',
                'vaccinated' => true,
                'goodWithKids' => 'Moderate',
                'goodWithDogs' => true,
                'goodWithCats' => false,
                'description' => 'Rescued, very energetic and vocal',
                'imageUrl' => 'https://thumbs.dreamstime.com/b/siberian-husky-running-across-snowy-ground-distant-winter-trees-background-ai-generated-siberian-husky-running-snowy-410088406.jpg',
            ],
            [
                'name' => 'Thor',
                'breed' => 'Boxer',
                'age' => 4,
                'gender' => 'Male',
                'size' => 'XL',
                'weight' => 34,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Adopted',
                'arrivalDate' => '29/12/2025',
                'microchipNumber' => '819625190369610',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => true,
                'goodWithCats' => 'Moderate',
                'description' => 'Strong, loyal, loves play',
                'imageUrl' => 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Titan',
                'breed' => 'Great Dane',
                'age' => 3,
                'gender' => 'Male',
                'size' => 'XL',
                'weight' => 68,
                'healthStatus' => 'Healthy',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '03/02/2026',
                'microchipNumber' => '819625190369614',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => 'Moderate',
                'goodWithCats' => 'Moderate',
                'description' => 'Gentle giant, surprisingly calm indoors',
                'imageUrl' => 'https://pet-health-content-media.chewy.com/wp-content/uploads/2025/03/21152414/black-and-white-great-dane-scaled.jpg',
            ],
            [
                'name' => 'Athena',
                'breed' => 'Mastiff',
                'age' => 5,
                'gender' => 'Female',
                'size' => 'XL',
                'weight' => 72,
                'healthStatus' => 'Minor issues',
                'adoptionStatus' => 'Available',
                'arrivalDate' => '20/01/2026',
                'microchipNumber' => '819625190369615',
                'vaccinated' => true,
                'goodWithKids' => true,
                'goodWithDogs' => 'Moderate',
                'goodWithCats' => false,
                'description' => 'Protective but very sweet with family',
                'imageUrl' => 'https://www.thesprucepets.com/thmb/QcznubwjS6H-TnslOqMgkcCm9BQ=/2122x0/filters:no_upscale():strip_icc()/GettyImages-1290848911-5e28eb078af64588bc6237f19a88f14a.jpg',
            ],
        ];

        foreach ($dogsData as $data) {
            $dog = new Dogs();

            $dog->setName($data['name']);
            $dog->setBreed($data['breed']);
            $dog->setAge($data['age']);
            $dog->setGender($data['gender']);
            $dog->setSize($data['size']);
            $dog->setWeight($data['weight']);
            $dog->setHealthStatus($data['healthStatus']);
            $dog->setAdoptionStatus($data['adoptionStatus']);

            $arrival = DateTime::createFromFormat('d/m/Y', $data['arrivalDate']);
            $dog->setArrivalDate($arrival ?: new DateTime());

            $dog->setMicrochipNumber($data['microchipNumber']);
            $dog->setVaccinated($data['vaccinated']);
            $dog->setFriendlyWithKids($data['goodWithKids']);
            $dog->setFriendlyWithDogs($data['goodWithDogs']);
            $dog->setFriendlyWithCats($data['goodWithCats']);
            $dog->setDescription($data['description']);

            // Convert remote image to base64 data URL
            $base64 = $this->getImageAsBase64($data['imageUrl']);
            $dog->setImage($base64);

            $manager->persist($dog);
        }

        $manager->flush();
    }

    /**
     * Downloads image and returns data URL (base64 with mime type)
     * Returns small transparent GIF fallback if download fails
     */
    private function getImageAsBase64(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout'       => 20,
                'ignore_errors' => true,
                'header'        => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                                   "Accept: image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8\r\n" .
                                   "Referer: https://www.google.com/\r\n",
            ],
        ]);

        $imageData = @file_get_contents($url, false, $context);

        if ($imageData === false || strlen($imageData) < 300) {
            // Fallback: 1×1 transparent GIF
            return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        }

        // Detect mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $imageData) ?: 'image/jpeg';
        finfo_close($finfo);

        return 'data:' . $mime . ';base64,' . base64_encode($imageData);
    }
}