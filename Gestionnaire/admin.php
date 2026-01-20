<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['admin'] === false) {
    header("Location: accueil.php");
    exit();
}

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


// Ajout utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $identifiant = trim($_POST['identifiant']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $admin = isset($_POST['admin']) ? 1 : 0;

    if ($identifiant && $mot_de_passe) {
        $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare('INSERT INTO "user" (identifiant, mot_de_passe, admin) VALUES (:identifiant, :mot_de_passe, :admin)');
            $stmt->execute([
                ':identifiant' => $identifiant,
                ':mot_de_passe' => $hash,
                ':admin' => $admin,
            ]);
            $message = "Utilisateur ajouté avec succès.";
        } catch (PDOException $e) {
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } else {
        $error = "Tous les champs sont obligatoires.";
    }
}

// Suppression utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $id_user = intval($_POST['id_user']);
    try {
        $stmt = $pdo->prepare('DELETE FROM "user" WHERE id_user = :id_user');
        $stmt->execute([':id_user' => $id_user]);
        $message = "Utilisateur supprimé.";
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Récupération liste utilisateurs
$stmt = $pdo->query('SELECT id_user, identifiant, admin FROM "user" ORDER BY identifiant');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


try {
    $stmt = $pdo->query("
        SELECT log.id, log.action, log.timestamp, u.identifiant 
        FROM log_actions log
        LEFT JOIN \"user\" u ON log.id_user = u.id_user
        ORDER BY log.timestamp DESC
        LIMIT 100
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}

function logAction(PDO $pdo, $id_user, $action) {
    $stmt = $pdo->prepare("INSERT INTO log_actions (id_user, action) VALUES (:id_user, :action)");
    $stmt->execute([
        ':id_user' => $id_user,
        ':action' => $action
    ]);
}

try {
    $total_reservations = $pdo->query("SELECT COUNT(*) FROM reservation")->fetchColumn();
    $reservations_par_statut = $pdo->query("SELECT statut, COUNT(*) AS count FROM reservation GROUP BY statut")->fetchAll(PDO::FETCH_ASSOC);
    $total_users = $pdo->query('SELECT COUNT(*) FROM "user"')->fetchColumn();
    $total_admins = $pdo->query('SELECT COUNT(*) FROM "user" WHERE admin = TRUE')->fetchColumn();
    $total_revenus = $pdo->query("SELECT COALESCE(SUM(tarif), 0) FROM reservation")->fetchColumn();

    $recent_reservations = $pdo->query(
        "SELECT r.id_reservation, r.date_de_debut, r.date_de_fin, r.tarif, r.statut, g.nom AS groupe_nom 
        FROM reservation r
        JOIN groupes g ON r.id_groupe = g.id_groupe
        ORDER BY r.date_de_debut DESC
        LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erreur base de données : " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Administration</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>

<nav>
    <button class="tab-button active" data-target="users">Gestion Utilisateurs</button>
    <button class="tab-button" data-target="audit">Journal des Modifications</button>
    <button class="tab-button" data-target="dashboard">Statistique</button>
    <a href="accueil.php" class="tab-button">Retour à l'accueil</a>
    <a href="logout.php" class="tab-button">Déconnexion</a>
</nav>

<main>
    <section id="users" class="active">
        <h2>Gestion des Utilisateurs</h2>

        <?php if (isset($message)): ?>
            <p style="color:green;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- Formulaire ajout -->
        <form method="POST">
            <input type="hidden" name="action" value="ajouter" />
            <label>Identifiant : <input type="text" name="identifiant" required></label><br><br>
            <label>Mot de passe : <input type="password" name="mot_de_passe" required></label><br><br>
            <label><input type="checkbox" name="admin"> Administrateur</label><br><br>
            <button type="submit" class="btn">Ajouter un utilisateur</button>
        </form>

        <hr>

        <!-- Liste utilisateurs -->
        <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Identifiant</th>
                    <th>Rôle</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['identifiant']) ?></td>
                    <td><?= $user['admin'] == 1 ? 'Administrateur' : 'Gestionnaire' ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Confirmer la suppression ?');" style="display:inline;">
                            <input type="hidden" name="action" value="supprimer" />
                            <input type="hidden" name="id_user" value="<?= (int)$user['id_user'] ?>" />
                            <button type="submit" class="btn_supprimer">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>


    <section id="audit">
        <h2>Journal des Modifications</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['timestamp']) ?></td>
                            <td><?= htmlspecialchars($log['identifiant'] ?? 'Inconnu') ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Aucune action enregistrée.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section id="dashboard">
        <h2>Statistiques Réservations</h2>

        <div class="dashboard-cards">
            <div class="dashboard-card">
                <h3><?= $total_reservations ?></h3>
                <p>Réservations totales</p>
            </div>
            <div class="dashboard-card">
                <h3><?= $total_users ?></h3>
                <p>Utilisateurs</p>
            </div>
            <div class="dashboard-card">
                <h3><?= $total_admins ?></h3>
                <p>Administrateurs</p>
            </div>
            <div class="dashboard-card">
                <h3><?= number_format($total_revenus, 2, ',', ' ') ?> €</h3>
                <p>Revenus totaux</p>
            </div>
        </div>

        <h3>Réservations par statut</h3>
        <table class="dashboard-table">
            <thead>
                <tr><th>Statut</th><th>Nombre</th></tr>
            </thead>
            <tbody>
                <?php foreach ($reservations_par_statut as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['statut']) ?></td>
                        <td><?= $row['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Dernières réservations</h3>
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Groupe</th>
                    <th>Date début</th>
                    <th>Date fin</th>
                    <th>Tarif (€)</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_reservations as $res): ?>
                    <tr>
                        <td><?= $res['id_reservation'] ?></td>
                        <td><?= htmlspecialchars($res['groupe_nom']) ?></td>
                        <td><?= $res['date_de_debut'] ?></td>
                        <td><?= $res['date_de_fin'] ?></td>
                        <td><?= number_format($res['tarif'], 2, ',', ' ') ?></td>
                        <td><?= htmlspecialchars($res['statut']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

</main>

<script>
    const buttons = document.querySelectorAll('nav button.tab-button');
    const sections = document.querySelectorAll('main section');

    buttons.forEach(button => {
        button.addEventListener('click', () => {
            buttons.forEach(btn => btn.classList.remove('active'));
            sections.forEach(sec => sec.classList.remove('active'));

            button.classList.add('active');
            const target = button.getAttribute('data-target');
            document.getElementById(target).classList.add('active');
        });
    });
</script>

</body>
</html>
