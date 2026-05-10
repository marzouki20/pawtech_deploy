<?php

namespace App\Controller;

use App\Service\NotificationService;
use App\Service\NotificationChatService;
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
        $token = (string) $request->request->get('_token', '');

        if ($this->isCsrfTokenValid('mark_read_' . $id, $token)) {
            $notificationService->markAsRead($id);
        }

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/{id}/unread', name: 'app_notifications_unread', methods: ['POST'])]
    public function markAsUnread(
        int $id,
        Request $request,
        NotificationService $notificationService
    ): Response {
        $token = (string) $request->request->get('_token', '');

        if ($this->isCsrfTokenValid('mark_unread_' . $id, $token)) {
            $notificationService->markAsUnread($id);
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

            return $this->redirectToRoute('app_signin');
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
        if (($entry['allowed'] ?? false) !== true) {
            $this->addFlash('error', (string) ($entry['reason'] ?? 'You are not allowed to access this conversation.'));

            return $this->redirectToRoute('app_notifications');
        }

        // Notification is marked read only after one vet and one municipal agent have entered.
        if (($entry['both_entered'] ?? false) === true) {
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
        $currentUserName = $identity['display_name'];
        $currentRole = $identity['role'];

        return $this->render('notifications/chat.html.twig', [
            'notification' => $notification,
            'messages' => $conversation['messages'],
            'participants' => $conversation['participants'],
            'both_entered' => !empty($conversation['entries']['vet_entered']) && !empty($conversation['entries']['agent_entered']),
            'current_user_id' => $identity['user_id'],
            'current_user_name' => $currentUserName,
            'current_role' => $currentRole,
            'current_role_type' => $identity['role_type'],
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
            return $this->json(['ok' => false, 'message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $notification = $notificationService->getNotificationById($id);
        if ($notification === null) {
            return $this->json(['ok' => false, 'message' => 'Notification not found'], Response::HTTP_NOT_FOUND);
        }

        $identity = $this->resolveChatIdentity($request);
        if ($identity['user_id'] <= 0 || !in_array($identity['role_type'], ['vet', 'agent'], true)) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $entry = $notificationChatService->registerParticipantEntry(
            $id,
            $identity['user_id'],
            $identity['display_name'],
            $identity['role'],
            $identity['role_type']
        );
        if (($entry['allowed'] ?? false) !== true) {
            return $this->json([
                'ok' => false,
                'message' => (string) ($entry['reason'] ?? 'Not allowed'),
            ], Response::HTTP_FORBIDDEN);
        }

        if (($entry['both_entered'] ?? false) === true) {
            $notificationService->markAsRead($id);
        } else {
            $notificationService->markAsUnread($id);
        }

        $conversation = $notificationChatService->getConversation($id);

        return $this->json([
            'ok' => true,
            'messages' => $conversation['messages'],
            'participants' => $conversation['participants'],
            'both_entered' => !empty($conversation['entries']['vet_entered']) && !empty($conversation['entries']['agent_entered']),
            'current_user_id' => $identity['user_id'],
            'current_user_name' => $identity['display_name'],
            'current_role' => $identity['role'],
            'current_role_type' => $identity['role_type'],
            'notification_status' => $notification->getStatut(),
        ]);
    }

    private function canAccessChat(Request $request): bool
    {
        foreach ($this->collectCurrentRoles($request) as $role) {
            if ($this->isVeterinarianRole($role) || $this->isMunicipalAgentRole($role)) {
                return true;
            }
        }

        return false;
    }

    private function collectCurrentRoles(Request $request): array
    {
        $roles = [];

        if ($this->getUser() !== null) {
            foreach ((array) $this->getUser()->getRoles() as $role) {
                $roles[] = $this->normalizeRole((string) $role);
            }
        }

        if ($request->hasSession()) {
            $sessionUser = $request->getSession()->get('user');
            if (is_array($sessionUser)) {
                $rawRole = $sessionUser['role'] ?? '';

                if (is_array($rawRole)) {
                    foreach ($rawRole as $role) {
                        $roles[] = $this->normalizeRole((string) $role);
                    }
                } else {
                    $roles[] = $this->normalizeRole((string) $rawRole);
                }
            }
        }

        return array_values(array_unique(array_filter($roles)));
    }

    private function isVeterinarianRole(string $role): bool
    {
        return str_contains($role, 'VETERINAIRE')
            || str_contains($role, 'VETERINARY')
            || preg_match('/\bVET\b/', $role) === 1;
    }

    private function isMunicipalAgentRole(string $role): bool
    {
        if (str_contains($role, 'AGENT_MUNICIP')) {
            return true;
        }

        return str_contains($role, 'AGENT') && str_contains($role, 'MUNICIP');
    }

    private function resolveChatIdentity(Request $request): array
    {
        $sessionUser = $request->hasSession() ? $request->getSession()->get('user') : null;
        $sessionUser = is_array($sessionUser) ? $sessionUser : [];
        $userId = isset($sessionUser['id']) ? (int) $sessionUser['id'] : 0;

        $displayName = trim((string) ($sessionUser['prenom'] ?? '') . ' ' . (string) ($sessionUser['nom'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($sessionUser['email'] ?? '');
        }
        if ($displayName === '') {
            $displayName = 'User';
        }

        $role = $this->normalizeRole((string) ($sessionUser['role'] ?? ''));
        if ($role === '' && $this->getUser() !== null) {
            $roles = $this->getUser()->getRoles();
            $role = isset($roles[0]) ? $this->normalizeRole((string) $roles[0]) : '';
        }
        if ($role === '') {
            $role = 'ROLE_USER';
        }

        $roleType = null;
        if ($this->isVeterinarianRole($role)) {
            $roleType = 'vet';
        } elseif ($this->isMunicipalAgentRole($role)) {
            $roleType = 'agent';
        }

        return [
            'user_id' => $userId,
            'display_name' => $displayName,
            'role' => $role,
            'role_type' => $roleType,
        ];
    }

    private function normalizeRole(string $role): string
    {
        $raw = trim($role);
        if ($raw === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        $clean = strtoupper($ascii !== false ? $ascii : $raw);
        $clean = preg_replace('/[^A-Z0-9]+/', '_', $clean) ?? '';
        $clean = trim($clean, '_');
        if ($clean === '') {
            return '';
        }

        return str_starts_with($clean, 'ROLE_') ? $clean : 'ROLE_' . $clean;
    }
}
