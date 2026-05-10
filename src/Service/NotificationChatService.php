<?php

namespace App\Service;

use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NotificationChatService
{
    private string $threadsDirectory;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $projectDir = rtrim((string) $parameterBag->get('kernel.project_dir'), '/');
        $this->threadsDirectory = $projectDir . '/var/notification_chat_threads';
    }

    public function getMessages(int $notificationId): array
    {
        $thread = $this->getConversation($notificationId);

        return $thread['messages'];
    }

    public function getConversation(int $notificationId): array
    {
        $path = $this->getThreadPath($notificationId);
        if (!is_file($path)) {
            return $this->createDefaultThread();
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return $this->createDefaultThread();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->createDefaultThread();
        }

        return $this->normalizeThread($decoded);
    }

    public function registerParticipantEntry(
        int $notificationId,
        int $userId,
        string $userName,
        string $userRole,
        string $roleType
    ): array {
        if ($userId <= 0) {
            return [
                'allowed' => false,
                'reason' => 'Unable to identify your account. Please sign in again.',
                'both_entered' => false,
                'thread' => $this->getConversation($notificationId),
            ];
        }

        if (!in_array($roleType, ['vet', 'agent'], true)) {
            return [
                'allowed' => false,
                'reason' => 'Only veterinarians and municipal agents can join this chat.',
                'both_entered' => false,
                'thread' => $this->getConversation($notificationId),
            ];
        }

        return $this->mutateThread($notificationId, function (array $thread) use ($userId, $userName, $userRole, $roleType): array {
            $slot = $thread['participants'][$roleType] ?? null;
            $slotUserId = is_array($slot) ? (int) ($slot['user_id'] ?? 0) : 0;

            if ($slotUserId > 0 && $slotUserId !== $userId) {
                $ownerLabel = $roleType === 'vet' ? 'veterinarian' : 'municipal agent';

                return [
                    'thread' => $thread,
                    'result' => [
                        'allowed' => false,
                        'reason' => sprintf('This notification already has a %s assigned to the conversation.', $ownerLabel),
                        'both_entered' => $this->hasBothRolesEntered($thread),
                    ],
                ];
            }

            if ($slotUserId === 0) {
                $thread['participants'][$roleType] = [
                    'user_id' => $userId,
                    'name' => trim($userName) !== '' ? trim($userName) : 'Unknown user',
                    'role' => trim($userRole) !== '' ? trim($userRole) : 'ROLE_USER',
                    'assigned_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                ];
            } else {
                $thread['participants'][$roleType]['name'] = trim($userName) !== '' ? trim($userName) : ($thread['participants'][$roleType]['name'] ?? 'Unknown user');
                $thread['participants'][$roleType]['role'] = trim($userRole) !== '' ? trim($userRole) : ($thread['participants'][$roleType]['role'] ?? 'ROLE_USER');
            }

            $thread['entries'][$roleType . '_entered'] = true;
            $bothEntered = $this->hasBothRolesEntered($thread);

            return [
                'thread' => $thread,
                'result' => [
                    'allowed' => true,
                    'reason' => null,
                    'both_entered' => $bothEntered,
                ],
            ];
        });
    }

    public function addMessage(
        int $notificationId,
        int $userId,
        string $roleType,
        string $senderName,
        string $senderRole,
        string $content
    ): bool {
        $cleanContent = trim($content);
        if ($cleanContent === '' || $userId <= 0 || !in_array($roleType, ['vet', 'agent'], true)) {
            return false;
        }

        $result = $this->mutateThread($notificationId, static function (array $thread) use ($userId, $roleType, $senderName, $senderRole, $cleanContent): array {
            $slot = $thread['participants'][$roleType] ?? null;
            $slotUserId = is_array($slot) ? (int) ($slot['user_id'] ?? 0) : 0;

            if ($slotUserId <= 0 || $slotUserId !== $userId) {
                return [
                    'thread' => $thread,
                    'result' => false,
                ];
            }

            $thread['messages'][] = [
                'sender_name' => trim($senderName) !== '' ? trim($senderName) : 'Unknown user',
                'sender_role' => trim($senderRole) !== '' ? trim($senderRole) : 'ROLE_USER',
                'sender_user_id' => $userId,
                'role_type' => $roleType,
                'content' => $cleanContent,
                'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];

            return [
                'thread' => $thread,
                'result' => true,
            ];
        });

        return (bool) $result;
    }

    private function mutateThread(int $notificationId, callable $mutator)
    {
        $this->ensureThreadsDirectory();
        $path = $this->getThreadPath($notificationId);

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open chat thread file: %s', $path));
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException(sprintf('Unable to lock chat thread file: %s', $path));
        }

        $raw = stream_get_contents($handle);
        $decoded = json_decode($raw !== false ? $raw : '', true);
        $thread = is_array($decoded) ? $this->normalizeThread($decoded) : $this->createDefaultThread();

        $mutation = $mutator($thread);
        $updatedThread = $thread;
        $result = null;
        if (is_array($mutation) && isset($mutation['thread'])) {
            $updatedThread = $this->normalizeThread((array) $mutation['thread']);
            $result = $mutation['result'] ?? null;
        } elseif (is_array($mutation)) {
            $updatedThread = $this->normalizeThread($mutation);
        }

        $payload = json_encode($updatedThread, JSON_PRETTY_PRINT);
        if ($payload === false) {
            $payload = json_encode($this->createDefaultThread(), JSON_PRETTY_PRINT) ?: '{}';
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $payload);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $result;
    }

    private function ensureThreadsDirectory(): void
    {
        if (!is_dir($this->threadsDirectory) && !mkdir($concurrentDirectory = $this->threadsDirectory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Unable to create chat thread directory: %s', $this->threadsDirectory));
        }
    }

    private function getThreadPath(int $notificationId): string
    {
        $safeId = max(0, $notificationId);

        return $this->threadsDirectory . '/notification_' . $safeId . '.json';
    }

    private function createDefaultThread(): array
    {
        return [
            'participants' => [
                'vet' => null,
                'agent' => null,
            ],
            'entries' => [
                'vet_entered' => false,
                'agent_entered' => false,
            ],
            'messages' => [],
        ];
    }

    private function normalizeThread(array $decoded): array
    {
        // Backward compatibility: old files only stored a flat messages array.
        if (array_is_list($decoded)) {
            $decoded = [
                'participants' => [
                    'vet' => null,
                    'agent' => null,
                ],
                'entries' => [
                    'vet_entered' => false,
                    'agent_entered' => false,
                ],
                'messages' => $decoded,
            ];
        }

        $thread = $this->createDefaultThread();

        if (isset($decoded['participants']) && is_array($decoded['participants'])) {
            foreach (['vet', 'agent'] as $roleType) {
                $slot = $decoded['participants'][$roleType] ?? null;
                $thread['participants'][$roleType] = is_array($slot) ? [
                    'user_id' => (int) ($slot['user_id'] ?? 0),
                    'name' => (string) ($slot['name'] ?? ''),
                    'role' => (string) ($slot['role'] ?? ''),
                    'assigned_at' => (string) ($slot['assigned_at'] ?? ''),
                ] : null;
            }
        }

        if (isset($decoded['entries']) && is_array($decoded['entries'])) {
            $thread['entries']['vet_entered'] = (bool) ($decoded['entries']['vet_entered'] ?? false);
            $thread['entries']['agent_entered'] = (bool) ($decoded['entries']['agent_entered'] ?? false);
        }

        if (isset($decoded['messages']) && is_array($decoded['messages'])) {
            $thread['messages'] = array_values(array_filter($decoded['messages'], static fn ($row) => is_array($row)));
        }

        usort($thread['messages'], static fn (array $a, array $b) => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));

        return $thread;
    }

    private function hasBothRolesEntered(array $thread): bool
    {
        return !empty($thread['entries']['vet_entered']) && !empty($thread['entries']['agent_entered']);
    }
}
