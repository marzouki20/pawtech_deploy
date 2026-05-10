<?php

namespace App\Controller;

use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class RecommendationController extends AbstractController
{
    private RecommendationService $recommendationService;
    private EntityManagerInterface $entityManager;

    public function __construct(RecommendationService $recommendationService, EntityManagerInterface $entityManager)
    {
        $this->recommendationService = $recommendationService;
        $this->entityManager = $entityManager;
    }

    #[Route('/recommendations/product/{id}', name: 'app_api_product_recommendations', methods: ['GET'])]
    public function getProductRecommendations(int $id): JsonResponse
    {
        $recommendations = $this->recommendationService->getRecommendations($id, 4);
        
        $data = [];
        foreach ($recommendations as $product) {
            $data[] = [
                'id' => $product->getId(),
                'nom' => $product->getNom(),
                'prix' => $product->getPrix(),
                'image' => $product->getImage(),
                'quantite' => $product->getQuantite(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/recommendations/user/{userId}', name: 'app_api_user_recommendations', methods: ['GET'])]
    public function getUserRecommendations(int $userId): JsonResponse
    {
        $recommendations = $this->recommendationService->getCollaborativeFilteringRecommendations($userId, 4);
        
        $data = [];
        foreach ($recommendations as $product) {
            $data[] = [
                'id' => $product->getId(),
                'nom' => $product->getNom(),
                'prix' => $product->getPrix(),
                'image' => $product->getImage(),
                'quantite' => $product->getQuantite(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/recommendations/similar/{productId}', name: 'app_api_similar_products', methods: ['GET'])]
    public function getSimilarProducts(int $productId): JsonResponse
    {
        $product = $this->entityManager->getRepository(\App\Entity\Produit::class)->find($productId);
        
        if (!$product) {
            return $this->json(['error' => 'Product not found'], 404);
        }

        $similar = $this->recommendationService->getSimilarProducts($product, 4);
        
        $data = [];
        foreach ($similar as $p) {
            $data[] = [
                'id' => $p->getId(),
                'nom' => $p->getNom(),
                'prix' => $p->getPrix(),
                'image' => $p->getImage(),
                'quantite' => $p->getQuantite(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/recommendations/top-rated', name: 'app_api_top_rated', methods: ['GET'])]
    public function getTopRatedProducts(Request $request): JsonResponse
    {
        $limit = $request->query->get('limit', 10);
        $products = $this->recommendationService->getTopRatedProducts((int) $limit);
        
        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => $product->getId(),
                'nom' => $product->getNom(),
                'prix' => $product->getPrix(),
                'image' => $product->getImage(),
                'quantite' => $product->getQuantite(),
                'rating' => $product->getAverageRating(),
                'rating_count' => $product->getRatingCount(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/recommendations/popular', name: 'app_api_popular_recommendations', methods: ['GET'])]
    public function getPopularRecommendations(Request $request): JsonResponse
    {
        $limit = $request->query->get('limit', 6);
        $products = $this->recommendationService->getPurchaseBasedRecommendations((int) $limit);
        
        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => $product->getId(),
                'nom' => $product->getNom(),
                'prix' => $product->getPrix(),
                'image' => $product->getImage(),
                'quantite' => $product->getQuantite(),
                'rating' => $product->getAverageRating(),
                'rating_count' => $product->getRatingCount(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/recommendations/knn', name: 'app_api_knn_recommendations', methods: ['GET'])]
    public function getKnnRecommendations(Request $request): JsonResponse
    {
        $limit = $request->query->get('limit', 6);
        $products = $this->recommendationService->getAllProductsWithKnnScore((int) $limit);
        
        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'id' => $product->getId(),
                'nom' => $product->getNom(),
                'prix' => $product->getPrix(),
                'image' => $product->getImage(),
                'quantite' => $product->getQuantite(),
                'rating' => $product->getAverageRating(),
                'rating_count' => $product->getRatingCount(),
            ];
        }

        return $this->json($data);
    }
}
