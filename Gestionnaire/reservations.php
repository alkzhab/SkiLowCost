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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Attribution de chambres
        $hasChambre = false;
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'chambre_') === 0) {
                $hasChambre = true;
                break;
            }
        }

        if ($hasChambre) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'chambre_') === 0) {
                    $id_res = intval(substr($key, 8));
                    $chambre = trim($value);

                    $stmt = $pdo->prepare("UPDATE reservation SET chambre = :chambre WHERE id_reservation = :id");
                    $stmt->execute([':chambre' => $chambre, ':id' => $id_res]);
                    logAction($pdo, $_SESSION['id_user'], "Attribution de la chambre $chambre à la réservation ID $id_res");
                }
            }
        }

        // Actions sur les réservations
        if (isset($_POST['action'], $_POST['id_reservation'])) {
            $id_res = intval($_POST['id_reservation']);

            if ($_POST['action'] === 'accepter') {
                $pdo->prepare("UPDATE reservation SET statut = 'acceptée' WHERE id_reservation = :id")
                    ->execute([':id' => $id_res]);
                logAction($pdo, $_SESSION['id_user'], "Réservation ID $id_res acceptée");

            } elseif ($_POST['action'] === 'refuser') {
                $pdo->prepare("UPDATE reservation SET statut = 'refusée' WHERE id_reservation = :id")
                    ->execute([':id' => $id_res]);
                logAction($pdo, $_SESSION['id_user'], "Réservation ID $id_res refusée");

            } elseif ($_POST['action'] === 'supprimer') {
                $pdo->prepare("DELETE FROM reservation WHERE id_reservation = :id")
                    ->execute([':id' => $id_res]);
                logAction($pdo, $_SESSION['id_user'], "Réservation ID $id_res supprimée");
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        logAction($pdo, $_SESSION['id_user'], "Erreur dans les opérations sur réservation : " . $e->getMessage());
        die("Erreur lors du traitement : " . $e->getMessage());
    }

    header("Location: reservations.php");
    exit();
}


// Récupération des réservations
$sql = "
    SELECT 
        r.id_reservation,
        r.id_groupe,
        r.date_de_debut,
        r.date_de_fin,
        r.tarif,
        r.chambre,
        r.statut,
        g.nom AS nom_groupe,
        c.nom AS nom_client,
        c.prenom AS prenom_client
    FROM reservation r
    JOIN groupes g ON r.id_groupe = g.id_groupe
    JOIN clients c ON c.groupe_id = g.id_groupe
    WHERE c.Prenom = (
        SELECT MIN(c2.prenom)
        FROM clients c2
        WHERE c2.groupe_id = g.id_groupe
    )
    ORDER BY r.date_de_debut DESC
";

$stmt = $pdo->query($sql);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Réservations</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        .btn { padding: 5px 10px; margin: 2px; }
        .acceptee { color: green; font-weight: bold; }
        .refusee { color: red; font-weight: bold; }
        .en-attente { color: orange; font-weight: bold; }
    </style>
</head>
<body>
<header>
    <h1>Réservations</h1>
    <nav>
        <ul>
            <li><a href="accueil.php">Accueil</a></li>
            <li><a href="clients.php">Clients</a></li>
            <li><a href="groupes.php">Groupes</a></li>
            <li><a href="chambres.php">Chambres</a></li>
            <li><a href="admin.php">Admin</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        </ul>
    </nav>
</header>

<main>
    <h2>Liste des Réservations</h2>

    <form method="POST" action="reservations.php">
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Groupe</th>
                    <th>Date de début</th>
                    <th>Date de fin</th>
                    <th>Chambre</th>
                    <th>Tarif (€)</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $row):
                    $affichage_groupe = ($row['nom_groupe'] === $row['nom_client']) 
                        ? htmlspecialchars($row['nom_client']) 
                        : htmlspecialchars($row['nom_groupe']);

                    $statut = $row['statut'] ?? 'en attente';
                    $class_statut = $statut === 'acceptée' ? 'acceptee' : ($statut === 'refusée' ? 'refusee' : 'en-attente');
                ?>
                <tr>
                    <td><?= $affichage_groupe ?></td>
                    <td><?= (new DateTime($row['date_de_debut']))->format('d-m-Y') ?></td>
                    <td><?= (new DateTime($row['date_de_fin']))->format('d-m-Y') ?></td>
                    <td>
                        <input class="input-chambre" type="text" name="chambre_<?= $row['id_reservation'] ?>" style="width: 140px;" 
                            value="<?= htmlspecialchars($row['chambre'] ?? '') ?>" placeholder="chambre" />
                    </td>
                    <td><?= number_format($row['tarif'], 2, ',', ' ') ?></td>
                    <td class="<?= $class_statut ?>"><?= ucfirst($statut) ?></td>
                    <td>
                        <form method="POST" action="reservations.php" class="form-action">
                            <input type="hidden" name="id_reservation" value="<?= $row['id_reservation'] ?>">
                            <input type="hidden" name="action" value="accepter">
                            <button type="submit" class="btn btn-accepter">Accepter</button>
                        </form>

                        <form method="POST" action="reservations.php" class="form-action">
                            <input type="hidden" name="id_reservation" value="<?= $row['id_reservation'] ?>">
                            <input type="hidden" name="action" value="refuser">
                            <button type="submit" class="btn btn-refuser">Refuser</button>
                        </form>

                        <form method="POST" action="reservations.php" class="form-action" onsubmit="return confirm('Supprimer cette réservation ?');">
                            <input type="hidden" name="id_reservation" value="<?= $row['id_reservation'] ?>">
                            <input type="hidden" name="action" value="supprimer">
                            <button type="submit" class="btn btn-supprimer">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <a href="ajout_client.php" class="btn_ajout">Ajouter une réservation</a>
        </table>
        <button type="submit" class="btn_ajout">Enregistrer les chambres</button>
       
    </form>
</main>
</body>
</html>
