<?php
header('Content-Type: application/json');

$club = $_GET['club'] ?? '';
$team = $_GET['team'] ?? '';
$datum = $_GET['datum'] ?? '';

if (!$club || !$team || !$datum) {
    echo json_encode(['exists' => false]);
    exit;
}

$cleanClub = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $club);
$cleanTeam = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $team);

$filePath = __DIR__ . "/data/{$cleanClub}/{$cleanTeam}/{$datum}/training.json";

if (file_exists($filePath)) {
    $savedData = json_decode(file_get_contents($filePath), true);
    echo json_encode([
        'exists' => true,
        'data' => $savedData
    ]);
} else {
    echo json_encode(['exists' => false]);
}