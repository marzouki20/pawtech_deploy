<?php

namespace App\Service;

use App\Repository\IpCameraRepository;
use Psr\Log\LoggerInterface;

/**
 * Service to manage automatic stream startup on application boot
 * and to recover streams that may have stopped after a restart
 */
class StreamAutoStartService
{
    private StreamTranscoderService $transcoderService;
    private IpCameraRepository $cameraRepository;
    private LoggerInterface $logger;

    public function __construct(
        StreamTranscoderService $transcoderService,
        IpCameraRepository $cameraRepository,
        LoggerInterface $logger
    ) {
        $this->transcoderService = $transcoderService;
        $this->cameraRepository = $cameraRepository;
        $this->logger = $logger;
    }

    /**
     * Start all cameras that have 'active' status in the database
     * This should be called on application boot to recover streams after restart
     */
    public function startAllActiveStreams(): array
    {
        $results = [
            'started' => [],
            'already_running' => [],
            'failed' => []
        ];

        // Get all cameras with 'active' status
        $cameras = $this->cameraRepository->findBy(['status' => 'active']);

        $this->logger->info('Starting auto-start for ' . count($cameras) . ' active cameras');

        foreach ($cameras as $camera) {
            $cameraId = $camera->getId();
            
            // Check if stream is already running (using our improved isTranscoding method)
            if ($this->transcoderService->isTranscoding($cameraId)) {
                $this->logger->info("Stream already running for camera {$cameraId}");
                $results['already_running'][] = $cameraId;
                continue;
            }

            // Try to start the stream
            try {
                $this->logger->info("Attempting to start stream for camera {$cameraId}");
                
                // Get the RTSP URL
                $rtspUrl = $camera->getRtspUrl() ?: $camera->getFullStreamUrl();
                
                if (!$rtspUrl) {
                    $this->logger->warning("No RTSP URL for camera {$cameraId}, skipping");
                    $results['failed'][] = [
                        'cameraId' => $cameraId,
                        'reason' => 'No RTSP URL configured'
                    ];
                    continue;
                }

                // Start transcoding
                $success = $this->transcoderService->startTranscoding($camera);
                
                if ($success) {
                    $this->logger->info("Successfully started stream for camera {$cameraId}");
                    $results['started'][] = $cameraId;
                } else {
                    $this->logger->error("Failed to start stream for camera {$cameraId}");
                    $results['failed'][] = [
                        'cameraId' => $cameraId,
                        'reason' => 'Transcoder returned failure'
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->error("Exception starting stream for camera {$cameraId}: " . $e->getMessage());
                $results['failed'][] = [
                    'cameraId' => $cameraId,
                    'reason' => $e->getMessage()
                ];
            }
        }

        $this->logger->info('Auto-start complete: ' . count($results['started']) . ' started, ' 
            . count($results['already_running']) . ' already running, ' 
            . count($results['failed']) . ' failed');

        return $results;
    }

    /**
     * Check and restart any streams that may have stopped
     * This can be called periodically via cron or a scheduler
     */
    public function checkAndRestartStoppedStreams(): array
    {
        $results = [
            'restarted' => [],
            'still_running' => [],
            'not_active' => []
        ];

        // Get all cameras with 'active' status that should be streaming
        $cameras = $this->cameraRepository->findBy(['status' => 'active']);

        foreach ($cameras as $camera) {
            $cameraId = $camera->getId();
            
            // Check if stream is actually running
            if ($this->transcoderService->isTranscoding($cameraId)) {
                $results['still_running'][] = $cameraId;
                continue;
            }

            // Stream is not running but camera is marked as active - restart it
            $this->logger->info("Stream not running for active camera {$cameraId}, attempting restart");
            
            try {
                $success = $this->transcoderService->startTranscoding($camera);
                
                if ($success) {
                    $results['restarted'][] = $cameraId;
                }
            } catch (\Exception $e) {
                $this->logger->error("Failed to restart stream for camera {$cameraId}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Get the current status of all cameras
     */
    public function getStreamStatus(): array
    {
        $status = [];
        $cameras = $this->cameraRepository->findAll();

        foreach ($cameras as $camera) {
            $cameraId = $camera->getId();
            $isTranscoding = $this->transcoderService->isTranscoding($cameraId);
            
            $status[] = [
                'id' => $cameraId,
                'name' => $camera->getName(),
                'dbStatus' => $camera->getStatus(),
                'transcoding' => $isTranscoding,
                'hlsUrl' => $camera->getHlsStreamUrl(),
                'rtspUrl' => $camera->getRtspUrl() ?: $camera->getFullStreamUrl()
            ];
        }

        return $status;
    }
}
