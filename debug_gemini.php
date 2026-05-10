<?php
// debug_gemini.php

$apiKey = 'AIzaSyDH-2UERZ2avCBSYuJY1SDOwUH26duuTs8';

echo "🔍 Testing Gemini API Connection...\n\n";

// Test 1: Check if API key is valid by listing models
echo "Test 1: Listing available models...\n";
$url = "https://generativelanguage.googleapis.com/v1/models?key={$apiKey}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: " . $httpCode . "\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['models'])) {
        echo "✅ API key is valid! Found " . count($data['models']) . " models:\n";
        foreach ($data['models'] as $model) {
            echo "  - " . $model['name'] . "\n";
            echo "    Display: " . ($model['displayName'] ?? 'N/A') . "\n";
        }
    } else {
        echo "❌ Unexpected response:\n";
        print_r($data);
    }
} else {
    echo "❌ Failed to connect. HTTP Code: $httpCode\n";
    echo "Response: " . $response . "\n";
}

echo "\n";

// Test 2: Try a simple generateContent call
echo "Test 2: Testing generateContent with gemini-pro...\n";
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key={$apiKey}";

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say hello in one word']
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: " . $httpCode . "\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        echo "✅ Success! Response: " . $data['candidates'][0]['content']['parts'][0]['text'] . "\n";
    } else {
        echo "❌ Unexpected response format:\n";
        print_r($data);
    }
} else {
    echo "❌ Failed. HTTP Code: $httpCode\n";
    echo "Response: " . $response . "\n";
}

echo "\nTest complete!\n";