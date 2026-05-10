<?php

namespace App\Service;

use App\Entity\IpCamera;
use Psr\Log\LoggerInterface;

/**
 * DirectRtspService - Handles RTSP to MJPEG conversion for IP cameras
 * 
 * Best practices implemented:
 * - Comprehensive error handling with try-catch blocks
 * - Process monitoring and health checks
 * - Resource management (PID tracking, cleanup)
 * - Detailed logging for debugging
 * - Configuration via parameters
 * - Auto-restart capability on failure
 * - Frame freshness monitoring
 */
class DirectRtspService
{
    private string $streamDirectory;
    private LoggerInterface $logger;
    private array $mjpegProcesses = [];
    private array $frameProcesses = [];

    // Configuration - can be injected via parameters
    private const MJPEG_PORT_BASE = 8888;
    private const MAX_RESTART_ATTEMPTS = 3;
    private const FRAME_TIMEOUT_SECONDS = 10; // Frame must be updated within this time

    public function __construct(
        LoggerInterface $logger,
        string $streamsDirectory
    ) {
        $this->logger = $logger;
        $this->streamDirectory = $streamsDirectory;
        
        if (!is_dir($this->streamDirectory)) {
            mkdir($this->streamDirectory, 0755, true);
        }
    }

    /**
     * Start direct RTSP to MJPEG conversion for low-latency streaming
     * This provides real-time video without HLS segment buffering
     */
    public function startDirectRtspStream(IpCamera $camera): bool
    {
        $cameraId = $camera->getId();
        
        try {
            // Stop any existing stream first
            $this->stopDirectRtspStream($cameraId);

            $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Clean up old files before starting
            $this->cleanupStreamFiles($cameraId);

            // Validate camera configuration
            $rtspUrl = $this->buildRtspUrl($camera);
            if (!$rtspUrl) {
                $this->logger->error("Failed to build RTSP URL for camera {$cameraId}");
                return false;
            }

            $mjpegPort = self::MJPEG_PORT_BASE + $cameraId;

            // Check if port is available
            $this->releasePort($mjpegPort);

            // Build FFmpeg commands
            $mjpegCommand = $this->buildMjpegCommand($rtspUrl, $cameraId, $outputDir);
            $frameCommand = $this->buildFrameCommand($rtspUrl, $cameraId);

            $this->logger->info("Starting direct RTSP stream for camera {$cameraId}");
            $this->logger->info("RTSP URL: " . $this->maskCredentials($rtspUrl));

            // Start MJPEG stream
            exec($mjpegCommand);
            usleep(200000); // Wait for process to start

            // Start frame extractor
            exec($frameCommand);
            usleep(200000);

            // Verify processes started
            if (!$this->verifyStreamStarted($cameraId, $outputDir)) {
                $this->logStartupFailureDetails($outputDir);
                $this->logger->error("Failed to verify stream started for camera {$cameraId}");
                return false;
            }

            // Track processes
            $this->mjpegProcesses[$cameraId] = [
                'pid' => $this->readPidFile($outputDir . '/rtsp.pid'),
                'cameraId' => $cameraId,
                'outputDir' => $outputDir,
                'startedAt' => new \DateTime(),
                'restartAttempts' => 0
            ];

            $this->logger->info("Direct RTSP stream started successfully for camera {$cameraId}");
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to start RTSP stream for camera {$cameraId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Stop the direct RTSP stream with proper cleanup
     */
    public function stopDirectRtspStream(int $cameraId): bool
    {
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;

        try {
            // Kill FFmpeg processes by pattern
            exec("pkill -9 -f 'ffmpeg.*rtsp_stream_{$cameraId}' 2>/dev/null");
            exec("pkill -9 -f 'ffmpeg.*rtsp_frame_{$cameraId}' 2>/dev/null");
            exec("pkill -9 -f 'mjpeg_server.php.*{$cameraId}' 2>/dev/null");

            // Kill by PID files
            $pidFiles = [
                $outputDir . '/rtsp.pid',
                $outputDir . '/frame.pid'
            ];

            foreach ($pidFiles as $pidFile) {
                if (file_exists($pidFile)) {
                    $pid = trim(file_get_contents($pidFile));
                    if ($pid && $this->isProcessRunning($pid)) {
                        exec("kill -9 $pid 2>/dev/null");
                    }
                    @unlink($pidFile);
                }
            }

            // Clean up temp files
            @unlink("/tmp/rtsp_stream_{$cameraId}.mjpg");
            @unlink("/tmp/rtsp_frame_{$cameraId}.jpg");

            // Remove from tracking
            if (isset($this->mjpegProcesses[$cameraId])) {
                unset($this->mjpegProcesses[$cameraId]);
            }
            if (isset($this->frameProcesses[$cameraId])) {
                unset($this->frameProcesses[$cameraId]);
            }

            $this->logger->info("Stopped direct RTSP stream for camera {$cameraId}");
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Error stopping stream for camera {$cameraId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restart the stream with error handling
     */
    public function restartDirectRtspStream(IpCamera $camera): bool
    {
        $cameraId = $camera->getId();
        
        // Check restart attempts
        if (isset($this->mjpegProcesses[$cameraId]['restartAttempts'])) {
            if ($this->mjpegProcesses[$cameraId]['restartAttempts'] >= self::MAX_RESTART_ATTEMPTS) {
                $this->logger->error("Max restart attempts reached for camera {$cameraId}");
                return false;
            }
            $this->mjpegProcesses[$cameraId]['restartAttempts']++;
        }

        $this->logger->info("Restarting stream for camera {$cameraId}");
        
        $this->stopDirectRtspStream($cameraId);
        usleep(1000000); // Wait for cleanup
        
        return $this->startDirectRtspStream($camera);
    }

    /**
     * Health check - verifies stream is working properly
     */
    public function healthCheck(int $cameraId): array
    {
        $health = [
            'healthy' => false,
            'mjpegRunning' => false,
            'frameFresh' => false,
            'errors' => []
        ];

        try {
            // Check MJPEG process
            $mjpegPid = $this->readPidFile($this->streamDirectory . '/camera_' . $cameraId . '/rtsp.pid');
            if ($mjpegPid && $this->isProcessRunning($mjpegPid)) {
                $health['mjpegRunning'] = true;
            } else {
                $health['errors'][] = 'MJPEG process not running';
            }

            // Check frame freshness
            $frameFile = "/tmp/rtsp_frame_{$cameraId}.jpg";
            if (file_exists($frameFile)) {
                $lastModified = filemtime($frameFile);
                $age = time() - $lastModified;
                if ($age <= self::FRAME_TIMEOUT_SECONDS) {
                    $health['frameFresh'] = true;
                } else {
                    $health['errors'][] = "Frame is stale (age: {$age}s)";
                }
            } else {
                $health['errors'][] = 'Frame file not found';
            }

            // Overall health
            $health['healthy'] = $health['mjpegRunning'] && $health['frameFresh'];

        } catch (\Exception $e) {
            $health['errors'][] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Auto-heal - attempts to restart failed streams
     */
    public function autoHeal(IpCamera $camera): bool
    {
        $cameraId = $camera->getId();
        $health = $this->healthCheck($cameraId);

        if (!$health['healthy']) {
            $this->logger->warning("Auto-heal triggered for camera {$cameraId}: " . implode(', ', $health['errors']));
            return $this->restartDirectRtspStream($camera);
        }

        return true;
    }

    /**
     * Get the MJPEG stream URL for direct playback
     */
    public function getMjpegStreamUrl(int $cameraId): ?string
    {
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $pidFile = $outputDir . '/rtsp.pid';

        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                $port = self::MJPEG_PORT_BASE + $cameraId;
                return "http://127.0.0.1:{$port}/stream.mjpg";
            }
        }

        return null;
    }

    /**
     * Get direct file path for MJPEG stream
     */
    public function getMjpegFilePath(int $cameraId): string
    {
        return "/tmp/rtsp_stream_{$cameraId}.mjpg";
    }

    /**
     * Get frame file path
     */
    public function getFrameFilePath(int $cameraId): string
    {
        return "/tmp/rtsp_frame_{$cameraId}.jpg";
    }

    /**
     * Check if stream is running
     */
    public function isStreamRunning(int $cameraId): bool
    {
        // Check memory
        if (isset($this->mjpegProcesses[$cameraId])) {
            $pid = $this->mjpegProcesses[$cameraId]['pid'] ?? null;
            if ($pid && $this->isProcessRunning($pid)) {
                return true;
            }
            unset($this->mjpegProcesses[$cameraId]);
        }

        // Check file system
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $pidFile = $outputDir . '/rtsp.pid';

        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get stream status with detailed information
     */
    public function getStreamStatus(IpCamera $camera): array
    {
        $cameraId = $camera->getId();
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        
        $rtspUrl = $this->buildRtspUrl($camera);
        
        $status = [
            'running' => false,
            'mode' => 'direct_rtsp',
            'rtspUrl' => $this->maskCredentials($rtspUrl),
            'mjpegUrl' => null,
            'frameUrl' => null,
            'filePath' => null,
            'framePath' => null,
            'fileExists' => false,
            'frameExists' => false,
            'fileSize' => 0,
            'frameSize' => 0,
            'lastModified' => null,
            'frameLastModified' => null,
            'pid' => null,
            'uptime' => null,
            'health' => null,
            'error' => null
        ];

        try {
            // Check if MJPEG process is running
            $pidFile = $outputDir . '/rtsp.pid';
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                if ($pid && $this->isProcessRunning($pid)) {
                    $status['running'] = true;
                    $status['pid'] = $pid;

                    // Calculate uptime
                    if (isset($this->mjpegProcesses[$cameraId]['startedAt'])) {
                        $started = $this->mjpegProcesses[$cameraId]['startedAt'];
                        $status['uptime'] = $started->diff(new \DateTime())->format('%H:%I:%S');
                    }
                }
            }

            // Check MJPEG file
            $mjpegFile = $this->getMjpegFilePath($cameraId);
            if (file_exists($mjpegFile)) {
                $status['fileExists'] = true;
                $status['filePath'] = $mjpegFile;
                $status['fileSize'] = filesize($mjpegFile);
                $status['lastModified'] = date('Y-m-d H:i:s', filemtime($mjpegFile));
                $status['mjpegUrl'] = $this->getMjpegStreamUrl($cameraId);
            }

            // Check frame file
            $frameFile = $this->getFrameFilePath($cameraId);
            if (file_exists($frameFile)) {
                $status['frameExists'] = true;
                $status['framePath'] = $frameFile;
                $status['frameSize'] = filesize($frameFile);
                $status['frameLastModified'] = date('Y-m-d H:i:s', filemtime($frameFile));
                $status['frameUrl'] = '/admin/cameras/' . $cameraId . '/frame.jpg';
            }

            // Health check
            $status['health'] = $this->healthCheck($cameraId);

            if (!$status['running'] && !$status['fileExists']) {
                $status['error'] = 'Stream not running - no active processes or files';
            }

        } catch (\Exception $e) {
            $status['error'] = $e->getMessage();
            $this->logger->error("Error getting stream status for camera {$cameraId}: " . $e->getMessage());
        }

        return $status;
    }

    /**
     * Get all active streams
     */
    public function getAllStreamsStatus(): array
    {
        $streams = [];
        
        // Get all camera directories
        $dirs = glob($this->streamDirectory . '/camera_*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            if (preg_match('/camera_(\d+)$/', $dir, $matches)) {
                $cameraId = (int)$matches[1];
                $streams[$cameraId] = $this->isStreamRunning($cameraId);
            }
        }
        
        return $streams;
    }

    /**
     * Cleanup all streams (for maintenance)
     */
    public function cleanupAllStreams(): void
    {
        $streams = $this->getAllStreamsStatus();
        
        foreach ($streams as $cameraId => $isRunning) {
            if ($isRunning) {
                $this->stopDirectRtspStream($cameraId);
            }
        }
        
        // Clean up temp files
        array_map('unlink', glob("/tmp/rtsp_stream_*.mjpg"));
        array_map('unlink', glob("/tmp/rtsp_frame_*.jpg"));
        
        $this->logger->info("Cleaned up all streams");
    }

    // ==================== Private Helper Methods ====================

    /**
     * Build RTSP URL from camera entity
     */
    private function buildRtspUrl(IpCamera $camera): ?string
    {
        // Use custom RTSP URL if set
        if ($camera->getRtspUrl()) {
            return $camera->getRtspUrl();
        }

        // Build from IP and port
        $ip = $camera->getIpAddress();
        $port = $camera->getPort() ?: 554;
        
        if (!$ip) {
            return null;
        }

        $username = $camera->getUsername();
        $password = $camera->getPassword();
        
        if ($username && $password) {
            return "rtsp://{$username}:{$password}@{$ip}:{$port}/stream";
        }
        
        return "rtsp://{$ip}:{$port}/stream";
    }

    /**
     * Build MJPEG FFmpeg command with enhanced stability options
     */
    private function buildMjpegCommand(string $rtspUrl, int $cameraId, string $outputDir): string
    {
        $quotedRtspUrl = escapeshellarg($rtspUrl);
        $quotedMjpegOutput = escapeshellarg("/tmp/rtsp_stream_{$cameraId}.mjpg");
        $quotedLogPath = escapeshellarg($outputDir . '/rtsp_mjpeg.log');
        $quotedPidPath = escapeshellarg($outputDir . '/rtsp.pid');

        return sprintf(
            'nohup ffmpeg -y ' .
            // Keep options conservative for compatibility across FFmpeg builds.
            '-nostdin ' .
            '-rtsp_transport tcp ' .
            '-fflags nobuffer ' .
            '-flags low_delay ' .
            '-analyzeduration 1000000 ' .
            '-probesize 1000000 ' .
            '-i %s ' .
            '-c:v mjpeg ' .
            '-q:v 4 ' .
            '-f mjpeg ' .
            '-an ' .
            '%s ' .
            '> %s 2>&1 & echo $! > %s',
            $quotedRtspUrl,
            $quotedMjpegOutput,
            $quotedLogPath,
            $quotedPidPath
        );
    }

    /**
     * Build frame extractor FFmpeg command with enhanced stability
     */
    private function buildFrameCommand(string $rtspUrl, int $cameraId): string
    {
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $quotedRtspUrl = escapeshellarg($rtspUrl);
        $quotedFrameOutput = escapeshellarg("/tmp/rtsp_frame_{$cameraId}.jpg");
        $quotedLogPath = escapeshellarg($outputDir . '/frame.log');
        $quotedPidPath = escapeshellarg($outputDir . '/frame.pid');

        return sprintf(
            'nohup ffmpeg -y ' .
            '-nostdin ' .
            '-rtsp_transport tcp ' .
            '-fflags nobuffer ' .
            '-flags low_delay ' .
            '-analyzeduration 1000000 ' .
            '-probesize 1000000 ' .
            '-i %s ' .
            '-vf "fps=6" ' .
            '-q:v 4 ' .
            '-f image2 ' .
            '-update 1 ' .
            '%s ' .
            '> %s 2>&1 & echo $! > %s',
            $quotedRtspUrl,
            $quotedFrameOutput,
            $quotedLogPath,
            $quotedPidPath
        );
    }

    /**
     * Verify stream started successfully
     */
    private function verifyStreamStarted(int $cameraId, string $outputDir): bool
    {
        // Check PID file exists
        $pidFile = $outputDir . '/rtsp.pid';
        if (!file_exists($pidFile)) {
            return false;
        }

        // Give processes time to start
        usleep(700000);

        // Check if process is running
        $pid = trim(file_get_contents($pidFile));
        if ($pid && $this->isProcessRunning($pid)) {
            return true;
        }

        // Fallback: accept started state if frame is being generated.
        $frameFile = "/tmp/rtsp_frame_{$cameraId}.jpg";
        if (file_exists($frameFile)) {
            $mtime = @filemtime($frameFile);
            if ($mtime !== false && (time() - $mtime) <= self::FRAME_TIMEOUT_SECONDS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean up stream files
     */
    private function cleanupStreamFiles(int $cameraId): void
    {
        @unlink("/tmp/rtsp_stream_{$cameraId}.mjpg");
        @unlink("/tmp/rtsp_frame_{$cameraId}.jpg");
        
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        if (is_dir($outputDir)) {
            $files = glob($outputDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Release port if in use
     */
    private function releasePort(int $port): void
    {
        $checkPort = shell_exec("lsof -i:{$port} 2>/dev/null");
        if (!empty(trim($checkPort))) {
            $this->logger->warning("Port {$port} is in use, killing existing process");
            shell_exec("fuser -k {$port}/tcp 2>/dev/null");
            usleep(500000);
        }
    }

    /**
     * Read PID from file
     */
    private function readPidFile(?string $path): ?string
    {
        if (!$path || !file_exists($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $pid = trim($raw);
        return $pid !== '' ? $pid : null;
    }

    /**
     * Check if process is running
     */
    private function isProcessRunning(string $pid): bool
    {
        if (empty($pid)) {
            return false;
        }
        if (!ctype_digit((string) $pid)) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill((int) $pid, 0);
        }

        exec('kill -0 ' . escapeshellarg($pid) . ' 2>/dev/null', $output, $returnCode);
        return $returnCode === 0;
    }

    private function logStartupFailureDetails(string $outputDir): void
    {
        $files = [
            $outputDir . '/frame.log',
            $outputDir . '/rtsp_mjpeg.log',
        ];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $content = @file_get_contents($file);
            if ($content === false || $content === '') {
                continue;
            }
            $tail = substr($content, -800);
            $this->logger->error('RTSP startup log tail (' . basename($file) . '): ' . $tail);
        }
    }

    /**
     * Mask credentials in URL for logging
     */
    private function maskCredentials(string $url): string
    {
        return preg_replace('/(rtsp|http):\/\/([^:]+):([^@]+)@/', '$1://****:****@', $url);
    }
}
