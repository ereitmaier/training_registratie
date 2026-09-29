<?php
// api_fetch_data.php
header('Content-Type: application/json');

// Geheime API Key (haak dit eventueel aan getenv('API_KEY'))
$secretApiKey = getenv('STREAMLIT_API_KEY') ?: 'JOUW_SUPER_GEHEIME_API_KEY_123';

// Controleer Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if ($authHeader !== 'Bearer ' . $secretApiKey) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorised: Ongeldige API Key']);
    exit;
}

$club  = $_GET['club'] ?? '';
$team  = $_GET['team'] ?? '';
$jaar  = $_GET['jaar'] ?? '';
$maand = $_GET['maand'] ?? '';
$dag   = $_GET['dag'] ?? '';

if (!$club || !$team || !$jaar || !$maand || !$dag) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Onvolledige parameters']);
    exit;
}

$cleanClub = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $club);
$cleanTeam = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $team);

$filePath = __DIR__ . "/data/{$cleanClub}/{$cleanTeam}/{$jaar}/{$maand}/{$jaar}-{$maand}-{$dag}-training.json";

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Bestand niet gevonden']);
    exit;
}

// Geef de inhoud van het JSON bestand veilig terug
echo file_get_contents($filePath);