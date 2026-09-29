<?php
// save_training.php
session_start();
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['club']) || !isset($data['team']) || !isset($data['datum'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ongeldige payload']);
    exit;
}

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

try {
    $pdo->beginTransaction();

    // Haal Club ID & Team ID op
    $stmtClub = $pdo->prepare("SELECT id FROM clubs WHERE name = :name LIMIT 1");
    $stmtClub->execute(['name' => $data['club']]);
    $clubId = $stmtClub->fetchColumn();

    $stmtTeam = $pdo->prepare("SELECT id FROM teams WHERE name = :name AND club_id = :club_id LIMIT 1");
    $stmtTeam->execute(['name' => $data['team'], 'club_id' => $clubId]);
    $teamId = $stmtTeam->fetchColumn();

    if (!$clubId || !$teamId) {
        throw new Exception("Club of Team niet gevonden in database.");
    }

    $userId = $_SESSION['user_id'] ?? null;
    $trainerNaam = $data['trainer'] ?? ($_SESSION['naam'] ?? 'Onbekend');

    // 1. Voeg training toe
    $stmtIns = $pdo->prepare("
        INSERT INTO trainingen (club_id, team_id, user_id, trainer_naam, datum, notitie, versie)
        VALUES (:club_id, :team_id, :user_id, :trainer_naam, :datum, :notitie, :versie)
        RETURNING id
    ");
    $stmtIns->execute([
        'club_id'      => $clubId,
        'team_id'      => $teamId,
        'user_id'      => $userId,
        'trainer_naam' => $trainerNaam,
        'datum'        => $data['datum'],
        'notitie'      => $data['notitie'] ?? '',
        'versie'       => $data['versie'] ?? 'v2.2.0'
    ]);
    $trainingId = $stmtIns->fetchColumn();

    // 2. Voeg oefeningen toe
    $stmtOef = $pdo->prepare("
        INSERT INTO training_onderdelen (training_id, sectie, oefening_id, oefening_naam, duur_minuten, intensiteit)
        VALUES (:training_id, :sectie, :oefening_id, :oefening_naam, :duur_minuten, :intensiteit)
    ");

    foreach ($data['onderdelen'] as $item) {
        $stmtOef->execute([
            'training_id'   => $trainingId,
            'sectie'        => $item['sectie'],
            'oefening_id'   => $item['id'],
            'oefening_naam' => $item['naam'],
            'duur_minuten'  => $item['duur_minuten'] ?? 0,
            'intensiteit'   => $item['intensiteit'] ?? ''
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Training succesvol opgeslagen in database!']);

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == '23505') { // Unique constraint violation
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Deze training is al opgeslagen en vergrendeld!']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Fout bij opslaan: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}