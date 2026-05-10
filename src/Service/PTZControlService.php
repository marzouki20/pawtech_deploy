<?php

namespace App\Service;

use App\Entity\IpCamera;
use Psr\Log\LoggerInterface;

class PTZControlService
{
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Send PTZ command to camera using ONVIF ContinuousMove protocol
     * 
     * @param IpCamera $camera Camera entity
     * @param string $action PTZ action (ptz_up, ptz_down, ptz_left, ptz_right, zoom_in, zoom_out, ptz_stop, ptz_home)
     * @param int $timeoutSeconds Timeout for ContinuousMove in seconds (default 2). Used for hold-to-move functionality.
     * @return array Response with success status and details
     */
    public function sendPTZCommand(IpCamera $camera, string $action, int $timeoutSeconds = 2): array
    {
        $ipAddress = $camera->getIpAddress();
        $port = $this->resolvePtzPort($camera);
        $username = $camera->getUsername();
        $password = $camera->getPassword();

        if (!$ipAddress) {
            return [
                'success' => false,
                'error' => 'Camera IP address not configured',
            ];
        }

        // Map action to velocity values (x, y) for ContinuousMove
        // x: positive = right, negative = left
        // y: positive = up, negative = down
        $velocities = [
            'ptz_up' => [0, 0.5],
            'ptz_down' => [0, -0.5],
            'ptz_left' => [-0.5, 0],
            'ptz_right' => [0.5, 0],
            'up' => [0, 0.5],
            'down' => [0, -0.5],
            'left' => [-0.5, 0],
            'right' => [0.5, 0],
        ];

        // Handle stop command
        if ($action === 'ptz_stop' || $action === 'stop') {
            return $this->sendOnvifStop($ipAddress, $port, $username, $password);
        }

        // Handle home command
        if ($action === 'ptz_home' || $action === 'home') {
            return $this->sendOnvifHome($ipAddress, $port, $username, $password);
        }

        // Handle zoom
        if ($action === 'zoom_in') {
            return $this->sendOnvifZoom($ipAddress, $port, $username, $password, 0.5, $timeoutSeconds);
        }
        if ($action === 'zoom_out') {
            return $this->sendOnvifZoom($ipAddress, $port, $username, $password, -0.5, $timeoutSeconds);
        }

        if (!isset($velocities[$action])) {
            return [
                'success' => false,
                'error' => "Unknown action: $action",
            ];
        }

        $vel = $velocities[$action];
        
        // Build ONVIF SOAP request for ContinuousMove with configurable timeout
        // Timeout is critical for hold-to-move: camera moves for duration of timeout
        // Frontend must send Stop command on button release to stop immediately
        $timeoutIso = sprintf('PT%dS', $timeoutSeconds);
        $body = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<s:Envelope xmlns:s=\"http://www.w3.org/2003/05/soap-envelope\">
<s:Body xmlns:ns1=\"http://www.onvif.org/ver10/ptz/wsdl\">
<ContinuousMove xmlns=\"http://www.onvif.org/ver10/ptz.wsdl\">
<ProfileToken>Profile_1</ProfileToken>
<Velocity><PanTilt x=\"{$vel[0]}\" y=\"{$vel[1]}\" space=\"http://www.onvif.org/ver10/tptz/PanTiltSpaces/VelocityGenericSpace\" xmlns=\"http://www.onvif.org/ver10/schema\"/></Velocity>
<Timeout>{$timeoutIso}</Timeout>
</ContinuousMove>
</s:Body>
</s:Envelope>";
        
        return $this->sendOnvifRequest($ipAddress, $port, $username, $password, $body, $action);
    }

    /**
     * Send ONVIF zoom command
     * 
     * @param string $ip Camera IP address
     * @param int $port Camera port
     * @param string|null $username ONVIF username
     * @param string|null $password ONVIF password
     * @param float $speed Zoom speed (-1 to 1)
     * @param int $timeoutSeconds Timeout in seconds for hold-to-move
     * @return array Response with success status
     */
    private function sendOnvifZoom(string $ip, int $port, ?string $username, ?string $password, float $speed, int $timeoutSeconds = 2): array
    {
        $timeoutIso = sprintf('PT%dS', $timeoutSeconds);
        $body = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<s:Envelope xmlns:s=\"http://www.w3.org/2003/05/soap-envelope\">
<s:Body xmlns:ns1=\"http://www.onvif.org/ver10/ptz/wsdl\">
<ContinuousMove xmlns=\"http://www.onvif.org/ver10/ptz.wsdl\">
<ProfileToken>Profile_1</ProfileToken>
<Velocity><Zoom x=\"{$speed}\" space=\"http://www.onvif.org/ver10/tptz/ZoomSpaces/VelocityGenericSpace\" xmlns=\"http://www.onvif.org/ver10/schema\"/></Velocity>
<Timeout>{$timeoutIso}</Timeout>
</ContinuousMove>
</s:Body>
</s:Envelope>";

        return $this->sendOnvifRequest($ip, $port, $username, $password, $body, $speed > 0 ? 'zoom_in' : 'zoom_out');
    }

    /**
     * Send ONVIF stop command
     */
    private function sendOnvifStop(string $ip, int $port, ?string $username, ?string $password): array
    {
        $this->log("Executing ONVIF Stop command to $ip:$port");
        
        $body = '<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
<s:Body xmlns:ns1="http://www.onvif.org/ver10/ptz/wsdl">
<Stop xmlns="http://www.onvif.org/ver10/ptz.wsdl">
<ProfileToken>Profile_1</ProfileToken>
<PanTilt>true</PanTilt>
<Zoom>true</Zoom>
</Stop>
</s:Body>
</s:Envelope>';
        $result = $this->sendOnvifRequest($ip, $port, $username, $password, $body, 'stop');
        if ($result['success'] ?? false) {
            return $result;
        }

        // Some cameras reject Stop but accept zero-velocity ContinuousMove as stop.
        $this->log("Primary Stop failed, retrying with zero-velocity ContinuousMove.");
        $fallbackResult = $this->sendOnvifStopViaZeroVelocity($ip, $port, $username, $password);
        if ($fallbackResult['success'] ?? false) {
            $fallbackResult['message'] = 'Stop succeeded via zero-velocity fallback';
        }
        return $fallbackResult;
    }

    /**
     * Send ONVIF home command (go to preset 0)
     */
    private function sendOnvifHome(string $ip, int $port, ?string $username, ?string $password): array
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
<s:Body xmlns:ns1="http://www.onvif.org/ver10/ptz/wsdl">
<AbsoluteMove xmlns="http://www.onvif.org/ver10/ptz.wsdl">
<ProfileToken>Profile_1</ProfileToken>
<Position><PanTilt x="0" y="0" space="http://www.onvif.org/ver10/tptz/PanTiltSpaces/PositionGenericSpace" xmlns="http://www.onvif.org/ver10/schema"/></Position>
<Speed><PanTilt x="0.5" y="0.5" space="http://www.onvif.org/ver10/tptz/PanTiltSpaces/GenericSpeedSpace" xmlns="http://www.onvif.org/ver10/schema"/></Speed>
</AbsoluteMove>
</s:Body>
</s:Envelope>';

        return $this->sendOnvifRequest($ip, $port, $username, $password, $body, 'home');
    }

    /**
     * Send ONVIF request via curl
     */
    private function sendOnvifRequest(string $ip, int $port, ?string $username, ?string $password, string $body, string $action): array
    {
        $url = "http://$ip:$port/onvif/ptz";
        $this->log("Sending ONVIF $action to $url");

        // Save body to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'onvif_');
        file_put_contents($tempFile, $body);
        
        // Build curl command with proper ONVIF headers.
        $soapAction = $this->resolveSoapAction($action);
        $authPart = '';
        if ($username !== null && $username !== '') {
            $authPart = '-u ' . escapeshellarg($username . ':' . ($password ?? '')) . ' ';
        }

        $curlCmd = sprintf(
            'curl -s --connect-timeout 3 --max-time 10 %s-H "Content-Type: application/soap+xml" -H "SOAPAction: %s" -d @%s %s 2>&1',
            $authPart,
            $soapAction,
            escapeshellarg($tempFile),
            escapeshellarg($url)
        );
        
        $this->log("Curl command: " . str_replace($password, '***', $curlCmd));
        
        exec($curlCmd, $output, $returnCode);
        unlink($tempFile);
        
        $response = implode("\n", $output);
        
        // Determine status code from response
        $statusCode = 200; // Assume success if no error
        if ($returnCode !== 0) {
            $statusCode = 500;
            $this->log("Curl error: return code $returnCode, output: " . substr($response, 0, 200));
        } elseif (str_contains($response, '<env:Fault>') || str_contains($response, '<s:Fault>') || str_contains($response, '<Fault>')) {
            $statusCode = 400;
        }

        $this->log("ONVIF response: $statusCode - " . substr($response, 0, 200));

        // Check for fault response
        if (str_contains($response, '<env:Fault>') || str_contains($response, '<s:Fault>') || str_contains($response, '<Fault>')) {
            return [
                'success' => false,
                'protocol' => 'onvif',
                'message' => 'ONVIF fault returned by camera',
                'error' => 'Camera returned fault',
                'details' => substr($response, 0, 300),
                'status_code' => $statusCode,
            ];
        }

        // Empty response usually means success for ONVIF
        $isSuccess = $statusCode < 400;
        return [
            'success' => $isSuccess,
            'status_code' => $statusCode,
            'action' => $action,
            'protocol' => 'onvif',
            'message' => $isSuccess ? 'PTZ command sent' : 'PTZ command failed',
            'error' => $isSuccess ? null : 'Unknown PTZ transport error',
            'response_length' => strlen($response),
        ];
    }

    private function sendOnvifStopViaZeroVelocity(string $ip, int $port, ?string $username, ?string $password): array
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
<s:Body xmlns:ns1="http://www.onvif.org/ver10/ptz/wsdl">
<ContinuousMove xmlns="http://www.onvif.org/ver10/ptz.wsdl">
<ProfileToken>Profile_1</ProfileToken>
<Velocity>
<PanTilt x="0.0" y="0.0" space="http://www.onvif.org/ver10/tptz/PanTiltSpaces/VelocityGenericSpace" xmlns="http://www.onvif.org/ver10/schema"/>
</Velocity>
<Timeout>PT1S</Timeout>
</ContinuousMove>
</s:Body>
</s:Envelope>';

        return $this->sendOnvifRequest($ip, $port, $username, $password, $body, 'stop_fallback');
    }

    private function resolveSoapAction(string $action): string
    {
        if ($action === 'stop') {
            return 'http://www.onvif.org/ver10/ptz/wsdl/Stop';
        }
        if ($action === 'home') {
            return 'http://www.onvif.org/ver10/ptz/wsdl/AbsoluteMove';
        }
        if (str_starts_with($action, 'preset_')) {
            return 'http://www.onvif.org/ver10/ptz/wsdl/GotoPreset';
        }

        return 'http://www.onvif.org/ver10/ptz/wsdl/ContinuousMove';
    }

    private function resolvePtzPort(IpCamera $camera): int
    {
        $settings = $camera->getCameraSettings() ?? [];
        if (isset($settings['ptz_port'])) {
            $ptzPort = (int) $settings['ptz_port'];
            if ($ptzPort > 0 && $ptzPort <= 65535) {
                return $ptzPort;
            }
        }

        return $camera->getPort() ?: 80;
    }

    /**
     * Move camera to a preset position
     */
    public function gotoPreset(IpCamera $camera, int $preset): array
    {
        $ipAddress = $camera->getIpAddress();
        $port = $camera->getPort();
        $username = $camera->getUsername();
        $password = $camera->getPassword();

        if (!$ipAddress) {
            return [
                'success' => false,
                'error' => 'Camera IP address not configured',
            ];
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope">
<s:Body xmlns:ns1="http://www.onvif.org/ver10/ptz/wsdl">
<GotoPreset xmlns="http://www.onvif.org/ver10/ptz.wsdl">
<ProfileToken>Profile_1</ProfileToken>
<PresetToken>' . $preset . '</PresetToken>
</GotoPreset>
</s:Body>
</s:Envelope>';

        return $this->sendOnvifRequest($ipAddress, $port, $username, $password, $body, "preset_$preset");
    }

    /**
     * Get PTZ capabilities for a camera
     */
    public function getCapabilities(IpCamera $camera): array
    {
        return [
            'supported' => true,
            'ptz' => true,
            'zoom' => true,
            'presets' => true,
            'protocols' => ['onvif'],
        ];
    }

    /**
     * Internal logging
     */
    private function log(string $message): void
    {
        if ($this->logger) {
            $this->logger->info('[PTZ] ' . $message);
        }
    }
}
