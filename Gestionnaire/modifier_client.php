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

// Vérifie que l'utilisateur est connecté
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

function logAction(PDO $pdo, $id_user, $action) {
    $stmt = $pdo->prepare("INSERT INTO log_actions (id_user, action) VALUES (:id_user, :action)");
    $stmt->execute([':id_user' => $id_user, ':action' => $action]);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de client invalide.");
}

$id_client = intval($_GET['id']);

// Récupérer le client
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id_client = :id");
$stmt->execute([':id' => $id_client]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die("Client non trouvé.");
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $update = $pdo->prepare("
            UPDATE clients 
            SET nom = :nom, prenom = :prenom, date_naissance = :date_naissance, 
                telephone = :telephone, formule = :formule, taille = :taille, 
                poids = :poids, pointure = :pointure
            WHERE id_client = :id
        ");

        $update->execute([
            ':nom' => $_POST['nom'],
            ':prenom' => $_POST['prenom'],
            ':date_naissance' => $_POST['date_naissance'],
            ':telephone' => $_POST['telephone'],
            ':formule' => $_POST['formule'],
            ':taille' => $_POST['taille'],
            ':poids' => $_POST['poids'],
            ':pointure' => $_POST['pointure'],
            ':id' => $id_client
        ]);

        logAction($pdo, $_SESSION['id_user'], "Modification du client ID $id_client");

        $pdo->commit();

        header("Location: clients.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Client</title>
    <link rel="stylesheet" href="style.css"> <!-- ou admin.css selon ta structure -->
    <style>
        form {
            max-width: 600px;
            margin: auto;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 8px;
        }
        form label {
            display: block;
            margin: 0.5rem 0 0.2rem;
        }
        form input {
            width: 100%;
            padding: 0.4rem;
        }
        .error {
            color: red;
            text-align: center;
        }
        .btn {
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<header>
    <h1 style="text-align:center;">Modifier un Client</h1>
</header>

<main>
    <?php if ($error_message): ?>
        <p class="error"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Nom :</label>
        <input type="text" name="nom" required value="<?= htmlspecialchars($client['nom']) ?>">

        <label>Prénom :</label>
        <input type="text" name="prenom" required value="<?= htmlspecialchars($client['prenom']) ?>">

        <label>Date de naissance :</label>
        <input type="date" name="date_naissance" required value="<?= $client['date_naissance'] ?>">

        <label>Téléphone :</label>
        <input type="text" name="telephone" value="<?= htmlspecialchars($client['telephone']) ?>">

        <label>Formule :</label>
        <input type="text" name="formule" required value="<?= htmlspecialchars($client['formule']) ?>">

        <label>Taille (cm) :</label>
        <input type="number" name="taille" step="1" value="<?= htmlspecialchars($client['taille']) ?>">

        <label>Poids (kg) :</label>
        <input type="number" name="poids" step="0.1" value="<?= htmlspecialchars($client['poids']) ?>">

        <label>Pointure :</label>
        <input type="number" name="pointure" step="0.5" value="<?= htmlspecialchars($client['pointure']) ?>">

        <button class="btn" type="submit">Enregistrer les modifications</button>
    </form>
</main>

</body>
</html>
