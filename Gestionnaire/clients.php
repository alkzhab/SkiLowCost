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

// Requête pour récupérer les clients avec leur groupe (LEFT JOIN)
$sql = "
    SELECT clients.id_client, clients.nom, clients.prenom, clients.date_naissance, clients.taille, clients.poids, clients.pointure, clients.telephone, clients.formule, groupes.nom AS nom_groupe
    FROM clients
    LEFT JOIN groupes ON clients.groupe_id = groupes.id_groupe
    ORDER BY clients.nom ASC
";


$stmt = $pdo->query($sql);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $id_client = intval($_POST['id_client']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM clients WHERE id_client = :id_client");
        $stmt->execute([':id_client' => $id_client]);

        logAction($pdo, $_SESSION['id_user'], "Suppression du client ID $id_client");

        $pdo->commit();

        header("Location: clients.php"); // pour éviter la resoumission du formulaire
        exit;
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
        logAction($pdo, $_SESSION['id_user'], "Échec de suppression du client ID $id_client : " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des Clients</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

<header>
    <h1>Gestion des Clients</h1>
    <nav>
        <ul>
            <li><a href="accueil.php">Accueil</a></li>
            <li><a href="groupes.php">Groupes</a></li>
            <li><a href="chambres.php">Chambres</a></li>
            <li><a href="reservations.php">Réservations</a></li>
            <li><a href="admin.php">Admin</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        </ul>
    </nav>
</header>

<main>
    <h2>Liste des Clients</h2>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Date de naissance</th>
                <th>Téléphone</th>
                <th>Formule</th>
                <th>Taille (cm)</th>
                <th>Poids (kg)</th>
                <th>Pointure</th>
                <th>Groupe</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($clients): ?>
                <?php foreach ($clients as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nom']) ?></td>
                        <td><?= htmlspecialchars($row['prenom']) ?></td>
                        <td><?= htmlspecialchars($row['date_naissance']) ?></td>
                        <td><?= htmlspecialchars($row['telephone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['formule']) ?></td>
                        <td><?= htmlspecialchars($row['taille']) ?></td>
                        <td><?= htmlspecialchars($row['poids']) ?></td>
                        <td><?= htmlspecialchars($row['pointure']) ?></td>
                        <td><?= htmlspecialchars($row['nom_groupe'] ?? '-') ?></td>
                        <td>
                            <a href="modifier_client.php?id=<?= $row['id_client'] ?>" class="btn_modifier">Modifier</a>
                            <form method="POST" action="clients.php" onsubmit="return confirm('Supprimer ce client ?');" style="display:inline;">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id_client" value="<?= htmlspecialchars($row['id_client'] ?? '') ?>">
                                <button type="submit" class="btn_supprimer">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" class="no-data">Aucun client trouvé.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <a href="ajout_client.php" class="btn_ajout">Ajouter un client</a>

    </table>
</main>

</body>
</html>
