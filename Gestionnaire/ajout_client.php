<?php
session_start();

// Vérification de la session
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
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

function logAction(PDO $pdo, $id_user, $action) {
    $stmt = $pdo->prepare("INSERT INTO log_actions (id_user, action) VALUES (:id_user, :action)");
    $stmt->execute([
        ':id_user' => $id_user,
        ':action' => $action
    ]);
}

$erreurs = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {

        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $date_naissance = $_POST['date_naissance'];
        $adresse = $_POST['adresse'];
        $email = $_POST['email'];
        $telephone = $_POST['telephone'];
        $niveau = $_POST['niveau'];
        $taille = $_POST['taille'];
        $poids = $_POST['poids'];
        $pointure = $_POST['pointure'];
        $formule = $_POST['formule'];
        $type_reservation = $_POST['type-reservation'];

        $groupe_id = NULL;

        function getOrCreateGroupeId($conn, $nom_groupe) {
            $stmt = $conn->prepare("SELECT Id_groupe FROM groupes WHERE Nom = :nom");
            $stmt->execute(['nom' => trim($nom_groupe)]);
            $existing_id = $stmt->fetchColumn();

            if ($existing_id) return $existing_id;

            $stmt = $conn->prepare("INSERT INTO groupes (Nom) VALUES (:nom) RETURNING Id_groupe");
            $stmt->execute(['nom' => $nom_groupe]);
            return $stmt->fetchColumn();
        }

        if ($type_reservation === 'individuel' || $type_reservation === 'famille') {
            $groupe_id = getOrCreateGroupeId($conn, $nom);
        } elseif ($type_reservation === 'groupe') {
            $nom_groupe = $_POST['groupename'] ?? '';
            $groupe_id = getOrCreateGroupeId($conn, $nom_groupe);
        }

        $stmt = $conn->prepare("INSERT INTO clients (Nom, Prenom, Date_naissance, Adresse, Email, Telephone, Niveau, Taille, Poids, Pointure, Formule, groupe_id) VALUES (:nom, :prenom, :date_naissance, :adresse, :email, :telephone, :niveau, :taille, :poids, :pointure, :formule, :groupe_id) RETURNING Id_client");
        $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'date_naissance' => $date_naissance,
            'adresse' => $adresse,
            'email' => $email,
            'telephone' => $telephone,
            'niveau' => $niveau,
            'taille' => $taille,
            'poids' => $poids,
            'pointure' => $pointure,
            'formule' => $formule,
            'groupe_id' => $groupe_id
        ]);
        $id_client_principal = $stmt->fetchColumn();

        if ($type_reservation === 'famille') {
            $members_count = 0;
            foreach ($_POST as $key => $value){
                if (strpos($key, 'family-firstname-') === 0){
                    $members_count++;
                }
            }

            for ($i = 1; $i <= $members_count; $i++) {
                $m_prenom = $_POST["family-firstname-$i"] ?? null;
                $m_nom = $_POST["family-lastname-$i"] ?? null;
                $m_date_naissance = $_POST["family-dob-$i"] ?? null;
                $m_niveau = $_POST["niveau-$i"] ?? null;
                $m_taille = (int)($_POST["taille-$i"] ?? 0);
                $m_poids = (int)($_POST["poids-$i"] ?? 0);
                $m_pointure = (int)($_POST["pointure-$i"] ?? 0);
                $m_formule = $_POST["formule-$i"] ?? null;

                $stmt = $conn->prepare("INSERT INTO clients (Nom, Prenom, Date_naissance, Adresse, Email, Telephone, Niveau, Taille, Poids, Pointure, Formule, groupe_id) VALUES (:nom, :prenom, :date_naissance, NULL, NULL, NULL, :niveau, :taille, :poids, :pointure, :formule, :groupe_id)");
                $stmt->execute([
                    'nom' => $m_nom,
                    'prenom' => $m_prenom,
                    'date_naissance' => $m_date_naissance,
                    'niveau' => $m_niveau,
                    'taille' => $m_taille,
                    'poids' => $m_poids,
                    'pointure' => $m_pointure,
                    'formule' => $m_formule,
                    'groupe_id' => $groupe_id
                ]);
            }
        }

        $date_debut = $_POST['arrival-date'];
        $date_fin = $_POST['departure-date'];

        if ($type_reservation === 'individuel') {
            $tarif = ($formule === 'skieur') ? 660 : 570;

            $stmt = $conn->prepare("INSERT INTO reservation (Id_groupe, Date_de_debut, Date_de_fin, Tarif) VALUES (:groupe_id, :date_debut, :date_fin, :tarif)");
            $stmt->execute([
                'groupe_id' => $groupe_id,
                'date_debut' => $date_debut,
                'date_fin' => $date_fin,
                'tarif' => $tarif
            ]);

        } elseif ($type_reservation === 'famille') {
            $nombre_personnes = $members_count + 1;
            $all_formules = [
                ['type' => $formule, 'Date_naissance' => $date_naissance]
            ];

            foreach ($_POST as $key => $value) {
                if (strpos($key, 'family-firstname-') === 0) {
                    $index = explode('-', $key)[2];
                    $dob = $_POST["family-dob-$index"] ?? null;
                    $formule_m = $_POST["formule-$index"] ?? 'standard';
                    if ($dob) {
                        $all_formules[] = ['type' => $formule_m, 'Date_naissance' => $dob];
                    }
                }
            }

            $total_tarif = 0;
            $today = new DateTime();

            foreach ($all_formules as $f) {
                $age = $today->diff(new DateTime($f['Date_naissance']))->y;
                if ($age < 2) {
                    $tarif = 0;
                } else {
                    $tarif = ($f['type'] === 'skieur') ? 510 : 420;
                    if ($age < 12) $tarif *= 0.8;
                }
                $total_tarif += $tarif;
            }

            if ($nombre_personnes % 2 !== 0) {
                $total_tarif += 150; // lit vide
            }

            $stmt = $conn->prepare("INSERT INTO reservation (Id_groupe, Date_de_debut, Date_de_fin, Tarif) VALUES (:groupe_id, :date_debut, :date_fin, :tarif)");
            $stmt->execute([
                'groupe_id' => $groupe_id,
                'date_debut' => $date_debut,
                'date_fin' => $date_fin,
                'tarif' => $total_tarif
            ]);

        } elseif ($type_reservation === 'groupe') {
            $tarif_individuel = ($formule === 'skieur') ? 510 : 420;

            $stmt = $conn->prepare("SELECT Id_Reservation, Tarif FROM reservation WHERE Id_groupe = :groupe_id");
            $stmt->execute(['groupe_id' => $groupe_id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($res) {
                $nouveau_tarif = $res['tarif'] + $tarif_individuel;
                $stmt = $conn->prepare("UPDATE reservation SET Tarif = :tarif WHERE Id_Reservation = :id_reservation");
                $stmt->execute([
                    'tarif' => $nouveau_tarif,
                    'id_reservation' => $res['id_reservation']
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO reservation (Id_groupe, Date_de_debut, Date_de_fin, Tarif) VALUES (:groupe_id, :date_debut, :date_fin, :tarif)");
                $stmt->execute([
                    'groupe_id' => $groupe_id,
                    'date_debut' => $date_debut,
                    'date_fin' => $date_fin,
                    'tarif' => $tarif_individuel
                ]);
            }
        }

        if (isset($_SESSION['id_user'])) {
            logAction($conn, $_SESSION['id_user'], "Nouvelle réservation pour groupe ID $groupe_id");
        }

        echo "Réservation réussie !";
    } catch (PDOException $e) {
        echo "Erreur de base de données : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajout d'un Client</title>
    <link rel="stylesheet" href="ajout_client.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
      <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" />
</head>
<body>

<header>
    <nav>
        <ul>
            <li><a href="accueil.php">← Retour à l'accueil</a></li>
        </ul>
    </nav>
</header>

<main>
    <?php if (!empty($erreurs)): ?>
        <div class="alert-erreur">
            <ul>
                <?php foreach ($erreurs as $erreur): ?>
                    <li><?= htmlspecialchars($erreur) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section id="booking" class="booking-section">
      <div class="container">
        <h2 class="section-title">Ajouter un client</h2>
        <form method="POST" action="ajout_client.php" id="booking-form" class="booking-card">

          
          <div class="form-section active" data-step="0">
            <div class="form-row">
              <div class="form-group">
                <label for="lastname">Nom</label>
                <input type="text" id="lastname" name="nom" required />
              </div>
              <div class="form-group">
                <label for="firstname">Prénom</label>
                <input type="text" id="firstname" name="prenom" required />
              </div>
            </div>

            <div class="form-group">
              <label for="birthdate">Date de naissance</label>
              <input type="date" id="birthdate" name="date_naissance" required />
            </div>

            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" required />
            </div>

            <div class="form-group">
              <label for="phone">Téléphone</label>
              <input type="tel" id="phone" name="telephone" required />
            </div>

            <div class="form-group">
              <label for="address">Adresse</label>
              <input type="text" id="address" name="adresse" required />
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="niveau">Niveau</label>
                <select id="niveau" name="niveau" required>
                  <option value="">-- Choisir --</option>
                  <option value="débutant">Débutant</option>
                  <option value="moyen">Moyen</option>
                  <option value="confirmé">Confirmé</option>
                </select>
              </div>

              <div class="form-group">
                <label for="taille">Taille (cm)</label>
                <input type="number" id="taille" name="taille" min="50" max="250" required />
              </div>

              <div class="form-group">
                <label for="poids">Poids (kg)</label>
                <input type="number" id="poids" name="poids" min="20" max="300" required />
              </div>

              <div class="form-group">
                <label for="pointure">Pointure</label>
                <input type="number" id="pointure" name="pointure" min="20" max="50" required />
              </div>
            </div>

            <div class="form-group">
              <label for="formule">Formule</label>
              <select id="formule" name="formule" required>
                <option value="">-- Choisir votre formule --</option>
                <option value="skieur">Skieur</option>
                <option value="non-skieur">Non-skieur</option>
              </select>
            </div>

            <div class="form-group">
              <label for="type-reservation">Type de réservation</label>
              <select id="type-reservation" name="type-reservation" required>
                <option value="">-- Choisir --</option>
                <option value="individuel">Individuel</option>
                <option value="famille">Famille</option>
                <option value="groupe">Groupe</option>
              </select>
            </div>

            <!-- Groupe -->
            <div id="group-name-container" class="form-group" style="display: none;">
              <label for="group-name">Nom du groupe</label>
              <input type="text" id="group-name" name="groupename" placeholder="Ex : Les Skieurs Fous">
              <small style="color: #777;">Tous les membres du groupe doivent indiquer le même nom lors de leur réservation.</small>
            </div>

            <!-- Famille -->
            <div id="family-members-container" class="form-group" style="display: none;">
              <label>Membres de la famille</label>
              <div id="family-members-list"></div>
              <button type="button" class="btn btn-outline" id="add-family-member-btn">Ajouter un membre</button>
            </div>



            <h3>Dates de séjour</h3>
            <p class="hint">Séjours de 6 jours, du dimanche au samedi.</p>
            <div class="form-row">
              <div class="form-group">
                <label for="arrival-date">Date d'arrivée</label>
                <input type="text" id="arrival-date" name="arrival-date" required readonly />
                <small>* Uniquement les dimanches</small>
              </div>

              <div class="form-group">
                <label for="departure-date">Date de départ</label>
                <input type="text" id="departure-date" name="departure-date" required readonly />
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Confirmer</button>
            </div>
          </div>
        </form>

    </div>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // Flatpickr : dimanches uniquement
    flatpickr("#arrival-date", {
      dateFormat: "Y-m-d",
      minDate: "today",
      enable: [date => date.getDay() === 0],
      onChange: function(selectedDates) {
        if (selectedDates.length) {
          const arrival = selectedDates[0];
          const departure = new Date(arrival);
          departure.setDate(arrival.getDate() + 6);
          function formatDateLocal(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
            }

            document.getElementById("departure-date").value = formatDateLocal(departure);

        }
      }
    });

    document.getElementById("booking-form").addEventListener("submit", function(e) {
      alert("Ajout du client avec succès");
    });


    const typeReservation = document.getElementById('type-reservation');
    const groupNameContainer = document.getElementById('group-name-container');
    const familyMembersContainer = document.getElementById('family-members-container');
    const familyMembersList = document.getElementById('family-members-list');
    const addFamilyMemberBtn = document.getElementById('add-family-member-btn');

    typeReservation.addEventListener('change', function () {
      const value = this.value;

      // Réinitialise tout
      groupNameContainer.style.display = 'none';
      familyMembersContainer.style.display = 'none';
      familyMembersList.innerHTML = '';

      if (value === 'groupe') {
        groupNameContainer.style.display = 'block';
      } else if (value === 'famille') {
        familyMembersContainer.style.display = 'block';
      }
    });

    // Ajouter un membre de la famille
    addFamilyMemberBtn.addEventListener('click', function () {
    const memberIndex = familyMembersList.children.length + 1;
    const memberDiv = document.createElement('div');
    memberDiv.classList.add('family-member');
    memberDiv.innerHTML = `
      <div class="form-row">
        <div class="form-group">
          <label>Membre ${memberIndex} - Prénom</label>
          <input type="text" name="family-firstname-${memberIndex}" required>
        </div>
        <div class="form-group">
          <label>Nom</label>
          <input type="text" name="family-lastname-${memberIndex}" required>
        </div>
        <div class="form-group">
          <label>Date de naissance</label>
          <input type="date" name="family-dob-${memberIndex}" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="niveau-${memberIndex}">Niveau</label>
          <select id="niveau-${memberIndex}" name="niveau-${memberIndex}" required>
            <option value="">-- Choisir --</option>
            <option value="débutant">Débutant</option>
            <option value="moyen">Moyen</option>
            <option value="confirmé">Confirmé</option>
          </select>
        </div>

        <div class="form-group">
          <label for="taille-${memberIndex}">Taille (cm)</label>
          <input type="number" id="taille-${memberIndex}" name="taille-${memberIndex}" min="50" max="250" required />
        </div>

        <div class="form-group">
          <label for="poids-${memberIndex}">Poids (kg)</label>
          <input type="number" id="poids-${memberIndex}" name="poids-${memberIndex}" min="20" max="300" required />
        </div>

        <div class="form-group">
          <label for="pointure-${memberIndex}">Pointure</label>
          <input type="number" id="pointure-${memberIndex}" name="pointure-${memberIndex}" min="20" max="50" required />
        </div>
      </div>

      <div class="form-group">
        <label for="formule-${memberIndex}">Formule</label>
        <select id="formule-${memberIndex}" name="formule-${memberIndex}" required>
          <option value="">-- Choisir votre formule --</option>
          <option value="skieur">Skieur</option>
          <option value="non-skieur">Non-skieur</option>
        </select>
      </div>

      <hr />
    `;
    familyMembersList.appendChild(memberDiv);
  });


  </script>

</body>
</html>
