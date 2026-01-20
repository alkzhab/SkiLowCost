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

// Vérifie la session utilisateur
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

function logAction(PDO $pdo, $id_user, $action) {
    $stmt = $pdo->prepare("INSERT INTO log_actions (id_user, action) VALUES (:id_user, :action)");
    $stmt->execute([
        ':id_user' => $id_user,
        ':action' => $action
    ]);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = $_POST['numero_chambre'] ?? '';
    $lits = $_POST['nombre_lits'] ?? '';
    $superficie = $_POST['superfecie'] ?? '';
    $balcon = isset($_POST['balcon']) ? 1 : 0;
    $vue = $_POST['vue'] ?? '';
    $etage = $_POST['etage'] ?? '';
    $batiment = $_POST['batiment'] ?? '';
    $libre = isset($_POST['libre']) ? 1 : 0;

    if ($numero && $lits && $superficie && $vue && $etage && $batiment) {
        try {
            // Démarrer une transaction
            $pdo->beginTransaction();

            // Insertion dans la table chambre
            $stmt = $pdo->prepare("
                INSERT INTO chambre (numero_chambre, nombre_lits, superfecie, balcon, vue, etage, batiment, libre)
                VALUES (:numero, :lits, :superficie, :balcon, :vue, :etage, :batiment, :libre)
            ");
            $stmt->execute([
                ':numero' => $numero,
                ':lits' => $lits,
                ':superficie' => $superficie,
                ':balcon' => $balcon,
                ':vue' => $vue,
                ':etage' => $etage,
                ':batiment' => $batiment,
                ':libre' => $libre,
            ]);

            // Insertion dans le journal d’actions
            $stmtLog = $pdo->prepare("INSERT INTO log_actions (id_user, action) VALUES (:id_user, :action)");
            $stmtLog->execute([
                ':id_user' => $_SESSION['id_user'],
                ':action' => "Ajout d'une nouvelle chambre"
            ]);

            // Commit si tout est bon
            $pdo->commit();

            header("Location: chambres.php");
            exit();

        } catch (PDOException $e) {
            // Rollback en cas d’erreur
            $pdo->rollBack();
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } else {
        $error = "Tous les champs obligatoires doivent être remplis.";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une chambre</title>
    <link rel="stylesheet" href="ajout_chambre.css">
</head>
<body>

<header>
    <nav>
        <ul>
            <li><a href="chambres.php">← Retour aux chambres</a></li>
        </ul>
    </nav>
</header>

<main>
    <h1>Ajouter une nouvelle chambre</h1>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label>Numéro de chambre* :
            <input type="text" name="numero_chambre" required>
        </label><br>

        <label>Nombre de lits* :
            <input type="number" name="nombre_lits" min="1" required>
        </label><br>

        <label>Superficie (en m²)* :
            <input type="number" name="superfecie" min="1" step="0.1" required>
        </label><br>

        <label>
            <input type="checkbox" name="balcon"> Balcon
        </label><br>

        <label>Vue* :
            <input type="text" name="vue" required>
        </label><br>

        <label>Étage* :
            <input type="number" name="etage" required>
        </label><br>

        <label>Bâtiment* :
            <input type="text" name="batiment" required>
        </label><br>

        <label>
            <input type="checkbox" name="libre" checked> Chambre disponible
        </label><br><br>

        <button type="submit">Ajouter la chambre</button>
    </form>
</main>

</body>
</html>
