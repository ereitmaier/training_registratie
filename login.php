<?php
// login.php

header('Content-Type: application/json');

// 1. Ontvang en decodeer de JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Vul gebruikersnaam en wachtwoord in.']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];

// 2. Database verbinding via omgevingsvariabelen (geen hardcoded wachtwoorden!)
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '5432';
$dbname   = getenv('DB_NAME') ?: 'trainings_db';
$user     = getenv('DB_USER') ?: 'postgres';
$dbPass   = getenv('DB_PASSWORD'); // Wordt uit de server-omgeving gehaald

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    // Geef GEEN gedetailleerde foutmeldingen/wachtwoorden prijs aan de client
    echo json_encode(['status' => 'error', 'message' => 'Fout bij verbinden met de database.']);
    exit;
}

// 3. Haal gebruiker en de actieve wachtwoord-hash op
$sqlUser = "
    SELECT 
        u.id AS user_id, 
        u.naam, 
        u.email, 
        p.encrypted_password 
    FROM users u
    JOIN passwords p ON u.id = p.user_id
    WHERE u.email = :username AND p.is_active = TRUE
    LIMIT 1;
";

$stmt = $pdo->prepare($sqlUser);
$stmt->execute(['username' => $username]);
$userRecord = $stmt->fetch();

// 4. Veilig verifiëren (vergelijkt invoer met de bcrypt-hash uit de database)
if (!$userRecord || !password_verify($password, $userRecord['encrypted_password'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Ongeldige inloggegevens.']);
    exit;
}

$userId = $userRecord['user_id'];

// 5. Update inlogstatistieken
$sqlUpdateLogin = "
    UPDATE users 
    SET last_login = NOW(), 
        login_count = login_count + 1 
    WHERE id = :user_id;
";
$stmtUpdate = $pdo->prepare($sqlUpdateLogin);
$stmtUpdate->execute(['user_id' => $userId]);

// 6. Haal toegewezen Clubs, Teams en Rollen op
$sqlAssignments = "
    SELECT 
        ur.role,
        c.id AS club_id, 
        c.name AS club_name,
        t.id AS team_id, 
        t.name AS team_name
    FROM user_roles ur
    LEFT JOIN clubs c ON ur.club_id = c.id
    LEFT JOIN teams t ON ur.team_id = t.id
    WHERE ur.user_id = :user_id;
";

$stmtAssignments = $pdo->prepare($sqlAssignments);
$stmtAssignments->execute(['user_id' => $userId]);
$assignments = $stmtAssignments->fetchAll();

if (empty($assignments)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Geen verenigingen of teams toegewezen.']);
    exit;
}

// 7. Succesvolle respons
echo json_encode([
    'status' => 'success',
    'message' => 'Inloggen geslaagd',
    'data' => [
        'user_id' => $userId,
        'naam' => $userRecord['naam'],
        'assignments' => $assignments
    ]
]);