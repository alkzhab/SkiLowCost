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

// Suppression d'un groupe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer_groupe') {
    $id_groupe = intval($_POST['id_groupe']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM groupes WHERE id_groupe = :id_groupe");
        $stmt->execute([':id_groupe' => $id_groupe]);

        $pdo->commit();

        logAction($pdo, $_SESSION['id_user'], "Suppression du groupe ID $id_groupe");

        header("Location: groupes.php"); // évite resoumission
        exit;
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
        logAction($pdo, $_SESSION['id_user'], "Échec de suppression du groupe ID $id_groupe : " . $e->getMessage());
    }
}

$sql = "
    SELECT g.id_groupe, g.nom AS nom_groupe, COUNT(c.id_client) AS nombre_clients
    FROM groupes g
    LEFT JOIN clients c ON g.id_groupe = c.groupe_id
    GROUP BY g.id_groupe
    ORDER BY nombre_clients DESC
";

$stmt = $pdo->query($sql);
$groupes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ajout d'un groupe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_groupe') {
    $nom_groupe = trim($_POST['nom_groupe'] ?? '');

    if (!empty($nom_groupe)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO groupes (nom) VALUES (:nom)");
            $stmt->execute([':nom' => $nom_groupe]);

            logAction($pdo, $_SESSION['id_user'], "Ajout d'un nouveau groupe");

            header("Location: groupes.php"); // évite re-soumission
            exit;
        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'ajout : " . $e->getMessage();
            logAction($pdo, $_SESSION['id_user'], "Échec d'ajout d'un nouveau groupe : " . $e->getMessage());
        }
    } else {
        $error_message = "Le nom du groupe ne peut pas être vide.";
    }
}

// Modification d'un groupe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_groupe') {
    $id_groupe = intval($_POST['id_groupe']);
    $nouveau_nom = trim($_POST['nouveau_nom']);

    if (!empty($nouveau_nom)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE groupes SET nom = :nom WHERE id_groupe = :id");
            $stmt->execute([':nom' => $nouveau_nom, ':id' => $id_groupe]);

            $pdo->commit();

            logAction($pdo, $_SESSION['id_user'], "Modification du groupe ID $id_groupe (nouveau nom : $nouveau_nom)");

            header("Location: groupes.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollback();
            $error_message = "Erreur lors de la modification : " . $e->getMessage();
            logAction($pdo, $_SESSION['id_user'], "Échec de modification du groupe ID $id_groupe : " . $e->getMessage());
        }
    } else {
        $error_message = "Le nom ne peut pas être vide.";
    }
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Groupes & Familles</title>
    <link rel="stylesheet" href="style.css" />
</head>
<body>

<header>
    <h1>Groupes et Familles</h1>
    <nav>
        <ul>
            <li><a href="accueil.php">Accueil</a></li>
            <li><a href="clients.php">Clients</a></li>
            <li><a href="chambres.php">Chambres</a></li>
            <li><a href="reservations.php">Réservations</a></li>
            <li><a href="admin.php">Admin</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        </ul>
    </nav>
</header>

<main>
    <h2>Liste des Groupes & Familles</h2>

    <?php if (isset($error_message)): ?>
        <p style="color:red;"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Nom du Groupe / Famille</th>
                <th>Nombre de clients</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($groupes): ?>
                <?php foreach ($groupes as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nom_groupe']) ?></td>
                        <td><?= htmlspecialchars($row['nombre_clients']) ?></td>
                        <td>
                            <a href="groupes.php?edit=<?= $row['id_groupe'] ?>" class="btn_modifier">Modifier</a>

                            <form method="POST" action="groupes.php" onsubmit="return confirm('Supprimer ce groupe ?');" style="display:inline;">
                                <input type="hidden" name="action" value="supprimer_groupe">
                                <input type="hidden" name="id_groupe" value="<?= $row['id_groupe'] ?>">
                                <button type="submit" class="btn_supprimer">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3">Aucun groupe ou famille trouvé.</td></tr>
            <?php endif; ?>
        </tbody>
        <h3>Ajouter un nouveau groupe</h3>
        <form method="POST" action="groupes.php" class="form-ajout-groupe">
            <input type="hidden" name="action" value="ajouter_groupe">
            <input class="champ" type="text" name="nom_groupe" placeholder="Nom du groupe" required>
            <button type="submit" class="btn_ajout">Ajouter</button>
        </form>

    <?php if (isset($_GET['edit'])): ?>
        <?php
        $edit_id = intval($_GET['edit']);
        $stmt_edit = $pdo->prepare("SELECT * FROM groupes WHERE id_groupe = :id");
        $stmt_edit->execute([':id' => $edit_id]);
        $groupe = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        ?>
        <?php if ($groupe): ?>
            <h3>Modifier le groupe : <?= htmlspecialchars($groupe['nom']) ?></h3>
            <form method="POST" action="groupes.php">
                <input type="hidden" name="action" value="modifier_groupe">
                <input type="hidden" name="id_groupe" value="<?= $groupe['id_groupe'] ?>">
                <input class="champ" type="text" name="nouveau_nom" value="<?= htmlspecialchars($groupe['nom']) ?>" required>
                <button type="submit" class="btn_modifier">Enregistrer</button>
                <a href="groupes.php" class="btn_annuler">Annuler</a>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    </table>
    

</main>

</body>
</html>
