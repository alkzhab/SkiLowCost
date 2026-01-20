<?php
session_start();


// Connexion à la base de données PostgreSQL
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
    $stmt = $pdo->prepare("INSERT INTO log_actions (id_user, action) VALUES (:id_user, :action)");
    $stmt->execute([
        ':id_user' => $id_user,
        ':action' => $action
    ]);
}




// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifiant = $_POST["identifiant"] ?? '';
    $mot_de_passe = $_POST["mot_de_passe"] ?? '';

    // Requête pour vérifier l'utilisateur
    $stmt = $pdo->prepare('SELECT "id_user", "identifiant", "mot_de_passe", "admin" FROM "user" WHERE "identifiant" = :identifiant');
    $stmt->bindParam(':identifiant', $identifiant);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        if ($_SESSION['$mot_de_passe'] = $user['mot_de_passe']) {
            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['identifiant'] = $user['identifiant'];
            $_SESSION['admin'] = $user['admin'];
            logAction($pdo, $user['id_user'], "Connexion réussie");
            header("Location: accueil.php");
            exit();
        } else {
            $error = "Identifiant ou mot de passe incorrect.";
        }
    } else {
        $error = "Identifiant ou mot de passe incorrect.";
    }

}
?>





<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="stylesheet" href="style.css">
</head>


<body class="login-page">
    <div class="login-container">
        <h1>Connexion</h1>
        <form action="login.php" method="post">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <input type="text" name="identifiant" placeholder="Nom d'utilisateur" required>
            <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    </div>

</body>
</html>
