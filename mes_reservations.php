<?php
require_once 'config.php';
require_once 'auth.php';
requireLogin();
$user = getCurrentUser();
function getClientReservations($user_email) {
    $conn = getdbConnection();
    
    $query = "SELECT c.*,
                     co.nom as coach_nom,
                     co.prenom as coach_prenom,
                     co.specialite as coach_specialite,
                     co.photo as coach_photo,
                     co.bureau as coach_bureau,
                     co.telephone as coach_telephone,
                     co.email as coach_email,
                     TIME_FORMAT(c.heure_debut, '%Hh%i') as heure_debut_format,
                     TIME_FORMAT(c.heure_fin, '%Hh%i') as heure_fin_format,
                     CONCAT(TIME_FORMAT(c.heure_debut, '%Hh%i'), ' - ', TIME_FORMAT(c.heure_fin, '%Hh%i')) as creneau_display,
                     CASE 
                        WHEN c.date_creneau > CURDATE() THEN 'futur'
                        WHEN c.date_creneau = CURDATE() AND c.heure_debut > CURTIME() THEN 'futur'
                        WHEN c.date_creneau = CURDATE() AND c.heure_debut <= CURTIME() AND c.heure_fin > CURTIME() THEN 'en_cours'
                        ELSE 'passe'
                     END as statut_rdv
              FROM creneaux c
              JOIN coachs co ON c.coach_id = co.id
              WHERE c.etudiant_email = :email AND c.statut = 'reserve'
              ORDER BY c.date_creneau DESC, c.heure_debut DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $user_email);
    $stmt->execute();
    
    return $stmt->fetchAll();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'cancel_reservation') {
        $creneau_id = $_POST['creneau_id'];
        
        $conn = getdbConnection();
        
        try {
            $query_check = "SELECT id, date_creneau, heure_debut FROM creneaux 
                           WHERE id = :creneau_id AND etudiant_email = :email AND statut = 'reserve'";
            $stmt_check = $conn->prepare($query_check);
            $stmt_check->bindParam(':creneau_id', $creneau_id);
            $stmt_check->bindParam(':email', $user['email']);
            $stmt_check->execute();
            
            $reservation = $stmt_check->fetch();
            
            if (!$reservation) {
                echo json_encode(['success' => false, 'message' => 'Réservation non trouvée ou déjà annulée']);
                exit;
            }
            $reservation_datetime = $reservation['date_creneau'] . ' ' . $reservation['heure_debut'];
            $limite_annulation = date('Y-m-d H:i:s', strtotime($reservation_datetime . ' -2 hours'));
            
            if (date('Y-m-d H:i:s') > $limite_annulation) {
                echo json_encode(['success' => false, 'message' => 'Impossible d\'annuler - Délai de 2h dépassé']);
                exit;
            }
            $query_cancel = "UPDATE creneaux 
                            SET statut = 'libre',
                                etudiant_nom = NULL,
                                etudiant_email = NULL,
                                notes = NULL,
                                updated_at = NOW()
                            WHERE id = :creneau_id";
            
            $stmt_cancel = $conn->prepare($query_cancel);
            $stmt_cancel->bindParam(':creneau_id', $creneau_id);
            
            if ($stmt_cancel->execute()) {
                echo json_encode(['success' => true, 'message' => 'Réservation annulée avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'annulation']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        exit;
    }
}
$reservations = getClientReservations($user['email']);
$reservations_futures = [];
$reservations_en_cours = [];
$reservations_passees = [];

foreach ($reservations as $reservation) {
    switch ($reservation['statut_rdv']) {
        case 'futur':
            $reservations_futures[] = $reservation;
            break;
        case 'en_cours':
            $reservations_en_cours[] = $reservation;
            break;
        case 'passe':
            $reservations_passees[] = $reservation;
            break;
    }
}
function formatDateFrancais($date) {
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    
    $timestamp = strtotime($date);
    $jour_semaine = $jours[date('w', $timestamp)];
    $jour = date('d', $timestamp);
    $mois_nom = $mois[date('n', $timestamp)];
    $annee = date('Y', $timestamp);
    
    return $jour_semaine . ' ' . $jour . ' ' . $mois_nom . ' ' . $annee;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations - Sportify | Omnes Education</title>
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
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 4px solid #2563eb;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
        }
        .section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .section-title {
            color: #1e40af;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .reservation-card {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .reservation-card:hover {
            border-color: #2563eb;
            transform: translateY(-2px);
        }

        .reservation-card.future {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }

        .reservation-card.current {
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
        }

        .reservation-card.past {
            border-left: 4px solid #64748b;
            background: #f8fafc;
            opacity: 0.8;
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .reservation-date {
            font-size: 1.1rem;
            font-weight: bold;
            color: #1e40af;
        }

        .reservation-time {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2563eb;
        }

        .reservation-details {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1.5rem;
            align-items: center;
        }

        .coach-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .coach-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #2563eb;
        }

        .coach-details h4 {
            margin: 0 0 0.25rem 0;
            color: #1e40af;
        }

        .coach-details p {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .reservation-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-cancel {
            background: #dc2626;
            color: white;
        }

        .btn-cancel:hover {
            background: #b91c1c;
        }

        .btn-contact {
            background: #2563eb;
            color: white;
        }

        .btn-contact:hover {
            background: #1e40af;
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }

        .empty-state img {
            width: 120px;
            height: 120px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-future {
            background: #dcfce7;
            color: #166534;
        }

        .status-current {
            background: #fef3c7;
            color: #92400e;
        }

        .status-past {
            background: #f1f5f9;
            color: #475569;
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

        .btn-confirm {
            background: #dc2626;
            color: white;
        }

        .btn-cancel-modal {
            background: #6b7280;
            color: white;
        }
        @media (max-width: 768px) {
            .reservation-details {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .reservation-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
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
                <a href="parametres.php">⚙️ Paramètres</a>
                <a href="logout.php">🚪 Déconnexion</a>
            </div>
        </div>
    </nav>
    <section class="page-header">
        <div class="container">
            <h1>📅 Mes Réservations</h1>
            <p>Consultez et gérez tous vos rendez-vous sportifs</p>
        </div>
    </section>
    <section class="main-content">
        <div class="container">
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?= count($reservations_futures) ?></div>
                    <div class="stat-label">RDV à venir</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($reservations_en_cours) ?></div>
                    <div class="stat-label">En cours</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($reservations_passees) ?></div>
                    <div class="stat-label">Terminées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($reservations) ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <?php if (!empty($reservations_en_cours)): ?>
            <div class="section">
                <h2 class="section-title">🔴 Séances en cours</h2>
                <?php foreach ($reservations_en_cours as $reservation): ?>
                <div class="reservation-card current">
                    <div class="reservation-header">
                        <div>
                            <div class="reservation-date"><?= formatDateFrancais($reservation['date_creneau']) ?></div>
                            <div class="reservation-time"><?= $reservation['creneau_display'] ?></div>
                        </div>
                        <span class="status-badge status-current">En cours</span>
                    </div>
                    <div class="reservation-details">
                        <div class="coach-info">
                            <img src="<?= htmlspecialchars($reservation['coach_photo'] ?: '/images_projet/default_coach.jpg') ?>" alt="Coach" class="coach-photo">
                            <div class="coach-details">
                                <h4><?= htmlspecialchars($reservation['coach_prenom'] . ' ' . $reservation['coach_nom']) ?></h4>
                                <p><?= htmlspecialchars($reservation['coach_specialite']) ?></p>
                                <p>📍 <?= htmlspecialchars($reservation['coach_bureau']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="section">
                <h2 class="section-title">🟢 Prochains rendez-vous</h2>
                <?php if (empty($reservations_futures)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📅</div>
                    <h3>Aucun rendez-vous à venir</h3>
                    <p>Vous n'avez actuellement aucun rendez-vous planifié.</p>
                    <a href="index.php#coachs" class="btn btn-contact">Prendre rendez-vous</a>
                </div>
                <?php else: ?>
                    <?php foreach ($reservations_futures as $reservation): ?>
                    <div class="reservation-card future">
                        <div class="reservation-header">
                            <div>
                                <div class="reservation-date"><?= formatDateFrancais($reservation['date_creneau']) ?></div>
                                <div class="reservation-time"><?= $reservation['creneau_display'] ?></div>
                            </div>
                            <span class="status-badge status-future">À venir</span>
                        </div>
                        <div class="reservation-details">
                            <div class="coach-info">
                                <img src="<?= htmlspecialchars($reservation['coach_photo'] ?: '/images_projet/default_coach.jpg') ?>" alt="Coach" class="coach-photo">
                                <div class="coach-details">
                                    <h4><?= htmlspecialchars($reservation['coach_prenom'] . ' ' . $reservation['coach_nom']) ?></h4>
                                    <p><?= htmlspecialchars($reservation['coach_specialite']) ?></p>
                                    <p>📍 <?= htmlspecialchars($reservation['coach_bureau']) ?></p>
                                    <?php if ($reservation['coach_telephone']): ?>
                                    <p>📞 <?= htmlspecialchars($reservation['coach_telephone']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="reservation-actions">
                                <button class="btn btn-contact" onclick="contactCoach('<?= htmlspecialchars($reservation['coach_email']) ?>')">
                                    💬 Contacter
                                </button>
                                <button class="btn btn-cancel" onclick="confirmCancelReservation(<?= $reservation['id'] ?>, '<?= formatDateFrancais($reservation['date_creneau']) ?>', '<?= $reservation['creneau_display'] ?>')">
                                    ❌ Annuler
                                </button>
                            </div>
                        </div>
                        <?php if ($reservation['notes']): ?>
                        <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(37, 99, 235, 0.1); border-radius: 8px;">
                            <strong>📝 Notes :</strong> <?= htmlspecialchars($reservation['notes']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($reservations_passees)): ?>
            <div class="section">
                <h2 class="section-title">📋 Historique des consultations</h2>
                <?php foreach (array_slice($reservations_passees, 0, 5) as $reservation): ?>
                <div class="reservation-card past">
                    <div class="reservation-header">
                        <div>
                            <div class="reservation-date"><?= formatDateFrancais($reservation['date_creneau']) ?></div>
                            <div class="reservation-time"><?= $reservation['creneau_display'] ?></div>
                        </div>
                        <span class="status-badge status-past">Terminée</span>
                    </div>
                    <div class="reservation-details">
                        <div class="coach-info">
                            <img src="<?= htmlspecialchars($reservation['coach_photo'] ?: '/images_projet/default_coach.jpg') ?>" alt="Coach" class="coach-photo">
                            <div class="coach-details">
                                <h4><?= htmlspecialchars($reservation['coach_prenom'] . ' ' . $reservation['coach_nom']) ?></h4>
                                <p><?= htmlspecialchars($reservation['coach_specialite']) ?></p>
                                <p>📍 <?= htmlspecialchars($reservation['coach_bureau']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($reservations_passees) > 5): ?>
                <div style="text-align: center; margin-top: 1rem;">
                    <button class="btn btn-contact" onclick="toggleHistorique()">
                        Voir plus d'historique (<?= count($reservations_passees) - 5 ?> séances)
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-title">⚠️ Confirmer l'annulation</div>
            <p>Êtes-vous sûr de vouloir annuler votre rendez-vous ?</p>
            <div id="cancelDetails"></div>
            <p style="color: #dc2626; font-size: 0.9rem;">
                <strong>Attention :</strong> Cette action est irréversible.
            </p>
            <div class="modal-actions">
                <button class="btn btn-confirm" onclick="cancelReservation()">
                    Confirmer l'annulation
                </button>
                <button class="btn btn-cancel-modal" onclick="closeCancelModal()">
                    Garder le RDV
                </button>
            </div>
        </div>
    </div>
    <div class="notification" id="notification">
        ✅ <span id="notification-text">Action effectuée avec succès !</span>
    </div>

    <script>
        let currentReservationId = null;
        function confirmCancelReservation(reservationId, date, time) {
            currentReservationId = reservationId;
            document.getElementById('cancelDetails').innerHTML = `
                <div style="background: #fef2f2; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                    <strong>📅 ${date}</strong><br>
                    <strong>🕐 ${time}</strong>
                </div>
            `;
            document.getElementById('cancelModal').style.display = 'block';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
            currentReservationId = null;
        }
        function cancelReservation() {
            if (!currentReservationId) return;
            
            const formData = new FormData();
            formData.append('action', 'cancel_reservation');
            formData.append('creneau_id', currentReservationId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeCancelModal();
                
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
                closeCancelModal();
                showNotification('Erreur de connexion', 'error');
                console.error('Erreur:', error);
            });
        }
        function contactCoach(coachEmail) {
            if (coachEmail) {
                window.location.href = `mailto:${coachEmail}?subject=Question concernant mon rendez-vous&body=Bonjour,\n\nJ'ai une question concernant mon rendez-vous à venir.\n\nCordialement,\n<?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>`;
            } else {
                showNotification('Email du coach non disponible', 'error');
            }
        }
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
            const modal = document.getElementById('cancelModal');
            if (event.target === modal) {
                closeCancelModal();
            }
        }
        function toggleHistorique() {
            showNotification('Fonctionnalité en cours de développement', 'info');
        }
    </script>
</body>
</html>