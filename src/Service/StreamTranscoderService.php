<?php

namespace App\Service;

use App\Entity\IpCamera;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class StreamTranscoderService
{
    private string $streamDirectory;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private array $activeProcesses = [];
    private array $vlcProcesses = [];  // Track VLC processes separately

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        string $streamsDirectory
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->streamDirectory = $streamsDirectory;
        
        // Create streams directory if it doesn't exist
        if (!is_dir($this->streamDirectory)) {
            mkdir($this->streamDirectory, 0755, true);
        }

        if ($this->isWindows()) {
            $runtimeDir = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/pawtech_streams';
            if (!is_dir($runtimeDir)) {
                @mkdir($runtimeDir, 0755, true);
            }
        }
    }

    private function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    private function getCameraOutputDir(int $cameraId): string
    {
        $baseDir = $this->streamDirectory;
        if ($this->isWindows()) {
            $baseDir = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/pawtech_streams';
        }

        return $baseDir . '/camera_' . $cameraId;
    }

    private function getFrameFile(int $cameraId): string
    {
        return $this->getCameraOutputDir($cameraId) . '/frame.jpg';
    }

    /**
     * Start FFmpeg transcoding process for a camera
     * Optimized for minimum latency RTSP to HLS streaming
     */
    public function startTranscoding(IpCamera $camera): bool
    {
        $cameraId = (int) $camera->getId();
        $frameFile = $this->getFrameFile($cameraId);
        $outputDir = $this->getCameraOutputDir($cameraId);

        if ($this->isTranscoding($cameraId)) {
            $this->logger->info("Transcoding already running for camera {$cameraId}, skipping restart");
            return true;
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $lockFile = $outputDir . '/start.lock';
        $lockHandle = @fopen($lockFile, 'c+');
        if ($lockHandle === false) {
            $this->logger->warning("Unable to open start lock for camera {$cameraId}, continuing without lock");
        } elseif (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $this->logger->info("Start already in progress for camera {$cameraId}, skipping duplicate request");
            @fclose($lockHandle);
            return true;
        }

        try {
            if ($this->isWindows()) {
                // On Windows dev environments, detached FFmpeg process management can
                // block Symfony local server shutdown. Use direct snapshot mode.
                @file_put_contents($outputDir . '/ffmpeg.pid', 'snapshot-mode');
                $this->activeProcesses[$cameraId] = [
                    'pid' => 'snapshot-mode',
                    'camera' => $camera,
                    'outputDir' => $outputDir,
                    'startedAt' => new \DateTime(),
                ];
                $camera->setStatus('active');
                $this->entityManager->flush();
                $this->logger->info("Using snapshot mode for camera {$cameraId} on Windows");
                return true;
            }

            $this->stopTranscoding($cameraId);
            @unlink($frameFile);

            if (is_dir($outputDir)) {
                $files = glob($outputDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && $file !== $outputDir . '/ffmpeg.log') {
                        @unlink($file);
                    }
                }
            }

            if (isset($this->activeProcesses[$cameraId])) {
                unset($this->activeProcesses[$cameraId]);
            }

            $rtspUrl = $camera->getRtspUrl() ?: $camera->getFullStreamUrl();
            if (!$rtspUrl) {
                $this->logger->error("No stream URL found for camera {$cameraId}");
                return false;
            }

            $auth = '';
            if ($camera->getUsername() && $camera->getPassword()) {
                $auth = $camera->getUsername() . ':' . $camera->getPassword() . '@';
            }
            $inputUrl = str_replace('://', '://' . $auth, $rtspUrl);

            $quotedInputUrl = escapeshellarg($inputUrl);
            $quotedStreamOutput = escapeshellarg($outputDir . '/stream.mjpg');
            $quotedFrameOutput = escapeshellarg($frameFile);
            $quotedLogOutput = escapeshellarg($outputDir . '/ffmpeg.log');
            $quotedStdoutOutput = escapeshellarg($outputDir . '/ffmpeg.out.log');
            $quotedPidOutput = escapeshellarg($outputDir . '/ffmpeg.pid');
            $ffmpegCommand = '';

            if (!$this->isWindows()) {
                $ffmpegCommand = sprintf(
                    'nohup ffmpeg -hide_banner -loglevel warning -nostdin ' .
                    '-rtsp_transport tcp ' .
                    '-fflags +genpts+discardcorrupt -flags low_delay ' .
                    '-max_delay 500000 -analyzeduration 1000000 -probesize 32768 ' .
                    '-i %s ' .
                    '-an -sn ' .
                    '-map 0:v -vf fps=8 -c:v mjpeg -q:v 6 -f mjpeg %s ' .
                    '-map 0:v -vf fps=12 -c:v mjpeg -q:v 5 -f image2 -update 1 %s ' .
                    '> %s 2>&1 & echo $! > %s',
                    $quotedInputUrl,
                    $quotedStreamOutput,
                    $quotedFrameOutput,
                    $quotedLogOutput,
                    $quotedPidOutput
                );
            }

            if ($ffmpegCommand === '') {
                $this->logger->error("Unable to build FFmpeg command for camera {$cameraId}");
                return false;
            }

            $this->logger->info("Starting FFmpeg transcoding for camera {$cameraId} (low-latency mode)");
            $this->logger->info("Command: " . str_replace($camera->getPassword() ?? '', '***', $ffmpegCommand));

            exec($ffmpegCommand, $output, $returnCode);
            usleep(500000);

            $pidFile = $outputDir . '/ffmpeg.pid';
            $pid = null;
            if (file_exists($pidFile)) {
                $pid = trim((string) file_get_contents($pidFile));
                if ($pid !== '') {
                    $this->logger->info("FFmpeg started with PID token: {$pid}");
                }
            }

            sleep(1);
            $mjpegFile = $outputDir . '/stream.mjpg';
            $frameReady = file_exists($frameFile) && filesize($frameFile) > 0;
            $mjpegReady = file_exists($mjpegFile) && filesize($mjpegFile) > 0;

            $streamReady = $this->isWindows() ? $frameReady : ($frameReady || $mjpegReady);
            if (!$streamReady) {
                $logFile = $outputDir . '/ffmpeg.log';
                if (file_exists($logFile)) {
                    $logContent = (string) file_get_contents($logFile);
                    $this->logger->warning("FFmpeg log for camera {$cameraId}: " . substr($logContent, -500));
                }
            }

            $this->activeProcesses[$cameraId] = [
                'pid' => $pid,
                'camera' => $camera,
                'outputDir' => $outputDir,
                'startedAt' => new \DateTime(),
            ];

            $camera->setStatus('active');
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to start transcoding for camera {$cameraId}: " . $e->getMessage());
            return false;
        } finally {
            if (is_resource($lockHandle)) {
                @flock($lockHandle, LOCK_UN);
                @fclose($lockHandle);
            }
        }
    }

    /**
     * Stop transcoding for a camera
     */
    public function stopTranscoding(int $cameraId): bool
    {
        if ($this->isWindows()) {
            exec('taskkill /F /IM ffmpeg.exe >NUL 2>&1');
        } else {
            exec("pkill -9 -f 'ffmpeg.*camera_{$cameraId}' 2>/dev/null");
            exec("pkill -9 -f 'ffmpeg.*streams/camera_{$cameraId}' 2>/dev/null");
            exec("pkill -9 -f 'ffmpeg.*frame.jpg' 2>/dev/null");
        }
        @unlink($this->getFrameFile($cameraId));
        
        if (isset($this->activeProcesses[$cameraId])) {
            try {
                $processInfo = $this->activeProcesses[$cameraId];
                $outputDir = $processInfo['outputDir'];
                
                // Try to kill using PID file
                $pidFile = $outputDir . '/ffmpeg.pid';
                if (file_exists($pidFile)) {
                    $pid = trim(file_get_contents($pidFile));
                    if ($this->isWindows()) {
                        if ($pid === 'snapshot-mode') {
                            // No OS process to kill in snapshot mode.
                        } elseif (ctype_digit($pid)) {
                            exec("taskkill /PID {$pid} /F >NUL 2>&1");
                        }
                    } else {
                        exec("kill -9 $pid 2>/dev/null");
                    }
                    @unlink($pidFile);
                }
                
                unset($this->activeProcesses[$cameraId]);
                
                $this->logger->info("Stopped transcoding for camera {$cameraId}");
                return true;

            } catch (\Exception $e) {
                $this->logger->error("Failed to stop transcoding for camera {$cameraId}: " . $e->getMessage());
                return false;
            }
        }
        
        $this->logger->info("Stopped all FFmpeg processes for camera {$cameraId}");
        return true;
    }

    /**
     * Get the HLS playlist URL for a camera
     */
    public function getStreamUrl(int $cameraId): ?string
    {
        $outputDir = $this->getCameraOutputDir($cameraId);
        $frameFile = $this->getFrameFile($cameraId);
        $mjpegFile = $outputDir . '/stream.mjpg';
        $playlistFile = $outputDir . '/playlist.m3u8';

        if (file_exists($frameFile)) {
            return '/admin/cameras/' . $cameraId . '/frame.jpg';
        }

        if (file_exists($mjpegFile)) {
            return '/streams/camera_' . $cameraId . '/stream.mjpg';
        }

        if (file_exists($playlistFile)) {
            return '/streams/camera_' . $cameraId . '/playlist.m3u8';
        }

        return null;
    }

    /**
     * Get stream status for a specific camera
     */
    public function getStreamStatus(int $cameraId): array
    {
        $outputDir = $this->getCameraOutputDir($cameraId);
        $frameFile = $this->getFrameFile($cameraId);
        $streamFile = $outputDir . '/stream.mjpg';
        $playlistFile = $outputDir . '/playlist.m3u8';
        $pidFile = $outputDir . '/ffmpeg.pid';
        $logFile = $outputDir . '/ffmpeg.log';
        
        $status = [
            'running' => false,
            'pid' => null,
            'hasFrame' => false,
            'frameAge' => null,
            'hasStream' => false,
            'streamSize' => null,
            'hasPlaylist' => false,
            'lastSegment' => null,
            'lastUpdate' => null,
            'error' => null
        ];
        
        // Check if process is running
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                $status['running'] = true;
                $status['pid'] = $pid;
            }
        }
        
        if (file_exists($frameFile)) {
            $status['hasFrame'] = true;
            $frameMtime = filemtime($frameFile);
            if ($frameMtime !== false) {
                $status['frameAge'] = max(0, time() - $frameMtime);
                $status['lastUpdate'] = $frameMtime;
            }
        }

        // Check MJPEG output first
        if (file_exists($streamFile)) {
            $status['hasStream'] = true;
            $status['streamSize'] = filesize($streamFile);
            if ($status['lastUpdate'] === null) {
                $status['lastUpdate'] = filemtime($streamFile);
            }
        }

        // Keep playlist compatibility for older mode
        if (file_exists($playlistFile)) {
            $status['hasPlaylist'] = true;
            if ($status['lastUpdate'] === null) {
                $status['lastUpdate'] = filemtime($playlistFile);
            }
            
            // Get latest segment
            $segments = glob($outputDir . '/segment_*.ts');
            if (!empty($segments)) {
                usort($segments, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $status['lastSegment'] = basename($segments[0]);
            }
        }
        
        // Check for errors in log
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            if (preg_match('/(error|failed|invalid)/i', $logContent)) {
                $status['error'] = 'Check log for errors';
            }
        }
        
        return $status;
    }

    /**
     * Restart stream - stop and start fresh
     */
    public function restartStream(IpCamera $camera): bool
    {
        $cameraId = $camera->getId();
        $this->logger->info("Restarting stream for camera {$cameraId}");
        
        // Stop
        $this->stopTranscoding($cameraId);
        
        // Clean up old segments
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        if (is_dir($outputDir)) {
            $files = glob($outputDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && $file !== $outputDir . '/ffmpeg.log') {
                    @unlink($file);
                }
            }
        }
        
        // Start fresh
        sleep(1);
        return $this->startTranscoding($camera);
    }

    /**
     * Refresh stream - clear segments without stopping FFmpeg
     * This allows the stream to continue while getting fresh segments
     */
    public function refreshStream(int $cameraId): bool
    {
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        
        // Only clear old segments, keep log and pid
        if (is_dir($outputDir)) {
            $files = glob($outputDir . '/segment_*.ts');
            foreach ($files as $file) {
                @unlink($file);
            }
            
            // Also clear playlist if exists
            $playlistFile = $outputDir . '/playlist.m3u8';
            if (file_exists($playlistFile)) {
                @unlink($playlistFile);
            }
        }
        
        $this->logger->info("Refreshed stream segments for camera {$cameraId}");
        return true;
    }

    /**
     * Check if transcoding is active for a camera
     * Checks both in-memory process tracking and actual PID file
     */
    public function isTranscoding(int $cameraId): bool
    {
        // First check in-memory tracking
        if (isset($this->activeProcesses[$cameraId])) {
            $processInfo = $this->activeProcesses[$cameraId];
            $outputDir = $processInfo['outputDir'];
            
            // Check if PID file exists and process is running
            $pidFile = $outputDir . '/ffmpeg.pid';
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                if ($pid && $this->isProcessRunning($pid)) {
                    return true;
                }
            }
            
            // Process no longer running, remove from active list
            unset($this->activeProcesses[$cameraId]);
        }
        
        // Also check if HLS files exist and are being updated (fallback check)
        $outputDir = $this->getCameraOutputDir($cameraId);
        $frameFile = $this->getFrameFile($cameraId);
        $streamFile = $outputDir . '/stream.mjpg';
        $playlistFile = $outputDir . '/playlist.m3u8';
        $pidFile = $outputDir . '/ffmpeg.pid';

        if (file_exists($frameFile)) {
            $frameMtime = @filemtime($frameFile);
            if ($frameMtime !== false && (time() - $frameMtime) <= 10) {
                return true;
            }
        }

        if ((file_exists($frameFile) || file_exists($streamFile) || file_exists($playlistFile)) && file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a process with given PID is running
     */
    private function isProcessRunning(string $pid): bool
    {
        if (empty($pid)) {
            return false;
        }

        if ($pid === 'snapshot-mode') {
            return true;
        }

        if ($this->isWindows()) {
            if (!ctype_digit($pid)) {
                exec('tasklist /FI "IMAGENAME eq ffmpeg.exe" /NH', $out, $code);
                return $code === 0 && !empty($out) && stripos(implode(' ', $out), 'ffmpeg.exe') !== false;
            }

            exec("tasklist /FI \"PID eq {$pid}\" /NH", $out, $code);
            return $code === 0 && !empty($out) && stripos(implode(' ', $out), (string) $pid) !== false;
        }

        exec("ps -p $pid -o pid=", $output, $returnCode);
        return $returnCode === 0 && !empty($output);
    }

    /**
     * Get status of all active transcoding processes
     */
    public function getStatus(): array
    {
        $status = [];
        
        // Get all camera directories in streams folder
        $streamDirs = glob($this->streamDirectory . '/camera_*');
        
        foreach ($streamDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $cameraId = (int) str_replace($this->streamDirectory . '/camera_', '', $dir);
            $pidFile = $dir . '/ffmpeg.pid';
            $frameFile = $this->getFrameFile($cameraId);
            $streamFile = $dir . '/stream.mjpg';
            $playlistFile = $dir . '/playlist.m3u8';
            $logFile = $dir . '/ffmpeg.log';
            
            $isRunning = false;
            $pid = null;
            
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                if ($pid && $this->isProcessRunning($pid)) {
                    $isRunning = true;
                }
            }
            
            $lastModified = null;
            if (file_exists($frameFile)) {
                $lastModified = filemtime($frameFile);
            } elseif (file_exists($streamFile)) {
                $lastModified = filemtime($streamFile);
            } elseif (file_exists($playlistFile)) {
                $lastModified = filemtime($playlistFile);
            }
            
            $status[$cameraId] = [
                'running' => $isRunning,
                'pid' => $pid,
                'outputDir' => $dir,
                'hasFrame' => file_exists($frameFile),
                'hasStream' => file_exists($streamFile),
                'hasPlaylist' => file_exists($playlistFile),
                'lastModified' => $lastModified ? date('Y-m-d H:i:s', $lastModified) : null,
                'hasLog' => file_exists($logFile)
            ];
        }
        
        return $status;
    }

    /**
     * Clean up old stream files
     */
    public function cleanup(int $cameraId): void
    {
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        
        if (is_dir($outputDir)) {
            $files = glob($outputDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Start VLC transcoding for low-latency computer vision processing
     * VLC provides better performance and smoother playback than FFmpeg HLS
     * 
     * @param IpCamera $camera The camera entity
     * @param int $vlcHttpPort The HTTP port for VLC stream (default 8080 + cameraId)
     * @return bool True if VLC started successfully
     */
    public function startVlcTranscoding(IpCamera $camera, int $vlcHttpPort = null): bool
    {
        $cameraId = $camera->getId();
        
        // Determine port - default to 8080 + cameraId to avoid conflicts
        if ($vlcHttpPort === null) {
            $vlcHttpPort = 8080 + $cameraId;
        }
        
        // Stop any existing VLC process for this camera
        $this->stopVlcTranscoding($cameraId);
        
        $rtspUrl = $camera->getRtspUrl() ?: $camera->getFullStreamUrl();
        
        if (!$rtspUrl) {
            $this->logger->error("No stream URL found for camera {$cameraId}");
            return false;
        }
        
        // Build authentication string if credentials exist
        $auth = '';
        if ($camera->getUsername() && $camera->getPassword()) {
            $auth = $camera->getUsername() . ':' . $camera->getPassword() . '@';
        }
        
        // Replace auth in RTSP URL if needed
        $inputUrl = str_replace('://', '://' . $auth, $rtspUrl);
        
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Check if port is available
        $checkPort = shell_exec("lsof -i:{$vlcHttpPort} 2>/dev/null");
        if (!empty(trim($checkPort))) {
            $this->logger->warning("Port {$vlcHttpPort} is already in use, trying " . ($vlcHttpPort + 1));
            $vlcHttpPort = $vlcHttpPort + 1;
        }
        
        // VLC command for low-latency streaming
        // Using HTTP output with MJPEG codec for browser compatibility and low latency
        $vlcCommand = sprintf(
            'nohup cvlc -vvv "%s" ' .
            '--sout "#transcode{vcodec=MJPG,vb=2000,width=1280,height=720,acodec=none}:http{mux=multipart boundary=myboundary,dst=:/%d/stream.jpg}" ' .
            '--no-video-title-show ' .
            '--no-snapshot-preview ' .
            '--clock-jitter=0 ' .
            '--clock-synchro=0 ' .
            '> %s/vlc.log 2>&1 & echo $! > %s/vlc.pid',
            $inputUrl,
            $vlcHttpPort,
            $outputDir,
            $outputDir
        );
        
        $this->logger->info("Starting VLC transcoding for camera {$cameraId} on port {$vlcHttpPort}");
        
        try {
            exec($vlcCommand);
            usleep(500000); // 0.5 seconds
            
            $pidFile = $outputDir . '/vlc.pid';
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                $this->vlcProcesses[$cameraId] = [
                    'pid' => $pid,
                    'camera' => $camera,
                    'port' => $vlcHttpPort,
                    'outputDir' => $outputDir,
                    'startedAt' => new \DateTime()
                ];
                
                $this->logger->info("VLC started with PID: {$pid} on port {$vlcHttpPort}");
                return true;
            }
            
            // Check log for errors
            $logFile = $outputDir . '/vlc.log';
            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                $this->logger->warning("VLC log for camera {$cameraId}: " . substr($logContent, -500));
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to start VLC for camera {$cameraId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Stop VLC transcoding for a camera
     */
    public function stopVlcTranscoding(int $cameraId): bool
    {
        // Kill all VLC processes for this camera
        exec("pkill -9 -f 'cvlc.*camera_{$cameraId}' 2>/dev/null");
        exec("pkill -9 -f 'vlc.*camera_{$cameraId}' 2>/dev/null");
        
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $pidFile = $outputDir . '/vlc.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            exec("kill -9 $pid 2>/dev/null");
            @unlink($pidFile);
        }
        
        if (isset($this->vlcProcesses[$cameraId])) {
            unset($this->vlcProcesses[$cameraId]);
        }
        
        $this->logger->info("Stopped VLC for camera {$cameraId}");
        return true;
    }

    /**
     * Get the VLC HTTP stream URL for a camera
     * This provides low-latency streaming ideal for computer vision
     */
    public function getVlcStreamUrl(int $cameraId): ?string
    {
        if (isset($this->vlcProcesses[$cameraId])) {
            $port = $this->vlcProcesses[$cameraId]['port'];
            return "/streams/camera_{$cameraId}/stream.m3u8";
        }
        
        // Check if VLC is running by checking for pid file
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $pidFile = $outputDir . '/vlc.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                // Extract port from log or use default
                $logFile = $outputDir . '/vlc.log';
                $port = 8080 + $cameraId; // Default port
                
                if (file_exists($logFile)) {
                    $logContent = file_get_contents($logFile);
                    if (preg_match('/Listening on.*?:(\d+)/', $logContent, $matches)) {
                        $port = (int)$matches[1];
                    }
                }
                
                // Return MJPEG stream URL
                return "http://localhost:{$port}/stream.jpg";
            }
        }
        
        return null;
    }

    /**
     * Get the direct VLC MJPEG URL for computer vision processing
     * Returns the direct HTTP URL for the VLC stream
     */
    public function getVlcMjpegUrl(IpCamera $camera): ?string
    {
        $cameraId = $camera->getId();
        $port = 8080 + $cameraId;
        
        // Check if VLC is running
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $pidFile = $outputDir . '/vlc.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                return "http://localhost:{$port}/stream.jpg";
            }
        }
        
        return null;
    }

    /**
     * Check if VLC is transcoding for a camera
     */
    public function isVlcTranscoding(int $cameraId): bool
    {
        if (isset($this->vlcProcesses[$cameraId])) {
            $pid = $this->vlcProcesses[$cameraId]['pid'];
            if ($pid && $this->isProcessRunning($pid)) {
                return true;
            }
            unset($this->vlcProcesses[$cameraId]);
        }
        
        // Fallback: check if VLC pid file exists and process is running
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $pidFile = $outputDir . '/vlc.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && $this->isProcessRunning($pid)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Start low-latency stream optimized for computer vision
     * Uses FFmpeg with UDP output for minimal latency
     * 
     * @param IpCamera $camera The camera entity
     * @param string $socketPath Unix socket path for CV processing
     * @return bool True if started successfully
     */
    public function startCvStream(IpCamera $camera, string $socketPath = null): bool
    {
        $cameraId = $camera->getId();
        
        if ($socketPath === null) {
            $socketPath = '/tmp/cv_camera_' . $cameraId . '.sock';
        }
        
        // Stop existing CV stream
        $this->stopCvStream($cameraId);
        
        // Remove existing socket
        if (file_exists($socketPath)) {
            @unlink($socketPath);
        }
        
        $rtspUrl = $camera->getRtspUrl() ?: $camera->getFullStreamUrl();
        
        if (!$rtspUrl) {
            $this->logger->error("No stream URL found for camera {$cameraId}");
            return false;
        }
        
        // Build authentication
        $auth = '';
        if ($camera->getUsername() && $camera->getPassword()) {
            $auth = $camera->getUsername() . ':' . $camera->getPassword() . '@';
        }
        $inputUrl = str_replace('://', '://' . $auth, $rtspUrl);
        
        // Create FIFO for CV processing
        shell_exec('mkfifo ' . $socketPath . ' 2>/dev/null');
        
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        
        // Ultra-low latency FFmpeg command for CV
        // Uses frame-by-frame output for minimal delay
        $cvCommand = sprintf(
            'nohup ffmpeg -re -rtsp_transport tcp ' .
            '-fflags nobuffer -flags low_delay ' .
            '-max_delay 100000 ' .
            '-analyzeduration 100000 -probesize 100000 ' .
            '-i "%s" ' .
            '-c:v libx264 -preset ultrafast -tune zerolatency ' .
            '-b:v 2000k -g 10 -keyint_min 10 ' .
            '-vf "scale=-2:720" ' .
            '-f h264 ' .
            '- | nc -l -k -p %d > %s/cv_stream.log 2>&1 & echo $! > %s/cv.pid',
            $inputUrl,
            (9000 + $cameraId),
            $outputDir,
            $outputDir
        );
        
        $this->logger->info("Starting CV stream for camera {$cameraId}");
        
        try {
            exec($cvCommand);
            usleep(300000); // 0.3 seconds
            
            $pidFile = $outputDir . '/cv.pid';
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                $this->logger->info("CV stream started with PID: {$pid}");
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to start CV stream for camera {$cameraId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Stop CV stream for a camera
     */
    public function stopCvStream(int $cameraId): bool
    {
        exec("pkill -9 -f 'ffmpeg.*cv_camera_{$cameraId}' 2>/dev/null");
        exec("pkill -9 -f 'nc.*9000{$cameraId}' 2>/dev/null");
        
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $pidFile = $outputDir . '/cv.pid';
        
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            exec("kill -9 $pid 2>/dev/null");
            @unlink($pidFile);
        }
        
        // Clean up socket
        $socketPath = '/tmp/cv_camera_' . $cameraId . '.sock';
        if (file_exists($socketPath)) {
            @unlink($socketPath);
        }
        
        $this->logger->info("Stopped CV stream for camera {$cameraId}");
        return true;
    }

    /**
     * Get stream status including VLC and CV streams
     */
    public function getFullStreamStatus(int $cameraId): array
    {
        $baseStatus = $this->getStreamStatus($cameraId);
        
        // Add VLC status
        $baseStatus['vlcRunning'] = $this->isVlcTranscoding($cameraId);
        $baseStatus['vlcUrl'] = $this->getVlcMjpegUrl($this->entityManager->getReference(IpCamera::class, $cameraId));
        
        // Add CV stream status
        $outputDir = $this->streamDirectory . '/camera_' . $cameraId;
        $cvPidFile = $outputDir . '/cv.pid';
        $baseStatus['cvRunning'] = false;
        
        if (file_exists($cvPidFile)) {
            $pid = trim(file_get_contents($cvPidFile));
            $baseStatus['cvRunning'] = $this->isProcessRunning($pid);
        }
        
        return $baseStatus;
    }
}
