<?php
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['namespace'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ongeldige payload']);
    exit;
}

$baseDir = __DIR__ . '/data';
$targetDir = $baseDir . $data['namespace'];
$filePath = $targetDir . 'training.json';

// --- FEATURE: BLOKKEER OVERSCHRIJVEN ---
if (file_exists($filePath)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Deze training is al opgeslagen en kan niet meer worden gewijzigd!'
    ]);
    exit;
}

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'success', 'message' => 'Opgeslagen in ' . $data['namespace']]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Opslaan mislukt']);
}