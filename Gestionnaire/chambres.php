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

// Suppression d'une chambre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer_chambre') {
    $id_chambre = intval($_POST['id_chambre']);

    try {
        $pdo->beginTransaction();

        // Supprime la chambre
        $stmt = $pdo->prepare("DELETE FROM chambre WHERE id_chambre = :id_chambre");
        $stmt->execute([':id_chambre' => $id_chambre]);

        $pdo->commit();

        logAction($pdo, $_SESSION['id_user'], "Suppression de la chambre ID $id_chambre");

        header("Location: chambres.php"); // évite resoumission
        exit();
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
        logAction($pdo, $_SESSION['id_user'], "Échec de suppression de la chambre $id_chambre : " . $e->getMessage());
    }
}

// Récupérer toutes les chambres
$sql = "SELECT * FROM chambre ORDER BY numero_chambre ASC";
$stmt = $pdo->query($sql);
$chambres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion du toggle "Libre"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_libre'], $_POST['id_chambre'])) {
    $id_chambre_toggle = (int) $_POST['id_chambre'];

    // Récupérer la valeur actuelle de Libre
    $sql_get = "SELECT libre FROM chambre WHERE id_chambre = :id";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([':id' => $id_chambre_toggle]);
    $row = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $nouveau_libre = $row['libre'] ? 0 : 1; // Inverse la valeur

        // Mettre à jour la valeur Libre
        $sql_update = "UPDATE chambre SET libre = :libre WHERE id_chambre = :id";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            ':libre' => $nouveau_libre,
            ':id' => $id_chambre_toggle
        ]);
        logAction($pdo, $_SESSION['id_user'], "Changement de disponibilité : Chambre ID $id_chambre_toggle mise à " . ($nouveau_libre ? 'libre' : 'occupée'));

        // Redirection pour éviter soumission multiple
        header("Location: chambres.php");
        exit();
    }
}

// Modification d'une chambre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_chambre') {
    $id_chambre = (int)$_POST['id_chambre'];
    $numero_chambre = trim($_POST['numero_chambre']);
    $nombre_lits = (int)$_POST['nombre_lits'];
    $superficie = (float)$_POST['superficie'];
    $balcon = isset($_POST['balcon']) ? 1 : 0;
    $vue = trim($_POST['vue']);
    $etage = (int)$_POST['etage'];
    $batiment = trim($_POST['batiment']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE chambre 
            SET numero_chambre = :numero, nombre_lits = :lits, superfecie = :superficie,
                balcon = :balcon, vue = :vue, etage = :etage, batiment = :batiment
            WHERE id_chambre = :id
        ");

        $stmt->execute([
            ':numero' => $numero_chambre,
            ':lits' => $nombre_lits,
            ':superficie' => $superficie,
            ':balcon' => $balcon,
            ':vue' => $vue,
            ':etage' => $etage,
            ':batiment' => $batiment,
            ':id' => $id_chambre
        ]);

        $pdo->commit();
        logAction($pdo, $_SESSION['id_user'], "Modification chambre ID $id_chambre");

        header("Location: chambres.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Erreur lors de la modification : " . $e->getMessage();
        logAction($pdo, $_SESSION['id_user'], "Échec de modification chambre ID $id_chambre : " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Chambres</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Liste des Chambres</h1>
    <nav>
        <ul>
            <li><a href="accueil.php">Accueil</a></li>
            <li><a href="clients.php">Clients</a></li>
            <li><a href="groupes.php">Groupes</a></li>
            <li><a href="reservations.php">Réservations</a></li>
            <li><a href="admin.php">Admin</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        </ul>
    </nav>
</header>

<main>
    <h2>Chambres</h2>

    <?php if (isset($error_message)): ?>
        <p style="color:red;"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Numéro</th>
                <th>Type</th>
                <th>Superficie</th>
                <th>Balcon</th>
                <th>Vue</th>
                <th>Étage</th>
                <th>Bâtiment</th>
                <th>Libre</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($chambres): ?>
                <?php foreach ($chambres as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['numero_chambre']) ?></td>
                        <td><?= htmlspecialchars($row['nombre_lits']) ?> places</td>
                        <td><?= htmlspecialchars($row['superfecie']) ?> m²</td>
                        <td><?= $row['balcon'] ? 'Oui' : 'Non' ?></td>
                        <td><?= htmlspecialchars($row['vue']) ?></td>
                        <td><?= htmlspecialchars($row['etage']) ?></td>
                        <td><?= htmlspecialchars($row['batiment']) ?></td>
                        <td>
                            <form method="POST" action="chambres.php" style="display:inline;">
                                <input type="hidden" name="id_chambre" value="<?= (int)$row['id_chambre'] ?>">
                                <button 
                                    class="btn-toggle <?= $row['libre'] ? 'btn-oui' : 'btn-non' ?>" 
                                    type="submit" 
                                    name="toggle_libre" 
                                    value="1"
                                >
                                    <?= $row['libre'] ? 'Oui' : 'Non' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <a href="chambres.php?edit=<?= $row['id_chambre'] ?>" class="btn_modifier">Modifier</a>
                            
                            <form method="POST" action="chambres.php" onsubmit="return confirm('Supprimer cette chambre ?');" style="display:inline;">
                                <input type="hidden" name="action" value="supprimer_chambre">
                                <input type="hidden" name="id_chambre" value="<?= (int)$row['id_chambre'] ?>">
                                <button type="submit" class="btn_supprimer">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9">Aucune chambre trouvée.</td></tr>
            <?php endif; ?>
        </tbody>
        <a href="ajout_chambre.php" class="btn_ajout">Ajouter une chambre</a>
        <?php if (isset($_GET['edit'])): ?>
            <?php
                $edit_id = (int)$_GET['edit'];
                $stmt_edit = $pdo->prepare("SELECT * FROM chambre WHERE id_chambre = :id");
                $stmt_edit->execute([':id' => $edit_id]);
                $chambre = $stmt_edit->fetch(PDO::FETCH_ASSOC);
            ?>
            <?php if ($chambre): ?>
            <h3>Modifier la chambre <?= htmlspecialchars($chambre['numero_chambre']) ?></h3>
            <form method="POST" action="chambres.php" class="form-modif-chambre">
                <input type="hidden" name="action" value="modifier_chambre">
                <input type="hidden" name="id_chambre" value="<?= $chambre['id_chambre'] ?>">

                <input type="text" name="numero_chambre" value="<?= htmlspecialchars($chambre['numero_chambre']) ?>" required>
                <input type="number" name="nombre_lits" value="<?= (int)$chambre['nombre_lits'] ?>" min="1" required>
                <input type="number" name="superficie" step="0.1" value="<?= (float)$chambre['superfecie'] ?>" required>
                <label><input type="checkbox" name="balcon" <?= $chambre['balcon'] ? 'checked' : '' ?>> Balcon</label>
                <input type="text" name="vue" value="<?= htmlspecialchars($chambre['vue']) ?>" required>
                <input type="number" name="etage" value="<?= (int)$chambre['etage'] ?>" required>
                <input type="text" name="batiment" value="<?= htmlspecialchars($chambre['batiment']) ?>" required>

                <button type="submit" class="btn_modifier">Enregistrer</button>
                <a href="chambres.php" class="btn_annuler">Annuler</a>
            </form>
            <?php endif; ?>
        <?php endif; ?>

    </table>
</main>

</body>
</html>
