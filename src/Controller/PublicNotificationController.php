<?php

namespace App\Controller;

use App\Service\NotificationChatService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * PublicNotificationController - Notification pages
 * Part of the ObservationStations module
 */
#[Route('/notifications')]
final class PublicNotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(Request $request, NotificationService $notificationService): Response
    {
        return $this->render('notifications.html.twig', [
            'notifications' => $notificationService->getNotifications(),
            'can_access_chat' => $this->canAccessChat($request),
        ]);
    }

    #[Route('/{id}/read', name: 'app_notifications_read', methods: ['POST'])]
    public function markAsRead(
        int $id,
        Request $request,
        NotificationService $notificationService
    ): Response {
        $token = $request->request->get('_token');
        
        if ($this->isCsrfTokenValid('mark_read_' . $id, $token)) {
            $notificationService->markAsRead($id);
        }

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/{id}/chat', name: 'app_notifications_chat', methods: ['GET', 'POST'])]
    public function chat(
        int $id,
        Request $request,
        NotificationService $notificationService,
        NotificationChatService $notificationChatService
    ): Response {
        if (!$this->canAccessChat($request)) {
            $this->addFlash('error', 'Only veterinarians and municipal agents can access notification chat.');
            return $this->redirectToRoute('app_notifications');
        }

        $notification = $notificationService->getNotificationById($id);
        if ($notification === null) {
            throw $this->createNotFoundException('Notification not found.');
        }

        $identity = $this->resolveChatIdentity($request);
        if ($identity['user_id'] <= 0) {
            $this->addFlash('error', 'Please sign in again before opening notification chat.');
            return $this->redirectToRoute('app_notifications');
        }

        if (!in_array($identity['role_type'], ['vet', 'agent'], true)) {
            $this->addFlash('error', 'Only veterinarians and municipal agents can access notification chat.');
            return $this->redirectToRoute('app_notifications');
        }

        $entry = $notificationChatService->registerParticipantEntry(
            $id,
            $identity['user_id'],
            $identity['display_name'],
            $identity['role'],
            $identity['role_type']
        );

        if (empty($entry['allowed'])) {
            $this->addFlash('error', (string) ($entry['reason'] ?? 'You are not allowed to access this conversation.'));
            return $this->redirectToRoute('app_notifications');
        }

        if (!empty($entry['both_entered'])) {
            $notificationService->markAsRead($id);
        } else {
            $notificationService->markAsUnread($id);
        }

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('chat_message_' . $id, $token)) {
                $this->addFlash('error', 'Invalid CSRF token for chat message.');
                return $this->redirectToRoute('app_notifications_chat', ['id' => $id]);
            }

            $message = trim((string) $request->request->get('message', ''));
            if ($message === '') {
                $this->addFlash('error', 'Message cannot be empty.');
                return $this->redirectToRoute('app_notifications_chat', ['id' => $id]);
            }

            $saved = $notificationChatService->addMessage(
                $id,
                $identity['user_id'],
                $identity['role_type'],
                $identity['display_name'],
                $identity['role'],
                $message
            );

            if (!$saved) {
                $this->addFlash('error', 'Only the assigned veterinarian and assigned municipal agent can send messages in this notification thread.');
            }

            return $this->redirectToRoute('app_notifications_chat', ['id' => $id]);
        }

        $conversation = $notificationChatService->getConversation($id);
        $participants = $conversation['participants'] ?? ['vet' => null, 'agent' => null];
        $bothEntered = !empty($conversation['entries']['vet_entered']) && !empty($conversation['entries']['agent_entered']);

        return $this->render('notifications/chat.html.twig', [
            'notification' => $notification,
            'messages' => $conversation['messages'] ?? [],
            'participants' => $participants,
            'both_entered' => $bothEntered,
            'current_user_name' => $identity['display_name'],
            'current_user_id' => $identity['user_id'],
            'current_role' => $identity['role'],
            'can_access_chat' => true,
            'voip_room_url' => sprintf('https://meet.jit.si/pawtech-notification-%d', $id),
        ]);
    }

    #[Route('/{id}/chat/messages', name: 'app_notifications_chat_messages', methods: ['GET'])]
    public function chatMessages(
        int $id,
        Request $request,
        NotificationService $notificationService,
        NotificationChatService $notificationChatService
    ): JsonResponse {
        if (!$this->canAccessChat($request)) {
            return $this->json(['ok' => false, 'message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $notification = $notificationService->getNotificationById($id);
        if ($notification === null) {
            return $this->json(['ok' => false, 'message' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $identity = $this->resolveChatIdentity($request);
        if ($identity['user_id'] <= 0 || !in_array($identity['role_type'], ['vet', 'agent'], true)) {
            return $this->json(['ok' => false, 'message' => 'Invalid user identity'], Response::HTTP_FORBIDDEN);
        }

        $entry = $notificationChatService->registerParticipantEntry(
            $id,
            $identity['user_id'],
            $identity['display_name'],
            $identity['role'],
            $identity['role_type']
        );

        if (empty($entry['allowed'])) {
            return $this->json(['ok' => false, 'message' => (string) ($entry['reason'] ?? 'Access denied')], Response::HTTP_FORBIDDEN);
        }

        if (!empty($entry['both_entered'])) {
            $notificationService->markAsRead($id);
        } else {
            $notificationService->markAsUnread($id);
        }

        $conversation = $notificationChatService->getConversation($id);
        $participants = $conversation['participants'] ?? ['vet' => null, 'agent' => null];
        $bothEntered = !empty($conversation['entries']['vet_entered']) && !empty($conversation['entries']['agent_entered']);

        return $this->json([
            'ok' => true,
            'messages' => $conversation['messages'] ?? [],
            'participants' => $participants,
            'both_entered' => $bothEntered,
            'current_user_id' => $identity['user_id'],
            'current_role' => $identity['role'],
            'notification_status' => $notification->getStatut(),
        ]);
    }

    private function canAccessChat(Request $request): bool
    {
        $session = $request->getSession();
        $sessionUser = $session->get('user');
        if (!is_array($sessionUser)) {
            return false;
        }

        $role = strtoupper((string) ($sessionUser['role'] ?? ''));
        if (
            $role === 'VETERINAIRE' ||
            $role === 'ROLE_VETERINAIRE' ||
            $role === 'AGENT_MUNICIPALE' ||
            $role === 'ROLE_AGENT_MUNICIPALE' ||
            str_contains($role, 'VETERINAIRE') ||
            str_contains($role, 'AGENT_MUNICIPALE')
        ) {
            return true;
        }

        $roles = $sessionUser['roles'] ?? [];
        if (is_string($roles)) {
            $roles = [$roles];
        }

        foreach ((array) $roles as $candidate) {
            $candidateRole = strtoupper((string) $candidate);
            if ($candidateRole === 'ROLE_VETERINAIRE' || $candidateRole === 'ROLE_AGENT_MUNICIPALE') {
                return true;
            }
        }

        return false;
    }

    private function resolveChatIdentity(Request $request): array
    {
        $sessionUser = $request->getSession()->get('user');
        if (!is_array($sessionUser)) {
            return [
                'user_id' => 0,
                'display_name' => '',
                'role' => 'ROLE_USER',
                'role_type' => 'unknown',
            ];
        }

        $userId = (int) ($sessionUser['id'] ?? 0);
        $firstName = trim((string) ($sessionUser['prenom'] ?? ''));
        $lastName = trim((string) ($sessionUser['nom'] ?? ''));
        $displayName = trim($firstName . ' ' . $lastName);
        if ($displayName === '') {
            $displayName = (string) ($sessionUser['email'] ?? 'Unknown user');
        }

        $rawRole = strtoupper((string) ($sessionUser['role'] ?? ''));
        $mappedRole = match ($rawRole) {
            'VETERINAIRE' => 'ROLE_VETERINAIRE',
            'AGENT_MUNICIPALE' => 'ROLE_AGENT_MUNICIPALE',
            default => ($rawRole !== '' ? $rawRole : 'ROLE_USER'),
        };

        $roleType = match ($mappedRole) {
            'ROLE_VETERINAIRE' => 'vet',
            'ROLE_AGENT_MUNICIPALE' => 'agent',
            default => 'unknown',
        };

        return [
            'user_id' => $userId,
            'display_name' => $displayName,
            'role' => $mappedRole,
            'role_type' => $roleType,
        ];
    }
}
