<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sessionUser = $request->getSession()->get('user');
        $sessionRole = is_array($sessionUser) && isset($sessionUser['role']) ? $sessionUser['role'] : null;

        if ($sessionRole !== 'ROLE_ADMIN') {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('dashboard/index.html.twig', [
            'page_title' => 'Dashboard',
            'active' => 'dashboard',
        ]);
    }
}
