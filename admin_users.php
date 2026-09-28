<?php
// admin_users.php
session_start();

// 1. Database Verbinding via Omgevingsvariabelen
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '5432';$dbname   = getenv('DB_NAME') ?: 'trainings_db';
$user     = getenv('DB_USER') ?: 'postgres';$dbPass   = getenv('DB_PASSWORD');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user,$dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database verbinding mislukt.");
}

// SIMULATIE / AFHANDELING VAN INGELOGDE BEHEERDER
// In productie haal je dit uit $_SESSION['user_id'] en$_SESSION['role']
$currentUserId =$_SESSION['user_id'] ?? 1; // Voorbeeld ID
$currentUserRole =$_SESSION['user_role'] ?? 'Admin'; // 'Superadmin' of 'Admin'

// Controleer of de ingelogde gebruiker wel bevoegd is
if (!in_array($currentUserRole, ['Admin', 'Superadmin'])) {
    http_response_code(403);
    die("Toegang geweigerd: Je hebt onvoldoende rechten.");
}

$message = '';$messageType = '';

// 2. VERWERKING VAN HET FORMULIER (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {$naam     = trim($_POST['naam'] ?? '');$email    = trim($_POST['email'] ?? '');$password = $_POST['password'] ?? '';$clubId   = !empty($_POST['club_id']) ? intval($_POST['club_id']) : null;
    $teamId   = !empty($_POST['team_id']) ? intval($_POST['team_id']) : null;
    $role     =$_POST['role'] ?? '';

    // Validatie
    if (empty($naam) || empty($email) \vert{}\vert{} empty($password) || empty($role)) {$message = "Vul alle verplichte velden in.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {$message = "Ongeldig e-mailadres.";
        $messageType = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // A. Voeg gebruiker toe aan `users`
            $stmtUser =$pdo->prepare("INSERT INTO users (naam, email) VALUES (:naam, :email) RETURNING id");
            $stmtUser->execute(['naam' => $naam, 'email' =>$email]);
            $newUserId =$stmtUser->fetchColumn();

            // B. Hash wachtwoord en voeg toe aan `passwords`
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmtPass =$pdo->prepare("INSERT INTO passwords (user_id, encrypted_password, is_active) VALUES (:user_id, :password, TRUE)");
            $stmtPass->execute([
                'user_id'  => $newUserId,
                'password' => $hashedPassword
            ]);

            // C. Wijs Rol, Club en optioneel Team toe in `user_roles`
            $stmtRole =$pdo->prepare("INSERT INTO user_roles (user_id, role, club_id, team_id) VALUES (:user_id, :role, :club_id, :team_id)");
            $stmtRole->execute([
                'user_id' => $newUserId,
                'role'    => $role,
                'club_id' => $clubId,
                'team_id' => $teamId
            ]);

            $pdo->commit();
            $message = "Gebruiker '{$naam}' is succesvol aangemaakt!";
            $messageType = "success";
        } catch (PDOException $e) {$pdo->rollBack();
            if ($e->getCode() == '23505') { // Unique constraint violation (e-mail bestaat al)$message = "Dit e-mailadres is al in gebruik.";
            } else {
                $message = "Fout bij opslaan: " . $e->getMessage();
            }
            $messageType = "error";
        }
    }
}

// 3. HAAL CLUBS EN TEAMS OP OP BASIS VAN ROL
if ($currentUserRole === 'Superadmin') {
    // Superadmin mag alle clubs zien
    $clubs =$pdo->query("SELECT id, name FROM clubs ORDER BY name")->fetchAll();
    $teams =$pdo->query("SELECT id, club_id, name FROM teams ORDER BY name")->fetchAll();
} else {
    // Admin ziet alleen toegewezen club(s)
    $stmtClubs =$pdo->prepare("
        SELECT DISTINCT c.id, c.name 
        FROM clubs c 
        JOIN user_roles ur ON ur.club_id = c.id 
        WHERE ur.user_id = :user_id AND ur.role = 'Admin'
        ORDER BY c.name
    ");
    $stmtClubs->execute(['user_id' =>$currentUserId]);
    $clubs =$stmtClubs->fetchAll();

    $allowedClubIds = array_column($clubs, 'id');
    if (!empty($allowedClubIds)) {
        $inQuery = implode(',', array_map('intval',$allowedClubIds));
        $teams =$pdo->query("SELECT id, club_id, name FROM teams WHERE club_id IN ($inQuery) ORDER BY name")->fetchAll();
    } else {
        $teams = [];
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gebruikersbeheer - Admin</title>
    <style>
        :root {
            --primary: #10b981;
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --text: #1f2937;
            --border: #e5e7eb;
            --error: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: var(--bg); color: var(--text); padding: 20px; }

        .container { max-width: 500px; margin: 0 auto; background: var(--card-bg); padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { font-size: 1.3rem; margin-bottom: 20px; color: #1e293b; text-align: center; }

        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: #f8fafc; font-size: 0.95rem; }

        .btn { background-color: var(--primary); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; width: 100%; cursor: pointer; font-size: 0.95rem; margin-top: 10px; }
        .btn:hover { background-color: #059669; }

        .alert { padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px; text-align: center; }
        .alert.success { background: #d1fae5; color: #065f46; }
        .alert.error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="container">
    <h1>👤 Nieuwe Gebruiker Aanmaken</h1>

    <?php if ($message): ?>
        <div class="alert <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" action="admin_users.php">
        <div class="form-group">
            <label for="naam">Volledige Naam</label>
            <input type="text" id="naam" name="naam" required placeholder="bijv. Jan de Trainer">
        </div>

        <div class="form-group">
            <label for="email">E-mailadres (Inlognaam)</label>
            <input type="email" id="email" name="email" required placeholder="jan@vereniging.nl" autocomplete="off">
        </div>

        <div class="form-group">
            <label for="password">Wachtwoord Instellen</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
        </div>

        <div class="form-group">
            <label for="club_id">Vereniging (Club)</label>
            <select id="club_id" name="club_id" required onchange="filterTeams()">
                <option value="">-- Selecteer Vereniging --</option>
                <?php foreach ($clubs as$club): ?>
                    <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="team_id">Team (Optioneel voor Admins)</label>
            <select id="team_id" name="team_id">
                <option value="">-- Selecteer Team --</option>
                <?php foreach ($teams as$team): ?>
                    <option value="<?= $team['id'] ?>" data-club="<?= $team['club_id'] ?>">
                        <?= htmlspecialchars($team['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="role">Rol</label>
            <select id="role" name="role" required>
                <option value="Trainer">Trainer (Lezen & Schrijven)</option>
                <option value="Coach">Coach (Alleen Lezen)</option>
                <?php if ($currentUserRole === 'Superadmin'): ?>
                    <option value="Admin">Admin (Clubbeheerder)</option>
                    <option value="Superadmin">Superadmin</option>
                <?php endif; ?>
            </select>
        </div>

        <button type="submit" class="btn">Gebruiker Opslaan</button>
    </form>
</div>

<script>
// Filter de teams in de dropdown op basis van de gekozen club
function filterTeams() {
    const selectedClubId = document.getElementById('club_id').value;
    const teamSelect = document.getElementById('team-id');
    const options = teamSelect.querySelectorAll('option');

    options.forEach(option => {
        if (!option.value) return; // Sla de placeholder over
        const clubId = option.getAttribute('data-club');
        
        if (selectedClubId && clubId === selectedClubId) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });

    teamSelect.value = ''; // Reset selectie
}
</script>

</body>
</html>