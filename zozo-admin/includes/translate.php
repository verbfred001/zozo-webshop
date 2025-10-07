<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode([
            'success' => false,
            'error' => 'No JSON input received'
        ]);
        exit;
    }

    $text = $input['text'] ?? '';
    $targetLang = $input['target_lang'] ?? 'en';

    if (empty($text)) {
        echo json_encode([
            'success' => false,
            'error' => 'No text provided'
        ]);
        exit;
    }

    // Gebruik MyMemory API (gratis)
    try {
        $apiUrl = "https://api.mymemory.translated.net/get?q=" . urlencode($text) . "&langpair=nl|" . $targetLang;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);

            if (isset($data['responseData']['translatedText'])) {
                $translation = $data['responseData']['translatedText'];

                // Clean up HTML entities
                $translation = html_entity_decode($translation, ENT_QUOTES, 'UTF-8');
                $translation = strip_tags($translation);

                echo json_encode([
                    'success' => true,
                    'translation' => trim($translation)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No translation found'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'API request failed'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Translation error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
