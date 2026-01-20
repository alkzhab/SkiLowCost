<?php
session_start();

$host = '';
$port = '';
$dbname = '';
$user = '';
$password = '';

try {
    // Connexion à PostgreSQL
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

function logAction(PDO $pdo, $id_user, $action) {
    if ($pdo) {
        $stmt = $pdo->prepare("INSERT INTO log_actions (id_user, action) VALUES (:id_user, :action)");
        $stmt->execute([
            ':id_user' => $id_user,
            ':action' => $action
        ]);
    }
}

if (isset($_SESSION['id_user'])) {
    // Log de la déconnexion
    logAction($pdo, $_SESSION['id_user'], "Déconnexion");
}

// Détruit toutes les variables de session
$_SESSION = array();

// Supprime les cookies de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruit la session
session_destroy();

// Redirige vers la page de connexion
header('Location: login.php');
exit();
