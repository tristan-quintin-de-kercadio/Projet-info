<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$user = getCurrentUser();
function getUserSettings($user_id) {
    $conn = getdbConnection();
    
    $query = "SELECT * FROM utilisateurs WHERE id = :user_id AND type_compte = 'client'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $conn = getdbConnection();
    
    switch ($_POST['action']) {
        case 'change_password':
            try {
                $query = "SELECT mot_de_passe FROM utilisateurs WHERE id = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':user_id', $user['id']);
                $stmt->execute();
                $current_user = $stmt->fetch();
                
                if (!password_verify($_POST['current_password'], $current_user['mot_de_passe'])) {
                    echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
                    exit;
                }
                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    echo json_encode(['success' => false, 'message' => 'Les nouveaux mots de passe ne correspondent pas']);
                    exit;
                }
                if (strlen($_POST['new_password']) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
                    exit;
                }
                $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $update_query = "UPDATE utilisateurs SET mot_de_passe = :new_password, updated_at = NOW() WHERE id = :user_id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':new_password', $new_password_hash);
                $update_stmt->bindParam(':user_id', $user['id']);
                
                if ($update_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_preferences':
            try {
                echo json_encode(['success' => true, 'message' => 'Préférences mises à jour avec succès']);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_account':
            try {
                $query = "SELECT mot_de_passe FROM utilisateurs WHERE id = :user_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':user_id', $user['id']);
                $stmt->execute();
                $current_user = $stmt->fetch();
                
                if (!password_verify($_POST['password_confirm'], $current_user['mot_de_passe'])) {
                    echo json_encode(['success' => false, 'message' => 'Mot de passe incorrect']);
                    exit;
                }
                $update_query = "UPDATE utilisateurs SET statut = 'inactif', updated_at = NOW() WHERE id = :user_id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':user_id', $user['id']);
                
                if ($update_stmt->execute()) {
                    $cancel_query = "UPDATE creneaux SET statut = 'libre', etudiant_nom = NULL, etudiant_email = NULL, notes = NULL, updated_at = NOW() WHERE etudiant_email = :email AND date_creneau >= CURDATE()";
                    $cancel_stmt = $conn->prepare($cancel_query);
                    $cancel_stmt->bindParam(':email', $user['email']);
                    $cancel_stmt->execute();
                    session_destroy();
                    
                    echo json_encode(['success' => true, 'message' => 'Compte désactivé avec succès', 'redirect' => 'index.php']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la désactivation']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
    }
}

$userSettings = getUserSettings($user['id']);
if (!$userSettings) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Sportify | Omnes Education</title>
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
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding: 3rem 0;
        }
        .settings-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .nav-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s;
        }

        .nav-tab.active {
            background: #2563eb;
            color: white;
        }

        .nav-tab:hover:not(.active) {
            background: #f1f5f9;
            color: #2563eb;
        }
        .settings-section {
            display: none;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .settings-section.active {
            display: block;
        }

        .section-title {
            color: #1e40af;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .form-group input:disabled {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        .info-card {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card.warning {
            background: #fef3c7;
            border-color: #f59e0b;
        }

        .info-card.danger {
            background: #fef2f2;
            border-color: #dc2626;
        }

        .info-card h4 {
            margin-bottom: 1rem;
            color: inherit;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2563eb;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .preference-item:last-child {
            border-bottom: none;
        }

        .preference-info h4 {
            margin: 0 0 0.25rem 0;
            color: #374151;
        }

        .preference-info p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .modal-title {
            color: #dc2626;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .settings-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .nav-tab {
                text-align: center;
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
                <a href="mon_profil.php">👤 Mon Profil</a>
                <a href="mes_reservations.php">📅 Mes Réservations</a>
                <a href="logout.php">🚪 Déconnexion</a>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container">
            <h1>⚙️ Paramètres</h1>
            <p>Gérez vos préférences et paramètres de sécurité</p>
        </div>
    </section>
    <section class="main-content">
        <div class="container">
            <div class="settings-nav">
                <button class="nav-tab active" onclick="showSection('security')">🔒 Sécurité</button>
                <button class="nav-tab" onclick="showSection('preferences')">🔧 Préférences</button>
                <button class="nav-tab" onclick="showSection('account')">👤 Compte</button>
            </div>
            <div id="security-section" class="settings-section active">
                <h2 class="section-title">🔒 Sécurité du compte</h2>
                
                <div class="info-card">
                    <h4>ℹ️ Sécurité de votre compte</h4>
                    <p>Votre compte est protégé par un mot de passe. Nous vous recommandons de le changer régulièrement et d'utiliser un mot de passe fort contenant au moins 8 caractères avec des lettres, chiffres et symboles.</p>
                </div>

                <form id="passwordForm">
                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel :</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe :</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe :</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        🔑 Changer le mot de passe
                    </button>
                </form>
            </div>

            <div id="preferences-section" class="settings-section">
                <h2 class="section-title">🔧 Préférences</h2>
                
                <div class="info-card">
                    <h4>📧 Notifications par email</h4>
                    <p>Configurez les types de notifications que vous souhaitez recevoir par email.</p>
                </div>

                <form id="preferencesForm">
                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Confirmations de réservation</h4>
                            <p>Recevoir un email de confirmation lors de chaque réservation</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Rappels de rendez-vous</h4>
                            <p>Recevoir un rappel 24h avant votre rendez-vous</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Newsletter Sportify</h4>
                            <p>Recevoir nos actualités et conseils sportifs</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <h4>Offres promotionnelles</h4>
                            <p>Recevoir des informations sur nos offres spéciales</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                        💾 Enregistrer les préférences
                    </button>
                </form>
            </div>
            <div id="account-section" class="settings-section">
                <h2 class="section-title">👤 Gestion du compte</h2>
                
                <div class="info-card">
                    <h4>📊 Informations du compte</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <div>
                            <strong>Email :</strong><br>
                            <?= htmlspecialchars($userSettings['email']) ?>
                        </div>
                        <div>
                            <strong>Type de compte :</strong><br>
                            <?= ucfirst($userSettings['type_compte']) ?>
                        </div>
                        <div>
                            <strong>Statut :</strong><br>
                            <span style="color: #059669;">✅ <?= ucfirst($userSettings['statut']) ?></span>
                        </div>
                        <div>
                            <strong>Membre depuis :</strong><br>
                            <?= date('d/m/Y', strtotime($userSettings['date_creation'])) ?>
                        </div>
                    </div>
                </div>

                <div class="info-card warning">
                    <h4>⚠️ Données personnelles</h4>
                    <p>Conformément au RGPD, vous avez le droit de demander l'accès, la rectification ou la suppression de vos données personnelles. Contactez-nous à <strong>privacy@sportify-omnes.fr</strong> pour toute demande.</p>
                </div>

                <div class="info-card danger">
                    <h4>🗑️ Supprimer mon compte</h4>
                    <p><strong>Attention :</strong> Cette action désactivera votre compte et annulera toutes vos réservations futures. Cette action est irréversible.</p>
                    <button class="btn btn-danger" onclick="openDeleteModal()" style="margin-top: 1rem;">
                        🗑️ Désactiver mon compte
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">⚠️ Confirmer la désactivation</div>
            <p>Êtes-vous absolument sûr de vouloir désactiver votre compte ?</p>
            <div style="background: #fef2f2; padding: 1rem; border-radius: 8px; margin: 1rem 0; text-align: left;">
                <strong>Cette action va :</strong>
                <ul style="margin: 0.5rem 0;">
                    <li>Désactiver votre compte définitivement</li>
                    <li>Annuler toutes vos réservations futures</li>
                    <li>Vous déconnecter automatiquement</li>
                </ul>
            </div>
            
            <form id="deleteForm">
                <div class="form-group" style="text-align: left; margin: 1rem 0;">
                    <label for="password_confirm">Confirmez avec votre mot de passe :</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-danger">
                        Confirmer la désactivation
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="notification" id="notification">
        ✅ <span id="notification-text">Action effectuée avec succès !</span>
    </div>

    <script>
        function showSection(sectionName) {
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(sectionName + '-section').classList.add('active');
            event.target.classList.add('active');
        }
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                showNotification('Les mots de passe ne correspondent pas', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', document.getElementById('current_password').value);
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('passwordForm').reset();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
            });
        });
        document.getElementById('preferencesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_preferences');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
            });
        });
        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.getElementById('deleteForm').reset();
        }
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('DERNIÈRE CONFIRMATION : Voulez-vous vraiment désactiver votre compte ?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_account');
            formData.append('password_confirm', document.getElementById('password_confirm').value);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || 'index.php';
                    }, 2000);
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
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>