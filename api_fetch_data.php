<?php
// api_fetch_data.php
header('Content-Type: application/json');

// 1. Bearer Token Beveiligingscheck
$secretApiKey = getenv('STREAMLIT_API_KEY') ?: 'JOUW_SUPER_GEHEIME_API_KEY_123';
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($authHeader !== 'Bearer ' . $secretApiKey) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorised: Ongeldige API Key']);
    exit;
}

// 2. Database Verbinding
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '5432';
$dbname   = getenv('DB_NAME') ?: 'trainings_db';
$user     = getenv('DB_USER') ?: 'postgres';
$dbPass   = getenv('DB_PASSWORD') ?: '';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database verbinding mislukt']);
    exit;
}

$action = $_GET['action'] ?? 'data';

// ROUTE 1: Haal de lijst van Clubs en Teams op voor de dropdowns
if ($action === 'options') {
    try {
        $sql = "
            SELECT DISTINCT c.name AS club_name, t.name AS team_name
            FROM clubs c
            JOIN teams t ON t.club_id = c.id
            ORDER BY c.name, t.name;
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();

        $structure = [];
        foreach ($rows as $row) {
            $club = $row['club_name'];
            $team = $row['team_name'];
            if (!isset($structure[$club])) {
                $structure[$club] = [];
            }
            $structure[$club][] = $team;
        }

        echo json_encode(['status' => 'success', 'data' => $structure]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Fout bij ophalen opties']);
        exit;
    }
}

// ROUTE 2: Haal trainingsdata op

// ROUTE 2: Haal trainingsdata op
$club       = $_GET['club'] ?? '';
$team       = $_GET['team'] ?? '';
$startDate  = $_GET['start_date'] ?? '';
$endDate    = $_GET['end_date'] ?? '';

if (!$club || !$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Vereiste parameters ontbreken']);
    exit;
}

try {
    // SQL query ondersteunt nu 'Alle' teams en is ongevoelig voor hoofdletters
    $sql = "
        SELECT 
            t.datum,
            c.name AS club,
            tm.name AS team,
            t.trainer_naam AS trainer,
            to_sub.sectie,
            to_sub.oefening_id AS id,
            to_sub.oefening_naam AS activiteit,
            to_sub.duur_minuten,
            to_sub.intensiteit
        FROM trainingen t
        JOIN clubs c ON t.club_id = c.id
        JOIN teams tm ON t.team_id = tm.id
        JOIN training_onderdelen to_sub ON to_sub.training_id = t.id
        WHERE c.name ILIKE :club
          AND (:team = '' OR :team = 'Alle' OR tm.name ILIKE :team)
          AND t.datum BETWEEN :start_date AND :end_date
        ORDER BY t.datum DESC;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'club'       => trim($club),
        'team'       => trim($team),
        'start_date' => $startDate,
        'end_date'   => $endDate
    ]);

    $records = $stmt->fetchAll();
    
    // Geef de records netjes als JSON array terug
    echo json_encode($records);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Databasefout: ' . $e->getMessage()]);
}
