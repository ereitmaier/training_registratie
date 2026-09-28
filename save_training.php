<?php
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['namespace'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ongeldige payload']);
    exit;
}

// Bepaal pad op basis van namespace
$baseDir = __DIR__ . '/data';
$targetDir = $baseDir . $data['namespace'];

// Maak de mappenstructuur aan indien deze nog niet bestaat
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$filePath = $targetDir . 'training.json';

if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'success', 'message' => 'Opgeslagen in ' . $data['namespace']]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Opslaan mislukt']);
}