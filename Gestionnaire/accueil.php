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

// Vérification de la session utilisateur
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Bienvenue sur le Portail Ski Club</h1>
    <nav>
        <ul>
            <li><a href="clients.php">Clients</a></li>
            <li><a href="groupes.php">Groupes</a></li>
            <li><a href="chambres.php">Chambres</a></li>
            <li><a href="reservations.php">Réservations</a></li>
            <li><a href="admin.php">Admin</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        </ul>
    </nav>
</header>

<main>
    <h2>Tableau de bord de l'accueil</h2>

    <?php
    // Nombre total de clients
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM clients");
    $clients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Nombre total de groupes
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM groupes");
    $groupes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Chambres non attribuées
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM reservation WHERE Chambre IS NULL OR Chambre = ''");
    $non_attribuees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Arrivées du jour
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM reservation WHERE Date_de_debut = :today");
    $stmt->execute([':today' => $today]);
    $arrivees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Départs du jour
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM reservation WHERE Date_de_fin = :today");
    $stmt->execute([':today' => $today]);
    $departs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    ?>

    <section class="dashboard">
        <div class="card">
            <h3>Clients</h3>
            <p><?= $clients ?> clients enregistrés</p>
        </div>
        <div class="card">
            <h3>Groupes</h3>
            <p><?= $groupes ?> groupes</p>
        </div>
        <div class="card">
            <h3>Chambres à attribuer</h3>
            <p><?= $non_attribuees ?> chambres non attribuées</p>
        </div>
        <div class="card">
            <h3>Arrivées aujourd'hui</h3>
            <p><?= $arrivees ?> réservations prévues</p>
        </div>
        <div class="card">
            <h3>Départs aujourd'hui</h3>
            <p><?= $departs ?> clients quittent aujourd'hui</p>
        </div>
    </section>
</main>

</body>
</html>
