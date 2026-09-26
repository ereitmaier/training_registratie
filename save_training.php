<?php
header('Content-Type: application/json');

// 1. Lees de binnenkomende JSON uit de request body
$jsonInput = file_get_contents('php://input');

if (empty($jsonInput)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geen data ontvangen.'
    ]);
    exit;
}

// 2. Valideer of het geldige JSON is
$data = json_decode($jsonInput, true);

if (!$data) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Ongeldige JSON ontvangen.'
    ]);
    exit;
}

// 3. Bepaal de bestandsnaam op basis van de datum (bijv. training_2026-09-26.json)
$datum = !empty($data['datum']) ? $data['datum'] : date('Y-m-d');
$fileName = 'training_' . $datum . '_' . time() . '.json';

// 4. Maak de JSON weer mooi geformatteerd om op te slaan (JSON_PRETTY_PRINT zorgt voor leesbare regeleinden)
$formattedJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// 5. Sla het bestand op
if (file_put_contents($fileName, $formattedJson) !== false) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Training succesvol opgeslagen als JSON!'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Kon het bestand niet opslaan op de server.'
    ]);
}
?>