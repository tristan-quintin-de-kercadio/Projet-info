<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$user = getCurrentUser();
function getClientProfile($user_id) {
    $conn = getdbConnection();
    
    $query = "SELECT u.*, 
                     COUNT(c.id) as nb_reservations_total,
                     COUNT(CASE WHEN c.date_creneau >= CURDATE() THEN 1 END) as nb_reservations_futures,
                     COUNT(CASE WHEN c.date_creneau < CURDATE() THEN 1 END) as nb_consultations_passees,
                     MAX(c.date_creneau) as derniere_reservation
              FROM utilisateurs u 
              LEFT JOIN creneaux c ON c.etudiant_email = u.email AND c.statut = 'reserve'
              WHERE u.id = :user_id AND u.type_compte = 'client'
              GROUP BY u.id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_profile') {
        $conn = getdbConnection();
        
        try {
            $query = "UPDATE utilisateurs SET 
                        nom = :nom,
                        prenom = :prenom,
                        telephone = :telephone,
                        updated_at = NOW()
                      WHERE id = :user_id";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':nom', $_POST['nom']);
            $stmt->bindParam(':prenom', $_POST['prenom']);
            $stmt->bindParam(':telephone', $_POST['telephone']);
            $stmt->bindParam(':user_id', $user['id']);
            
            if ($stmt->execute()) {
                $_SESSION['user_nom'] = $_POST['nom'];
                $_SESSION['user_prenom'] = $_POST['prenom'];
                
                echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        exit;
    }
}
$profile = getClientProfile($user['id']);
if (!$profile) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Sportify | Omnes Education</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f8fafc;
            color: #333;
            line-height: 1.6;
        }
        .navbar {
            background-color: #1f2937;
            padding: 1rem 0;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            color: #f59e0b;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #f59e0b;
        }
        .page-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            margin-bottom: 0;
            opacity: 0.9;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding: 3rem 0;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-top: 4px solid #2563eb;
        }

        .card-title {
            color: #2563eb;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
        .profile-photo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #2563eb;
            margin-bottom: 1rem;
        }

        .photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .form-group input:disabled {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }
        .account-info {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .account-info h4 {
            color: #0369a1;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(14, 165, 233, 0.1);
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #374151;
        }

        .info-value {
            color: #64748b;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #059669;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: #dc2626;
        }
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">🏋️ Sportify</a>
            <div class="nav-links">
                <a href="index.php">🏠 Accueil</a>
                <a href="mes_reservations.php">📅 Mes Réservations</a>
                <a href="parametres.php">⚙️ Paramètres</a>
                <a href="logout.php">🚪 Déconnexion</a>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container">
            <h1>👤 Mon Profil</h1>
            <p>Gérez vos informations personnelles et consultez vos statistiques</p>
        </div>
    </section>
    <section class="main-content">
        <div class="container">
            <div class="profile-grid">
                <div>
                    <div class="info-card">
                        <h3 class="card-title">📸 Photo de profil</h3>
                        
                        <div class="profile-photo-section">
                            <?php if (!empty($profile['photo_profil'])): ?>
                                <img src="<?= htmlspecialchars($profile['photo_profil']) ?>" alt="Photo de profil" class="profile-photo">
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <?= strtoupper(substr($profile['prenom'], 0, 1) . substr($profile['nom'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <p><strong><?= htmlspecialchars($profile['prenom'] . ' ' . $profile['nom']) ?></strong></p>
                            <p style="color: #64748b;">Membre Omnes Education</p>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?= $profile['nb_reservations_total'] ?: 0 ?></div>
                                <div class="stat-label">Réservations totales</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $profile['nb_reservations_futures'] ?: 0 ?></div>
                                <div class="stat-label">RDV à venir</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $profile['nb_consultations_passees'] ?: 0 ?></div>
                                <div class="stat-label">Consultations passées</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php if ($profile['derniere_reservation']): ?>
                                        <?= date('d/m', strtotime($profile['derniere_reservation'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                                <div class="stat-label">Dernière réservation</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="account-info">
                        <h4>ℹ️ Informations de votre compte</h4>
                        <div class="info-row">
                            <span class="info-label">Type de compte :</span>
                            <span class="info-value"><?= ucfirst($profile['type_compte']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email :</span>
                            <span class="info-value"><?= htmlspecialchars($profile['email']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Statut :</span>
                            <span class="info-value">
                                <span style="color: #059669; font-weight: 600;">✅ <?= ucfirst($profile['statut']) ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Membre depuis :</span>
                            <span class="info-value"><?= date('d/m/Y', strtotime($profile['date_creation'])) ?></span>
                        </div>
                        <?php if ($profile['derniere_connexion']): ?>
                        <div class="info-row">
                            <span class="info-label">Dernière connexion :</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($profile['derniere_connexion'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="info-card">
                        <h3 class="card-title">✏️ Modifier mes informations</h3>
                        
                        <form id="profileForm">
                            <div class="form-group">
                                <label for="nom">Nom :</label>
                                <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($profile['nom']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="prenom">Prénom :</label>
                                <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($profile['prenom']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email :</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" disabled>
                                <small style="color: #64748b;">L'email ne peut pas être modifié pour des raisons de sécurité</small>
                            </div>

                            <div class="form-group">
                                <label for="telephone">Téléphone :</label>
                                <input type="tel" id="telephone" name="telephone" value="<?= htmlspecialchars($profile['telephone'] ?: '') ?>" placeholder="01.23.45.67.89">
                            </div>

                            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                                <button type="submit" class="btn btn-primary">
                                    💾 Enregistrer les modifications
                                </button>
                                <a href="mes_reservations.php" class="btn btn-secondary">
                                    📅 Voir mes réservations
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="notification" id="notification">
        ✅ <span id="notification-text">Action effectuée avec succès !</span>
    </div>

    <script>
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('nom', document.getElementById('nom').value);
            formData.append('prenom', document.getElementById('prenom').value);
            formData.append('telephone', document.getElementById('telephone').value);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
            });
        });
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const text = document.getElementById('notification-text');
            
            text.textContent = message;
            notification.className = 'notification';
            if (type === 'error') {
                notification.classList.add('error');
            }
            notification.classList.add('show');
            
            setTimeout(function() {
                notification.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>