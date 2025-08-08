<?php
// lib/llm_api.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['webtool_logged_in']) || $_SESSION['webtool_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../helpers.php';
//validate_csrf_token();

$settings = loadSettings();

$requestData = json_decode(file_get_contents('php://input'), true);

$provider = $requestData['provider'] ?? null;
$prompt = $requestData['prompt'] ?? '';
$codeContent = $requestData['content'] ?? '';

if (!$provider || !$prompt || !$codeContent) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Fehlende Parameter.']);
    exit;
}

$apiKey = '';
$apiUrl = '';
$requestBody = [];

// Basisanleitung aus den Einstellungen holen
$baseInstructions = isset($settings) && isset($settings['llm_base_instructions']) ? $settings['llm_base_instructions'] : "";
$fullPrompt = $baseInstructions . "\n\n--- Benutzer-Prompt ---\n" . $prompt . "\n\n--- Quellcode ---\n" . $codeContent;


if ($provider === 'gemini') {
    $apiKey = $settings['gemini_api_key'] ?? '';
    if (empty($apiKey)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Gemini API Key nicht in den Einstellungen hinterlegt.']);
        exit;
    }
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;
    $requestBody = [
        'contents' => [['parts' => [['text' => $fullPrompt]]]]
    ];
} elseif ($provider === 'kimi') {
    $apiKey = $settings['kimi_api_key'] ?? '';
     if (empty($apiKey)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'KIMI K2 API Key nicht in den Einstellungen hinterlegt.']);
        exit;
    }
    $apiUrl = 'https://api.moonshot.cn/v1/chat/completions'; // Angepasst an die Kimi API
    $requestBody = [
        'model' => 'moonshot-v1-32k', // Beispiel-Modell, bitte anpassen
        'messages' => [
            ['role' => 'user', 'content' => $fullPrompt]
        ],
        'temperature' => 0.3
    ];
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unbekannter LLM-Provider.']);
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

$headers = [
    'Content-Type: application/json'
];
if ($provider === 'kimi') {
    $headers[] = 'Authorization: Bearer ' . $apiKey;
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'cURL-Fehler: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$decodedResult = json_decode($result, true);
$responseText = '';

if ($provider === 'gemini') {
    if (isset($decodedResult['candidates'][0]['content']['parts'][0]['text'])) {
        $responseText = $decodedResult['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($decodedResult['error'])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gemini API Fehler: ' . $decodedResult['error']['message']]);
        exit;
    }
} elseif ($provider === 'kimi') {
     if (isset($decodedResult['choices'][0]['message']['content'])) {
        $responseText = $decodedResult['choices'][0]['message']['content'];
    } elseif (isset($decodedResult['error'])) {
        http_response_code(500);
        $errorMessage = $decodedResult['error']['message'] ?? 'Unbekannter Fehler bei der Kimi API.';
        echo json_encode(['status' => 'error', 'message' => 'KIMI API Fehler: ' . $errorMessage]);
        exit;
    }
}

if(empty($responseText)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Leere oder unerwartete Antwort von der API erhalten.']);
    exit;
}

echo json_encode(['status' => 'success', 'response' => $responseText]);

?>
