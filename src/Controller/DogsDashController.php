<?php

namespace App\Controller;

use App\Entity\Dogs;
use App\Repository\DogsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DogsDashController extends AbstractController
{
    #[Route('/dashboard/dogs-dash', name: 'app_dogs_dash', methods: ['GET'])]
    public function index(DogsRepository $dogsRepository): Response
    {
        $dogs = $dogsRepository->filterDogs('Streetdog', null, null);

        $markers = [];
        foreach ($dogs as $dog) {
            $parsed = $this->extractLatestLocation($dog);
            if ($parsed === null) {
                continue;
            }

            $markers[] = [
                'id' => $dog->getId(),
                'name' => $dog->getName() ?? 'Unknown',
                'lat' => $parsed['lat'],
                'lng' => $parsed['lng'],
            ];
        }

        return $this->render('dogs_dash/index.html.twig', [
            'active' => 'dogs_dash',
            'page_title' => 'Dogs Dashboard',
            'dogs' => $dogs,
            'markers' => $markers,
        ]);
    }

    private function extractLatestLocation(Dogs $dog): ?array
    {
        $ziptag = $dog->getZiptag();
        if ($ziptag === null) {
            return null;
        }

        $latestInfo = null;
        foreach ($ziptag->getInfos() as $info) {
            $location = trim((string) $info->getLocation());
            if ($location === '') {
                continue;
            }

            $createdAt = $info->getCreatedAt();
            if ($createdAt === null) {
                continue;
            }

            if ($latestInfo === null || $createdAt > $latestInfo->getCreatedAt()) {
                $latestInfo = $info;
            }
        }

        if ($latestInfo === null) {
            return null;
        }

        $location = $latestInfo->getLocation() ?? '';
        $parts = array_map('trim', explode(',', $location));
        if (count($parts) !== 2) {
            return null;
        }

        if (!is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }

        return [
            'lat' => (float) $parts[0],
            'lng' => (float) $parts[1],
        ];
    }
}

