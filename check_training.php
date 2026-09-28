<?php
header('Content-Type: application/json');

$club  = $_GET['club'] ?? '';
$team  = $_GET['team'] ?? '';
$datum = $_GET['datum'] ?? ''; // Verwacht yyyy-mm-dd

if (!$club || !$team || !$datum) {
    echo json_encode(['exists' => false]);
    exit;
}

$cleanClub = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $club);
$cleanTeam = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $team);

// Datum opsplitsen naar Jaar, Maand, Dag
$dateParts = explode('-', $datum);
if (count($dateParts) !== 3) {
    echo json_encode(['exists' => false]);
    exit;
}

$jaar  = $dateParts[0];
$maand = $dateParts[1];
$dag   = $dateParts[2];

// Nieuwe padstructuur: /data/<club>/<team>/<jaar>/<maand>/<jaar>-<maand>-<dag>-training.json
$filePath = __DIR__ . "/data/{$cleanClub}/{$cleanTeam}/{$jaar}/{$maand}/{$datum}-training.json";

if (file_exists($filePath)) {
    $savedData = json_decode(file_get_contents($filePath), true);
    echo json_encode([
        'exists' => true,
        'data'   => $savedData
    ]);
} else {
    echo json_encode(['exists' => false]);
}